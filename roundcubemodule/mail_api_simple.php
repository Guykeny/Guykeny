<?php
/**
 * API complète pour le module Roundcube avec extension classement
 * Fusion de mail_api_simple.php existant + nouvelles routes classement
 */

// En-têtes pour l'API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Désactiver l'affichage des erreurs pour l'API
ini_set('display_errors', 0);
error_reporting(0);

// Fonction pour retourner une réponse JSON
function apiResponse($success, $data = null, $error = null) {
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($error !== null) $response['error'] = $error;
    echo json_encode($response);
    exit;
}

// Charger l'environnement Dolibarr
$res = 0;
$paths = [
    '../../../main.inc.php',
    '../../main.inc.php', 
    '../main.inc.php',
    '../../../../main.inc.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    apiResponse(false, null, 'Impossible de charger Dolibarr');
}

// Vérifier l'utilisateur connecté
if (empty($user->id)) {
    apiResponse(false, null, 'Utilisateur non connecté');
}

// Action de test
if (isset($_GET['test']) && $_GET['test'] == '1') {
    apiResponse(true, [
        'message' => 'API fonctionnelle',
        'user' => $user->login,
        'timestamp' => date('Y-m-d H:i:s'),
        'dolibarr_version' => DOL_VERSION,
        'rights' => [
            'webmail' => $user->hasRight('roundcubemodule', 'webmail', 'read'),
            'accounts' => $user->hasRight('roundcubemodule', 'accounts', 'write'),
            'admin' => $user->hasRight('roundcubemodule', 'admin', 'write'),
            'config' => $user->hasRight('roundcubemodule', 'config', 'write')
        ]
    ]);
}

// ============= ROUTES ORIGINALES (conservées) =============

// Action pour récupérer ou créer un mail
if (isset($_GET['action']) && $_GET['action'] == 'get_or_create_mail') {
    
    // Récupérer les paramètres
    $message_id = GETPOST('message_id', 'alpha');
    $subject = GETPOST('subject', 'alpha');
    $from_email = GETPOST('from_email', 'alpha');
    $to_email = GETPOST('to_email', 'alpha');
    $date_received = GETPOST('date_received', 'alpha');
    $imap_uid = GETPOST('imap_uid', 'int');
    $imap_mailbox = GETPOST('imap_mailbox', 'alpha');
    $direction = GETPOST('direction', 'alpha');
    $body_text = GETPOST('body_text', 'none');
    $body_html = GETPOST('body_html', 'none');
    $attachments = GETPOST('attachments', 'alpha');
    
    // Validation basique
    if (empty($message_id)) {
        apiResponse(false, null, 'message_id requis');
    }
    
    try {
        // Créer la table si elle n'existe pas
        $table_exists = false;
        $check_sql = "SHOW TABLES LIKE 'llx_mails'";
        $check_result = $db->query($check_sql);
        
        if ($check_result && $db->num_rows($check_result) > 0) {
            $table_exists = true;
        }
        
        if (!$table_exists) {
            $create_sql = "CREATE TABLE IF NOT EXISTS llx_mails (
                rowid int(11) NOT NULL AUTO_INCREMENT,
                message_id varchar(255) NOT NULL,
                subject text,
                from_email varchar(255),
                to_email text,
                date_received datetime,
                body_text longtext,
                body_html longtext,
                attachments text,
                imap_uid int(11),
                imap_mailbox varchar(255),
                direction enum('IN','OUT') DEFAULT 'IN',
                processed tinyint(1) DEFAULT 0,
                fk_soc int(11),
                fk_project int(11),
                fk_task int(11),
                fk_contact int(11),
                priority int DEFAULT 0,
                tags text,
                datec datetime NOT NULL,
                tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                fk_user_author int(11),
                fk_user_assigned int(11),
                entity int(11) DEFAULT 1,
                import_key varchar(14),
                status int DEFAULT 0,
                note_private text,
                note_public text,
                PRIMARY KEY (rowid),
                UNIQUE KEY uk_message_id (message_id),
                KEY idx_fk_soc (fk_soc),
                KEY idx_fk_project (fk_project),
                KEY idx_date_received (date_received),
                KEY idx_status (status),
                KEY idx_user_assigned (fk_user_assigned)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $db->query($create_sql);
        }
        
        // Vérifier si le mail existe déjà  
        $sql = "SELECT rowid, subject, datec, status FROM llx_mails WHERE message_id = '".$db->escape($message_id)."'";
        $result = $db->query($sql);
        
        if ($result && $db->num_rows($result) > 0) {
            // Mail existe déjà  
            $obj = $db->fetch_object($result);
            apiResponse(true, [
                'mail' => [
                    'rowid' => $obj->rowid,
                    'subject' => $obj->subject,
                    'datec' => $obj->datec,
                    'status' => $obj->status,
                    'action' => 'existing'
                ]
            ]);
        } else {
            // Créer un nouveau mail
            $now = dol_now();
            
            $sql = "INSERT INTO llx_mails (";
            $sql .= "message_id, subject, from_email, to_email, date_received, ";
            $sql .= "body_text, body_html, attachments, ";
            $sql .= "imap_uid, imap_mailbox, direction, ";
            $sql .= "datec, fk_user_author, entity";
            $sql .= ") VALUES (";
            $sql .= "'".$db->escape($message_id)."', ";
            $sql .= "'".$db->escape($subject)."', ";
            $sql .= "'".$db->escape($from_email)."', ";
            $sql .= "'".$db->escape($to_email)."', ";
            $sql .= "'".$db->escape($date_received)."', ";
            $sql .= "'".$db->escape($body_text)."', ";
            $sql .= "'".$db->escape($body_html)."', ";
            $sql .= "'".$db->escape($attachments)."', ";
            $sql .= "'".$db->escape($imap_uid)."', ";
            $sql .= "'".$db->escape($imap_mailbox)."', ";
            $sql .= "'".$db->escape($direction)."', ";
            $sql .= "'".$db->idate($now)."', ";
            $sql .= "'".$db->escape($user->id)."', ";
            $sql .= "'".$db->escape($conf->entity)."'";
            $sql .= ")";
            
            $result = $db->query($sql);
            
            if ($result) {
                $mail_id = $db->last_insert_id();
                apiResponse(true, [
                    'mail' => [
                        'rowid' => $mail_id,
                        'subject' => $subject,
                        'action' => 'created'
                    ]
                ]);
            } else {
                apiResponse(false, null, 'Erreur insertion: ' . $db->lasterror());
            }
        }
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Erreur base de données: ' . $e->getMessage());
    }
}

// ============= NOUVELLES ROUTES CLASSEMENT =============

// Route : Récupérer le classement d'un mail
if (isset($_GET['action']) && $_GET['action'] == 'get_mail_classification') {
    $message_id = GETPOST('message_id', 'alpha');
    
    if (empty($message_id)) {
        apiResponse(false, null, 'message_id requis');
    }
    
    try {
        // Vérifier si la table mailboxmodule_mail_audit existe
        $check_sql = "SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "mailboxmodule_mail_audit'";
        $check_result = $db->query($check_sql);
        
        if (!$check_result || $db->num_rows($check_result) == 0) {
            // Table n'existe pas - mail non classé
            apiResponse(true, [
                'classification' => null,
                'is_classified' => false,
                'info' => 'Table mailboxmodule_mail_audit non trouvée'
            ]);
        }
        
        // Rechercher dans la table d'audit avec le message_id complet
        $sql = "SELECT ma.*, s.nom as societe_nom, c.firstname, c.lastname, p.title as projet_title, p.ref as projet_ref";
        $sql .= " FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit ma";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON ma.fk_societe = s.rowid";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON ma.fk_contact = c.rowid";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet p ON ma.fk_projet = p.rowid";
        $sql .= " WHERE (ma.message_id_full = '" . $db->escape($message_id) . "'";
        $sql .= " OR ma.message_id_full LIKE '%" . $db->escape($message_id) . "%')";
        $sql .= " AND ma.entity = " . $conf->entity;
        $sql .= " ORDER BY ma.date_created DESC LIMIT 1";
        
        $result = $db->query($sql);
        
        if ($result && $db->num_rows($result) > 0) {
            $obj = $db->fetch_object($result);
            
            // Récupérer les modules liés
            $modules = [];
            $sql_links = "SELECT target_type, target_id, target_name FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_links";
            $sql_links .= " WHERE fk_mail = " . $obj->rowid;
            $result_links = $db->query($sql_links);
            
            if ($result_links) {
                while ($link = $db->fetch_object($result_links)) {
                    $modules[] = [
                        'type' => $link->target_type,
                        'id' => $link->target_id,
                        'name' => $link->target_name
                    ];
                }
            }
            
            $classification = [
                'id' => $obj->rowid,
                'is_classified' => true,
                'societe' => [
                    'id' => $obj->fk_societe,
                    'nom' => $obj->societe_nom
                ],
                'contact' => [
                    'id' => $obj->fk_contact,
                    'nom' => trim($obj->firstname . ' ' . $obj->lastname)
                ],
                'projet' => [
                    'id' => $obj->fk_projet,
                    'title' => $obj->projet_title,
                    'ref' => $obj->projet_ref
                ],
                'modules' => $modules,
                'date_creation' => $obj->date_created,
                'user_creation' => $obj->processed_by_user
            ];
            
            apiResponse(true, ['classification' => $classification]);
        } else {
            // Mail non classé
            apiResponse(true, [
                'classification' => null,
                'is_classified' => false
            ]);
        }
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Erreur récupération classement: ' . $e->getMessage());
    }
}

// Route : Recherche d'entités (reprise de la logique save2dolibarr)
if (isset($_GET['action']) && $_GET['action'] == 'search_entities') {
    $type = GETPOST('type', 'alpha');
    $query = GETPOST('query', 'alpha');
    $parent_id = GETPOST('parent_id', 'int');
    
    if (empty($type) || empty($query)) {
        apiResponse(false, null, 'Type et query requis');
    }
    
    if (strlen($query) < 2) {
        apiResponse(true, ['results' => []]);
    }
    
    try {
        $results = [];
        $query_escaped = $db->escape($query);
        
        switch ($type) {
            case 'thirdparty':
            case 'societe':
                if ($user->hasRight('societe', 'lire')) {
                    $sql = "SELECT rowid, nom, code_client, ville FROM " . MAIN_DB_PREFIX . "societe";
                    $sql .= " WHERE (nom LIKE '%" . $query_escaped . "%' OR code_client LIKE '%" . $query_escaped . "%')";
                    $sql .= " AND entity IN (" . getEntity('societe') . ")";
                    $sql .= " AND status = 1";
                    $sql .= " ORDER BY nom LIMIT 15";
                    
                    $result = $db->query($sql);
                    if ($result) {
                        while ($obj = $db->fetch_object($result)) {
                            $label = $obj->nom;
                            if ($obj->code_client) $label .= ' (' . $obj->code_client . ')';
                            if ($obj->ville) $label .= ' - ' . $obj->ville;
                            
                            $results[] = [
                                'id' => $obj->rowid,
                                'label' => $label,
                                'nom' => $obj->nom,
                                'code' => $obj->code_client
                            ];
                        }
                    }
                }
                break;
                
            case 'contact':
                if ($user->hasRight('societe', 'lire')) {
                    $sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, s.nom as societe_nom";
                    $sql .= " FROM " . MAIN_DB_PREFIX . "socpeople c";
                    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON c.fk_soc = s.rowid";
                    $sql .= " WHERE (c.firstname LIKE '%" . $query_escaped . "%' OR c.lastname LIKE '%" . $query_escaped . "%'";
                    $sql .= " OR c.email LIKE '%" . $query_escaped . "%')";
                    
                    if ($parent_id > 0) {
                        $sql .= " AND c.fk_soc = " . intval($parent_id);
                    }
                    
                    $sql .= " AND c.entity IN (" . getEntity('contact') . ")";
                    $sql .= " AND c.statut = 1";
                    $sql .= " ORDER BY c.lastname, c.firstname LIMIT 15";
                    
                    $result = $db->query($sql);
                    if ($result) {
                        while ($obj = $db->fetch_object($result)) {
                            $label = trim($obj->firstname . ' ' . $obj->lastname);
                            if ($obj->email) $label .= ' (' . $obj->email . ')';
                            if ($obj->societe_nom) $label .= ' - ' . $obj->societe_nom;
                            
                            $results[] = [
                                'id' => $obj->rowid,
                                'label' => $label,
                                'nom' => trim($obj->firstname . ' ' . $obj->lastname),
                                'email' => $obj->email,
                                'societe' => $obj->societe_nom
                            ];
                        }
                    }
                }
                break;
                
            case 'project':
            case 'projet':
                if ($user->hasRight('projet', 'lire')) {
                    $sql = "SELECT rowid, title, ref, description FROM " . MAIN_DB_PREFIX . "projet";
                    $sql .= " WHERE (title LIKE '%" . $query_escaped . "%' OR ref LIKE '%" . $query_escaped . "%')";
                    $sql .= " AND entity IN (" . getEntity('projet') . ")";
                    $sql .= " AND statut = 1";
                    $sql .= " ORDER BY title LIMIT 15";
                    
                    $result = $db->query($sql);
                    if ($result) {
                        while ($obj = $db->fetch_object($result)) {
                            $label = $obj->title;
                            if ($obj->ref) $label .= ' (' . $obj->ref . ')';
                            
                            $results[] = [
                                'id' => $obj->rowid,
                                'label' => $label,
                                'title' => $obj->title,
                                'ref' => $obj->ref
                            ];
                        }
                    }
                }
                break;
        }
        
        apiResponse(true, ['results' => $results]);
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Erreur recherche: ' . $e->getMessage());
    }
}

// Route : Modules disponibles selon les droits
if (isset($_GET['action']) && $_GET['action'] == 'get_available_modules') {
    try {
        $modules = [];
        
        // Configuration des modules avec vérification des droits
        $available_modules = [
            'propal' => [
                'icon' => '📄', 
                'label' => 'Proposition commerciale', 
                'enabled' => !empty($conf->propal->enabled) && !empty($user->rights->propal->lire)
            ],
            'commande' => [
                'icon' => '🛒', 
                'label' => 'Commande client', 
                'enabled' => !empty($conf->commande->enabled) && !empty($user->rights->commande->lire)
            ],
            'facture' => [
                'icon' => '💰', 
                'label' => 'Facture client', 
                'enabled' => !empty($conf->facture->enabled) && !empty($user->rights->facture->lire)
            ],
            'contrat' => [
                'icon' => '📋', 
                'label' => 'Contrat', 
                'enabled' => !empty($conf->contrat->enabled) && !empty($user->rights->contrat->lire)
            ],
            'ticket' => [
                'icon' => '🎫', 
                'label' => 'Ticket', 
                'enabled' => !empty($conf->ticket->enabled) && !empty($user->rights->ticket->read)
            ],
            'agenda' => [
                'icon' => '📅', 
                'label' => 'Agenda', 
                'enabled' => !empty($conf->agenda->enabled) && !empty($user->rights->agenda->myactions->read)
            ]
        ];
        
        foreach ($available_modules as $key => $config) {
            if ($config['enabled']) {
                $modules[] = [
                    'code' => $key,
                    'icon' => $config['icon'],
                    'label' => $config['label']
                ];
            }
        }
        
        apiResponse(true, ['modules' => $modules]);
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Erreur récupération modules: ' . $e->getMessage());
    }
}

// Action par défaut : informations sur l'API
apiResponse(true, [
    'message' => 'API Roundcube Mail Module avec classement - Version 2.0',
    'version' => '2.0',
    'user' => $user->login,
    'entity' => $conf->entity,
    'available_actions' => [
        'test' => 'Test de l\'API',
        'get_or_create_mail' => 'Récupérer ou créer un mail',
        'get_mail_classification' => 'Récupérer classement d\'un mail',
        'search_entities' => 'Rechercher entités pour classement',
        'get_available_modules' => 'Modules disponibles'
    ],
    'rights' => [
        'webmail' => $user->hasRight('roundcubemodule', 'webmail', 'read'),
        'accounts' => $user->hasRight('roundcubemodule', 'accounts', 'write'),
        'admin' => $user->hasRight('roundcubemodule', 'admin', 'write'),
        'config' => $user->hasRight('roundcubemodule', 'config', 'write')
    ]
]);
?>