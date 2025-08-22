<?php
/**
 * Script AJAX pour récupérer les modules liés à un mail
 * À placer dans : /custom/roundcubemodule/ajax/get_mail_modules.php
 * 
 * @package    RoundcubeModule
 * @version    1.0.0
 */

// Configuration d'affichage des erreurs pour debug
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers pour AJAX
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    // Charger Dolibarr
    $res = 0;
    if (!$res && file_exists("../../../main.inc.php")) {
        $res = @include("../../../main.inc.php");
    }
    if (!$res && file_exists("../../../../main.inc.php")) {
        $res = @include("../../../../main.inc.php");
    }
    if (!$res) {
        throw new Exception('Impossible de charger Dolibarr');
    }

    global $db, $conf, $langs, $user;

    // Vérifier la base de données
    if (!$db || !is_object($db)) {
        throw new Exception('Connexion base de données non disponible');
    }

    // Récupérer l'UID du mail
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    $mail_id = isset($_GET['mail_id']) ? intval($_GET['mail_id']) : 0;
    $message_id = isset($_GET['message_id']) ? $db->escape($_GET['message_id']) : '';

    // Log pour debug
    error_log("get_mail_modules.php - Recherche avec uid=$uid, mail_id=$mail_id, message_id=$message_id");

    if (!$uid && !$mail_id && !$message_id) {
        throw new Exception('Paramètre uid, mail_id ou message_id requis');
    }

    // Construire la requête SQL pour trouver le mail
    $sql = "SELECT m.rowid, m.subject, m.from_email, m.date_received, m.message_id, m.imap_uid 
            FROM ".MAIN_DB_PREFIX."mailboxmodule_mail as m 
            WHERE ";
    
    if ($mail_id > 0) {
        $sql .= "m.rowid = ".$mail_id;
    } elseif ($uid > 0) {
        $sql .= "m.imap_uid = ".$uid;
    } else {
        $sql .= "m.message_id = '".$message_id."'";
    }
    
    $sql .= " LIMIT 1";

    error_log("SQL Mail: " . $sql);

    $resql = $db->query($sql);
    if (!$resql) {
        throw new Exception('Erreur SQL mail: ' . $db->lasterror());
    }

    if ($db->num_rows($resql) == 0) {
        // Mail non trouvé dans la base
        echo json_encode([
            'status' => 'NOT_FOUND',
            'message' => 'Mail non trouvé dans la base de données',
            'uid' => $uid,
            'debug' => [
                'searched_uid' => $uid,
                'searched_mail_id' => $mail_id,
                'searched_message_id' => $message_id
            ]
        ]);
        exit;
    }

    $mail = $db->fetch_object($resql);
    $mail_id = $mail->rowid;

    // Récupérer les modules liés
    $sql_links = "SELECT ml.rowid, ml.target_type, ml.target_id, ml.target_name, ml.date_created 
                  FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links as ml 
                  WHERE ml.fk_mail = ".$mail_id."
                  ORDER BY ml.date_created DESC";

    error_log("SQL Links: " . $sql_links);

    $resql_links = $db->query($sql_links);
    if (!$resql_links) {
        throw new Exception('Erreur SQL liens: ' . $db->lasterror());
    }

    $modules = [];
    while ($obj = $db->fetch_object($resql_links)) {
        // Enrichir avec des informations supplémentaires selon le type
        $module_info = [
            'id' => $obj->target_id,
            'type' => $obj->target_type,
            'name' => $obj->target_name,
            'date_linked' => $obj->date_created,
            'link_id' => $obj->rowid
        ];

        // Récupérer des infos supplémentaires selon le type
        switch($obj->target_type) {
            case 'thirdparty':
            case 'societe':
                $sql_extra = "SELECT nom as name, email, code_client, code_fournisseur 
                             FROM ".MAIN_DB_PREFIX."societe 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->name;
                    $module_info['email'] = $extra->email;
                    $module_info['code'] = $extra->code_client ?: $extra->code_fournisseur;
                }
                break;

            case 'contact':
                $sql_extra = "SELECT CONCAT(firstname, ' ', lastname) as name, email, phone 
                             FROM ".MAIN_DB_PREFIX."socpeople 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->name;
                    $module_info['email'] = $extra->email;
                    $module_info['phone'] = $extra->phone;
                }
                break;

            case 'project':
            case 'projet':
                $sql_extra = "SELECT title as name, ref, dateo, datee 
                             FROM ".MAIN_DB_PREFIX."projet 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->name ?: $extra->ref;
                    $module_info['ref'] = $extra->ref;
                    $module_info['date_start'] = $extra->dateo;
                    $module_info['date_end'] = $extra->datee;
                }
                break;

            case 'propal':
                $sql_extra = "SELECT ref, ref_client, total_ht, datep 
                             FROM ".MAIN_DB_PREFIX."propal 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->ref;
                    $module_info['ref_client'] = $extra->ref_client;
                    $module_info['amount'] = $extra->total_ht;
                    $module_info['date'] = $extra->datep;
                }
                break;

            case 'commande':
                $sql_extra = "SELECT ref, ref_client, total_ht, date_commande 
                             FROM ".MAIN_DB_PREFIX."commande 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->ref;
                    $module_info['ref_client'] = $extra->ref_client;
                    $module_info['amount'] = $extra->total_ht;
                    $module_info['date'] = $extra->date_commande;
                }
                break;

            case 'invoice':
            case 'facture':
                $sql_extra = "SELECT ref, ref_client, total_ht, datef 
                             FROM ".MAIN_DB_PREFIX."facture 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->ref;
                    $module_info['ref_client'] = $extra->ref_client;
                    $module_info['amount'] = $extra->total_ht;
                    $module_info['date'] = $extra->datef;
                }
                break;

            case 'ticket':
                $sql_extra = "SELECT ref, subject, type_code, severity_code, datec 
                             FROM ".MAIN_DB_PREFIX."ticket 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->ref . ' - ' . $extra->subject;
                    $module_info['subject'] = $extra->subject;
                    $module_info['type'] = $extra->type_code;
                    $module_info['severity'] = $extra->severity_code;
                    $module_info['date'] = $extra->datec;
                }
                break;

            case 'contract':
            case 'contrat':
                $sql_extra = "SELECT ref, ref_customer, date_contrat 
                             FROM ".MAIN_DB_PREFIX."contrat 
                             WHERE rowid = ".$obj->target_id;
                $resql_extra = $db->query($sql_extra);
                if ($resql_extra && $db->num_rows($resql_extra) > 0) {
                    $extra = $db->fetch_object($resql_extra);
                    $module_info['name'] = $extra->ref;
                    $module_info['ref_customer'] = $extra->ref_customer;
                    $module_info['date'] = $extra->date_contrat;
                }
                break;
        }

        $modules[] = $module_info;
    }

    // Récupérer aussi les statistiques (nombre de mails liés au même tiers, etc.)
    $stats = [];
    if ($mail->from_email) {
        // Compter les mails du même expéditeur
        $sql_count = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                     WHERE from_email = '".$db->escape($mail->from_email)."'";
        $resql_count = $db->query($sql_count);
        if ($resql_count) {
            $count = $db->fetch_object($resql_count);
            $stats['mails_from_sender'] = $count->nb;
        }
    }

    // Log du résultat
    error_log("Trouvé " . count($modules) . " modules liés au mail ID " . $mail_id);

    // Retourner les données
    echo json_encode([
        'status' => 'OK',
        'mail_info' => [
            'id' => $mail->rowid,
            'uid' => $mail->imap_uid,
            'subject' => $mail->subject,
            'from' => $mail->from_email,
            'date' => $mail->date_received,
            'message_id' => $mail->message_id
        ],
        'modules' => $modules,
        'stats' => $stats,
        'debug' => [
            'searched_uid' => $uid,
            'found_uid' => $mail->imap_uid,
            'mail_id' => $mail_id
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Erreur get_mail_modules.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>