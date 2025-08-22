<?php
/**
 * API de synchronisation des mails entre Roundcube et le bandeau Dolibarr
 * Compatible avec le module mymailbox_module existant
 * 
 * @package    RoundcubeModule
 * @version    2.0.0
 */

// Charger Dolibarr
$res = 0;
$paths = ['../../../main.inc.php', '../../../../main.inc.php', '../../../../../main.inc.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'Impossible de charger Dolibarr']));
}

// Vérifier les droits
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'Accès refusé']));
}

header('Content-Type: application/json');

$action = GETPOST('action', 'alpha');
$response = ['success' => false];

try {
    switch ($action) {
        
        case 'test':
            // Point de test simple
            $response = [
                'success' => true, 
                'message' => 'API fonctionnelle',
                'user' => $user->login,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
        
        case 'check_mail_status':
            // Vérifier si un mail est déjà sauvegardé
            $message_id = GETPOST('message_id', 'alpha');
            $imap_uid = GETPOST('imap_uid', 'alpha');
            $from_email = GETPOST('from_email', 'email');
            $subject = GETPOST('subject', 'none');
            
            // Rechercher dans la table des mails avec plusieurs critères
            $sql = "SELECT m.rowid, m.subject, m.from_email, m.date_received, m.fk_soc,
                           m.direction, m.imap_uid, m.message_id,
                           s.nom as societe_name, s.email as societe_email,
                           COUNT(DISTINCT ml.rowid) as nb_links,
                           COUNT(DISTINCT a.rowid) as nb_attachments
                    FROM ".MAIN_DB_PREFIX."mailboxmodule_mail m
                    LEFT JOIN ".MAIN_DB_PREFIX."societe s ON m.fk_soc = s.rowid
                    LEFT JOIN ".MAIN_DB_PREFIX."mailboxmodule_mail_links ml ON ml.fk_mail = m.rowid
                    LEFT JOIN ".MAIN_DB_PREFIX."mailboxmodule_attachment a ON a.fk_mail = m.rowid
                    WHERE 1=1 ";
            
            // Recherche par message_id en priorité
            if (!empty($message_id)) {
                $sql .= " AND m.message_id = '".$db->escape($message_id)."'";
            } 
            // Sinon par UID
            elseif (!empty($imap_uid)) {
                $sql .= " AND m.imap_uid = '".$db->escape($imap_uid)."'";
            } 
            // Sinon par email et sujet (moins fiable)
            elseif (!empty($from_email) && !empty($subject)) {
                $sql .= " AND m.from_email = '".$db->escape($from_email)."'";
                $sql .= " AND m.subject = '".$db->escape($subject)."'";
            } else {
                throw new Exception('Critères de recherche insuffisants');
            }
            
            $sql .= " GROUP BY m.rowid";
            $sql .= " LIMIT 1";
            
            $resql = $db->query($sql);
            if ($resql && $obj = $db->fetch_object($resql)) {
                // Mail trouvé - récupérer les liens détaillés
                $links = [];
                $sql_links = "SELECT ml.target_type, ml.target_id, ml.date_creation ";
                $sql_links .= "FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links ml ";
                $sql_links .= "WHERE ml.fk_mail = ".$obj->rowid;
                
                $resql_links = $db->query($sql_links);
                while ($link = $db->fetch_object($resql_links)) {
                    $link_info = [
                        'type' => $link->target_type,
                        'id' => $link->target_id,
                        'date' => $link->date_creation,
                        'label' => getObjectLabel($db, $link->target_type, $link->target_id),
                        'url' => getObjectUrl($link->target_type, $link->target_id)
                    ];
                    $links[] = $link_info;
                }
                
                $response = [
                    'success' => true,
                    'is_saved' => true,
                    'mail' => [
                        'rowid' => $obj->rowid,
                        'subject' => $obj->subject,
                        'from_email' => $obj->from_email,
                        'date_received' => $obj->date_received,
                        'direction' => $obj->direction,
                        'fk_soc' => $obj->fk_soc,
                        'societe_name' => $obj->societe_name,
                        'societe_email' => $obj->societe_email,
                        'nb_links' => $obj->nb_links,
                        'nb_attachments' => $obj->nb_attachments,
                        'links' => $links
                    ]
                ];
            } else {
                // Mail non sauvegardé
                $response = [
                    'success' => true, 
                    'is_saved' => false,
                    'message' => 'Mail non trouvé dans la base'
                ];
            }
            break;
            
        case 'save_mail_from_roundcube':
            // Sauvegarder un mail depuis Roundcube
            $uid = GETPOST('uid', 'alpha');
            $mailbox = GETPOST('mailbox', 'alpha') ?: 'INBOX';
            $subject = GETPOST('subject', 'none');
            $from_email = GETPOST('from_email', 'email');
            $from_name = GETPOST('from_name', 'alpha');
            $date_received = GETPOST('date', 'alpha');
            $message_id = GETPOST('message_id', 'alpha');
            $has_attachments = GETPOST('has_attachments', 'int');
            $fk_soc = GETPOST('fk_soc', 'int');
            
            // Générer un message_id si non fourni
            if (empty($message_id)) {
                $message_id = '<' . uniqid() . '@roundcube.local>';
            }
            
            // Vérifier si le mail n'existe pas déjà
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailboxmodule_mail WHERE ";
            $sql .= "message_id = '".$db->escape($message_id)."'";
            
            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                $obj = $db->fetch_object($resql);
                $response = [
                    'success' => true, 
                    'message' => 'Mail déjà sauvegardé', 
                    'mail_id' => $obj->rowid,
                    'already_exists' => true
                ];
            } else {
                // Convertir la date au format SQL
                $date_sql = null;
                if (!empty($date_received)) {
                    $timestamp = strtotime($date_received);
                    if ($timestamp !== false) {
                        $date_sql = date('Y-m-d H:i:s', $timestamp);
                    }
                }
                if (empty($date_sql)) {
                    $date_sql = date('Y-m-d H:i:s');
                }
                
                // Insérer le mail
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail (";
                $sql .= "message_id, imap_uid, imap_mailbox, subject, from_email, from_name, ";
                $sql .= "date_received, direction, has_attachments, fk_soc, fk_user, date_creation";
                $sql .= ") VALUES (";
                $sql .= "'".$db->escape($message_id)."', ";
                $sql .= "'".$db->escape($uid)."', ";
                $sql .= "'".$db->escape($mailbox)."', ";
                $sql .= "'".$db->escape($subject)."', ";
                $sql .= "'".$db->escape($from_email)."', ";
                $sql .= "'".$db->escape($from_name)."', ";
                $sql .= "'".$db->escape($date_sql)."', ";
                $sql .= "'received', ";
                $sql .= ($has_attachments ? 1 : 0).", ";
                $sql .= ($fk_soc > 0 ? $fk_soc : "NULL").", ";
                $sql .= $user->id.", ";
                $sql .= "NOW())";
                
                if ($db->query($sql)) {
                    $mail_id = $db->last_insert_id(MAIN_DB_PREFIX."mailboxmodule_mail");
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Mail sauvegardé avec succès', 
                        'mail_id' => $mail_id,
                        'created' => true
                    ];
                    
                    // Log l'action
                    dol_syslog("Mail sauvegardé depuis Roundcube: ID=".$mail_id.", Subject=".$subject, LOG_INFO);
                } else {
                    throw new Exception('Erreur SQL lors de la sauvegarde: '.$db->lasterror());
                }
            }
            break;
            
        case 'update_mail_societe':
            // Mettre à jour la société d'un mail
            $mail_id = GETPOST('mail_id', 'int');
            $fk_soc = GETPOST('fk_soc', 'int');
            
            if (empty($mail_id)) {
                throw new Exception('Mail ID requis');
            }
            
            $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_mail ";
            $sql .= "SET fk_soc = ".($fk_soc > 0 ? $fk_soc : "NULL");
            $sql .= " WHERE rowid = ".$mail_id;
            
            if ($db->query($sql)) {
                $response = [
                    'success' => true,
                    'message' => 'Société mise à jour'
                ];
            } else {
                throw new Exception('Erreur SQL: '.$db->lasterror());
            }
            break;
            
        case 'add_mail_link':
            // Ajouter un lien vers un objet Dolibarr
            $mail_id = GETPOST('mail_id', 'int');
            $target_type = GETPOST('target_type', 'alpha');
            $target_id = GETPOST('target_id', 'int');
            
            if (empty($mail_id) || empty($target_type) || empty($target_id)) {
                throw new Exception('Paramètres manquants (mail_id, target_type, target_id)');
            }
            
            // Vérifier si le lien n'existe pas déjà
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links ";
            $sql .= "WHERE fk_mail = ".$mail_id." AND target_type = '".$db->escape($target_type)."' ";
            $sql .= "AND target_id = ".$target_id;
            
            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) == 0) {
                // Créer le lien
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links ";
                $sql .= "(fk_mail, target_type, target_id, date_creation, fk_user_creat) VALUES (";
                $sql .= $mail_id.", '".$db->escape($target_type)."', ".$target_id.", NOW(), ".$user->id.")";
                
                if ($db->query($sql)) {
                    $response = [
                        'success' => true, 
                        'message' => 'Lien ajouté avec succès',
                        'link' => [
                            'type' => $target_type,
                            'id' => $target_id,
                            'label' => getObjectLabel($db, $target_type, $target_id),
                            'url' => getObjectUrl($target_type, $target_id)
                        ]
                    ];
                } else {
                    throw new Exception('Erreur SQL lors de l\'ajout du lien: '.$db->lasterror());
                }
            } else {
                $response = [
                    'success' => true, 
                    'message' => 'Lien déjà existant',
                    'already_exists' => true
                ];
            }
            break;
            
        case 'remove_mail_link':
            // Supprimer un lien
            $mail_id = GETPOST('mail_id', 'int');
            $target_type = GETPOST('target_type', 'alpha');
            $target_id = GETPOST('target_id', 'int');
            
            if (empty($mail_id) || empty($target_type) || empty($target_id)) {
                throw new Exception('Paramètres manquants');
            }
            
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links ";
            $sql .= "WHERE fk_mail = ".$mail_id." AND target_type = '".$db->escape($target_type)."' ";
            $sql .= "AND target_id = ".$target_id;
            
            if ($db->query($sql)) {
                $response = [
                    'success' => true, 
                    'message' => 'Lien supprimé',
                    'deleted_rows' => $db->affected_rows()
                ];
            } else {
                throw new Exception('Erreur SQL lors de la suppression: '.$db->lasterror());
            }
            break;
            
        case 'delete_mail':
            // Supprimer complètement un mail
            $mail_id = GETPOST('mail_id', 'int');
            
            if (empty($mail_id)) {
                throw new Exception('Mail ID requis');
            }
            
            // Supprimer dans l'ordre : attachments, links, puis mail
            $db->begin();
            
            try {
                // Supprimer les pièces jointes
                $sql1 = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_attachment WHERE fk_mail = ".$mail_id;
                $db->query($sql1);
                $deleted_attachments = $db->affected_rows();
                
                // Supprimer les liens
                $sql2 = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links WHERE fk_mail = ".$mail_id;
                $db->query($sql2);
                $deleted_links = $db->affected_rows();
                
                // Supprimer le mail
                $sql3 = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail WHERE rowid = ".$mail_id;
                if (!$db->query($sql3)) {
                    throw new Exception('Impossible de supprimer le mail');
                }
                
                $db->commit();
                
                $response = [
                    'success' => true, 
                    'message' => 'Mail supprimé avec succès',
                    'deleted' => [
                        'mail' => 1,
                        'attachments' => $deleted_attachments,
                        'links' => $deleted_links
                    ]
                ];
                
                dol_syslog("Mail supprimé: ID=".$mail_id, LOG_INFO);
                
            } catch (Exception $e) {
                $db->rollback();
                throw new Exception('Erreur lors de la suppression: ' . $e->getMessage());
            }
            break;
            
        case 'search_societe':
            // Rechercher une société par email ou nom
            $email = GETPOST('email', 'email');
            $search = GETPOST('search', 'alpha');
            
            if (empty($email) && empty($search)) {
                throw new Exception('Email ou terme de recherche requis');
            }
            
            $sql = "SELECT rowid, nom, email, client, fournisseur, code_client, code_fournisseur ";
            $sql .= "FROM ".MAIN_DB_PREFIX."societe ";
            $sql .= "WHERE 1=1 ";
            
            if (!empty($email)) {
                // Extraire le domaine de l'email
                $domain = substr(strrchr($email, "@"), 1);
                $name_part = substr($email, 0, strpos($email, '@'));
                
                $sql .= " AND (";
                $sql .= "email LIKE '%".$db->escape($email)."%' ";
                $sql .= "OR email LIKE '%".$db->escape($domain)."%' ";
                $sql .= "OR nom LIKE '%".$db->escape($name_part)."%' ";
                $sql .= "OR nom LIKE '%".$db->escape($domain)."%' ";
                $sql .= ")";
            } elseif (!empty($search)) {
                $sql .= " AND (";
                $sql .= "nom LIKE '%".$db->escape($search)."%' ";
                $sql .= "OR email LIKE '%".$db->escape($search)."%' ";
                $sql .= ")";
            }
            
            $sql .= " ORDER BY nom ASC LIMIT 20";
            
            $resql = $db->query($sql);
            $societes = [];
            
            while ($obj = $db->fetch_object($resql)) {
                $societes[] = [
                    'rowid' => $obj->rowid,
                    'nom' => $obj->nom,
                    'email' => $obj->email,
                    'client' => $obj->client,
                    'fournisseur' => $obj->fournisseur,
                    'code_client' => $obj->code_client,
                    'code_fournisseur' => $obj->code_fournisseur,
                    'url' => dol_buildpath('/societe/card.php?socid='.$obj->rowid, 2)
                ];
            }
            
            $response = [
                'success' => true, 
                'societes' => $societes,
                'count' => count($societes)
            ];
            break;
            
        case 'search_objects':
            // Rechercher des objets Dolibarr pour les liens
            $type = GETPOST('type', 'alpha');
            $search = GETPOST('search', 'alpha');
            
            if (empty($type)) {
                throw new Exception('Type d\'objet requis');
            }
            
            $objects = [];
            
            switch($type) {
                case 'project':
                    $sql = "SELECT rowid, ref, title FROM ".MAIN_DB_PREFIX."projet ";
                    if ($search) $sql .= "WHERE ref LIKE '%".$db->escape($search)."%' OR title LIKE '%".$db->escape($search)."%' ";
                    $sql .= "ORDER BY ref DESC LIMIT 20";
                    break;
                    
                case 'invoice':
                    $sql = "SELECT rowid, ref, total_ttc FROM ".MAIN_DB_PREFIX."facture ";
                    if ($search) $sql .= "WHERE ref LIKE '%".$db->escape($search)."%' ";
                    $sql .= "ORDER BY ref DESC LIMIT 20";
                    break;
                    
                case 'propal':
                    $sql = "SELECT rowid, ref, total_ht FROM ".MAIN_DB_PREFIX."propal ";
                    if ($search) $sql .= "WHERE ref LIKE '%".$db->escape($search)."%' ";
                    $sql .= "ORDER BY ref DESC LIMIT 20";
                    break;
                    
                case 'order':
                    $sql = "SELECT rowid, ref, total_ttc FROM ".MAIN_DB_PREFIX."commande ";
                    if ($search) $sql .= "WHERE ref LIKE '%".$db->escape($search)."%' ";
                    $sql .= "ORDER BY ref DESC LIMIT 20";
                    break;
                    
                case 'ticket':
                    $sql = "SELECT rowid, ref, subject FROM ".MAIN_DB_PREFIX."ticket ";
                    if ($search) $sql .= "WHERE ref LIKE '%".$db->escape($search)."%' OR subject LIKE '%".$db->escape($search)."%' ";
                    $sql .= "ORDER BY ref DESC LIMIT 20";
                    break;
                    
                default:
                    throw new Exception('Type d\'objet non supporté: ' . $type);
            }
            
            $resql = $db->query($sql);
            while ($obj = $db->fetch_object($resql)) {
                $label = isset($obj->title) ? $obj->ref.' - '.$obj->title : $obj->ref;
                if (isset($obj->subject)) $label .= ' - ' . $obj->subject;
                
                $objects[] = [
                    'rowid' => $obj->rowid,
                    'ref' => $obj->ref,
                    'label' => $label,
                    'url' => getObjectUrl($type, $obj->rowid)
                ];
            }
            
            $response = [
                'success' => true,
                'objects' => $objects,
                'type' => $type
            ];
            break;
            
        default:
            throw new Exception('Action inconnue: '.$action);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false, 
        'error' => $e->getMessage(),
        'action' => $action
    ];
    dol_syslog("Erreur API mail_sync: ".$e->getMessage(), LOG_ERR);
}

echo json_encode($response);

/**
 * Fonction helper pour récupérer le label d'un objet
 */
function getObjectLabel($db, $type, $id) {
    $label = $type.' #'.$id;
    
    $sql = "";
    switch($type) {
        case 'societe':
        case 'thirdparty':
            $sql = "SELECT nom as label FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".$id;
            break;
        case 'project':
            $sql = "SELECT CONCAT(ref, ' - ', title) as label FROM ".MAIN_DB_PREFIX."projet WHERE rowid = ".$id;
            break;
        case 'invoice':
            $sql = "SELECT ref as label FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$id;
            break;
        case 'propal':
            $sql = "SELECT ref as label FROM ".MAIN_DB_PREFIX."propal WHERE rowid = ".$id;
            break;
        case 'order':
            $sql = "SELECT ref as label FROM ".MAIN_DB_PREFIX."commande WHERE rowid = ".$id;
            break;
        case 'contract':
            $sql = "SELECT ref as label FROM ".MAIN_DB_PREFIX."contrat WHERE rowid = ".$id;
            break;
        case 'ticket':
            $sql = "SELECT CONCAT(ref, ' - ', subject) as label FROM ".MAIN_DB_PREFIX."ticket WHERE rowid = ".$id;
            break;
        case 'contact':
            $sql = "SELECT CONCAT(firstname, ' ', lastname) as label FROM ".MAIN_DB_PREFIX."socpeople WHERE rowid = ".$id;
            break;
    }
    
    if ($sql) {
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            $label = $obj->label;
        }
    }
    
    return $label;
}

/**
 * Fonction helper pour récupérer l'URL d'un objet
 */
function getObjectUrl($type, $id) {
    global $conf;
    
    $url = '#';
    
    switch($type) {
        case 'societe':
        case 'thirdparty':
            $url = '/societe/card.php?socid='.$id;
            break;
        case 'project':
            $url = '/projet/card.php?id='.$id;
            break;
        case 'invoice':
            $url = '/compta/facture/card.php?facid='.$id;
            break;
        case 'propal':
            $url = '/comm/propal/card.php?id='.$id;
            break;
        case 'order':
            $url = '/commande/card.php?id='.$id;
            break;
        case 'contract':
            $url = '/contrat/card.php?id='.$id;
            break;
        case 'ticket':
            $url = '/ticket/card.php?id='.$id;
            break;
        case 'contact':
            $url = '/contact/card.php?id='.$id;
            break;
    }
    
    return dol_buildpath($url, 2);
}
?>