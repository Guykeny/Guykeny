<?php
/**
 * Extension API pour le classement des mails dans le bandeau
 * À intégrer dans mail_api_simple.php existant
 */

// ============= NOUVELLES ROUTES POUR LE CLASSEMENT =============

// Route : Récupérer le classement d'un mail
if (isset($_GET['action']) && $_GET['action'] == 'get_mail_classification') {
    $message_id = GETPOST('message_id', 'alpha');
    
    if (empty($message_id)) {
        apiResponse(false, null, 'message_id requis');
    }
    
    try {
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

// Route : Classer un mail
if (isset($_GET['action']) && $_GET['action'] == 'classify_mail') {
    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        apiResponse(false, null, 'Données JSON requises');
    }
    
    $message_id = $input['message_id'] ?? '';
    $societe_id = $input['societe_id'] ?? 0;
    $contact_id = $input['contact_id'] ?? 0;  
    $projet_id = $input['projet_id'] ?? 0;
    $modules = $input['modules'] ?? [];
    $mail_data = $input['mail_data'] ?? [];
    
    if (empty($message_id)) {
        apiResponse(false, null, 'message_id requis');
    }
    
    try {
        $db->begin();
        
        // Vérifier si déjà classé
        $sql_check = "SELECT rowid FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit";
        $sql_check .= " WHERE message_id_full = '" . $db->escape($message_id) . "'";
        $result_check = $db->query($sql_check);
        
        if ($result_check && $db->num_rows($result_check) > 0) {
            apiResponse(false, null, 'Ce mail est déjà classé');
        }
        
        // Insérer le classement principal
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit (";
        $sql .= "message_id_full, fk_societe, fk_contact, fk_projet, ";
        $sql .= "subject_full, from_header_full, date_header, ";
        $sql .= "processed_by_user, date_created, processing_date, ";
        $sql .= "classification_status, entity";
        $sql .= ") VALUES (";
        $sql .= "'" . $db->escape($message_id) . "', ";
        $sql .= intval($societe_id) . ", ";
        $sql .= intval($contact_id) . ", ";
        $sql .= intval($projet_id) . ", ";
        $sql .= "'" . $db->escape($mail_data['subject'] ?? '') . "', ";
        $sql .= "'" . $db->escape($mail_data['from'] ?? '') . "', ";
        $sql .= "'" . $db->escape($mail_data['date'] ?? date('Y-m-d H:i:s')) . "', ";
        $sql .= $user->id . ", ";
        $sql .= "'" . $db->idate(dol_now()) . "', ";
        $sql .= "'" . $db->idate(dol_now()) . "', ";
        $sql .= "'manual', ";
        $sql .= $conf->entity;
        $sql .= ")";
        
        if (!$db->query($sql)) {
            throw new Exception('Erreur insertion classement: ' . $db->lasterror());
        }
        
        $mail_audit_id = $db->last_insert_id(MAIN_DB_PREFIX . "mailboxmodule_mail_audit");
        
        // Insérer les liens vers les modules
        foreach ($modules as $module) {
            $sql_link = "INSERT INTO " . MAIN_DB_PREFIX . "mailboxmodule_mail_links (";
            $sql_link .= "fk_mail, target_type, target_id, target_name, date_created";
            $sql_link .= ") VALUES (";
            $sql_link .= $mail_audit_id . ", ";
            $sql_link .= "'" . $db->escape($module['type']) . "', ";
            $sql_link .= intval($module['id']) . ", ";
            $sql_link .= "'" . $db->escape($module['name']) . "', ";
            $sql_link .= "'" . $db->idate(dol_now()) . "'";
            $sql_link .= ")";
            
            if (!$db->query($sql_link)) {
                throw new Exception('Erreur insertion lien module: ' . $db->lasterror());
            }
        }
        
        $db->commit();
        
        apiResponse(true, [
            'message' => 'Mail classé avec succès',
            'classification_id' => $mail_audit_id
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        apiResponse(false, null, $e->getMessage());
    }
}

// Route : Mettre à jour un classement
if (isset($_GET['action']) && $_GET['action'] == 'update_classification') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        apiResponse(false, null, 'Données JSON requises');
    }
    
    $classification_id = $input['classification_id'] ?? 0;
    $societe_id = $input['societe_id'] ?? 0;
    $contact_id = $input['contact_id'] ?? 0;
    $projet_id = $input['projet_id'] ?? 0;
    $modules = $input['modules'] ?? [];
    
    if (empty($classification_id)) {
        apiResponse(false, null, 'classification_id requis');
    }
    
    try {
        $db->begin();
        
        // Mettre à jour le classement principal
        $sql = "UPDATE " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit SET ";
        $sql .= "fk_societe = " . intval($societe_id) . ", ";
        $sql .= "fk_contact = " . intval($contact_id) . ", ";
        $sql .= "fk_projet = " . intval($projet_id) . ", ";
        $sql .= "date_modified = '" . $db->idate(dol_now()) . "' ";
        $sql .= "WHERE rowid = " . intval($classification_id);
        $sql .= " AND entity = " . $conf->entity;
        
        if (!$db->query($sql)) {
            throw new Exception('Erreur mise à jour classement: ' . $db->lasterror());
        }
        
        // Supprimer les anciens liens modules
        $sql_del = "DELETE FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_links";
        $sql_del .= " WHERE fk_mail = " . intval($classification_id);
        $db->query($sql_del);
        
        // Insérer les nouveaux liens modules
        foreach ($modules as $module) {
            $sql_link = "INSERT INTO " . MAIN_DB_PREFIX . "mailboxmodule_mail_links (";
            $sql_link .= "fk_mail, target_type, target_id, target_name, date_created";
            $sql_link .= ") VALUES (";
            $sql_link .= intval($classification_id) . ", ";
            $sql_link .= "'" . $db->escape($module['type']) . "', ";
            $sql_link .= intval($module['id']) . ", ";
            $sql_link .= "'" . $db->escape($module['name']) . "', ";
            $sql_link .= "'" . $db->idate(dol_now()) . "'";
            $sql_link .= ")";
            
            if (!$db->query($sql_link)) {
                throw new Exception('Erreur insertion lien module: ' . $db->lasterror());
            }
        }
        
        $db->commit();
        
        apiResponse(true, ['message' => 'Classement mis à jour avec succès']);
        
    } catch (Exception $e) {
        $db->rollback();
        apiResponse(false, null, $e->getMessage());
    }
}

// Route : Déclasser un mail
if (isset($_GET['action']) && $_GET['action'] == 'unclassify_mail') {
    $classification_id = GETPOST('classification_id', 'int');
    
    if (empty($classification_id)) {
        apiResponse(false, null, 'classification_id requis');
    }
    
    try {
        $db->begin();
        
        // Supprimer les liens modules
        $sql_del_links = "DELETE FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_links";
        $sql_del_links .= " WHERE fk_mail = " . intval($classification_id);
        $db->query($sql_del_links);
        
        // Supprimer le classement principal
        $sql_del = "DELETE FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit";
        $sql_del .= " WHERE rowid = " . intval($classification_id);
        $sql_del .= " AND entity = " . $conf->entity;
        
        if (!$db->query($sql_del)) {
            throw new Exception('Erreur suppression classement: ' . $db->lasterror());
        }
        
        $db->commit();
        
        apiResponse(true, ['message' => 'Mail déclassé avec succès']);
        
    } catch (Exception $e) {
        $db->rollback();
        apiResponse(false, null, $e->getMessage());
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

// Route : Détecter les réponses et proposer un classement
if (isset($_GET['action']) && $_GET['action'] == 'detect_reply_classification') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        apiResponse(false, null, 'Données JSON requises');
    }
    
    $message_id = $input['message_id'] ?? '';
    $mail_data = $input['mail_data'] ?? [];
    
    try {
        $suggestion = [
            'is_reply' => false,
            'classification' => null,
            'original_date' => null
        ];
        
        // Recherche par In-Reply-To
        if (!empty($mail_data['in_reply_to'])) {
            $original_id = trim($mail_data['in_reply_to'], '<>');
            $classification = getMailClassificationByMessageId($original_id);
            if ($classification) {
                $suggestion['is_reply'] = true;
                $suggestion['classification'] = $classification;
                apiResponse(true, $suggestion);
            }
        }
        
        // Recherche par References
        if (!empty($mail_data['references'])) {
            $references = explode(' ', $mail_data['references']);
            foreach ($references as $ref) {
                $ref = trim($ref, '<>');
                if (!empty($ref)) {
                    $classification = getMailClassificationByMessageId($ref);
                    if ($classification) {
                        $suggestion['is_reply'] = true;
                        $suggestion['classification'] = $classification;
                        apiResponse(true, $suggestion);
                    }
                }
            }
        }
        
        // Recherche par sujet (Re: Subject)
        $subject = $mail_data['subject'] ?? '';
        if (preg_match('/^Re:\s*(.+)$/i', $subject, $matches)) {
            $original_subject = trim($matches[1]);
            
            $sql = "SELECT ma.*, s.nom as societe_nom, c.firstname, c.lastname, p.title as projet_title";
            $sql .= " FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit ma";
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON ma.fk_societe = s.rowid";
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON ma.fk_contact = c.rowid";
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet p ON ma.fk_projet = p.rowid";
            $sql .= " WHERE ma.subject_full LIKE '%" . $db->escape($original_subject) . "%'";
            $sql .= " AND ma.entity = " . $conf->entity;
            $sql .= " ORDER BY ma.date_created DESC LIMIT 1";
            
            $result = $db->query($sql);
            if ($result && $db->num_rows($result) > 0) {
                $obj = $db->fetch_object($result);
                
                $classification = [
                    'id' => $obj->rowid,
                    'societe' => ['id' => $obj->fk_societe, 'nom' => $obj->societe_nom],
                    'contact' => ['id' => $obj->fk_contact, 'nom' => trim($obj->firstname . ' ' . $obj->lastname)],
                    'projet' => ['id' => $obj->fk_projet, 'title' => $obj->projet_title],
                    'date_creation' => $obj->date_created
                ];
                
                $suggestion['is_reply'] = true;
                $suggestion['classification'] = $classification;
                $suggestion['original_date'] = $obj->date_created;
            }
        }
        
        apiResponse(true, $suggestion);
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Erreur détection réponse: ' . $e->getMessage());
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

/**
 * Fonction utilitaire pour récupérer un classement par message_id
 */
function getMailClassificationByMessageId($message_id) {
    global $db, $conf;
    
    $sql = "SELECT ma.*, s.nom as societe_nom, c.firstname, c.lastname, p.title as projet_title";
    $sql .= " FROM " . MAIN_DB_PREFIX . "mailboxmodule_mail_audit ma";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON ma.fk_societe = s.rowid";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON ma.fk_contact = c.rowid";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet p ON ma.fk_projet = p.rowid";
    $sql .= " WHERE ma.message_id_full LIKE '%" . $db->escape($message_id) . "%'";
    $sql .= " AND ma.entity = " . $conf->entity;
    $sql .= " ORDER BY ma.date_created DESC LIMIT 1";
    
    $result = $db->query($sql);
    
    if ($result && $db->num_rows($result) > 0) {
        $obj = $db->fetch_object($result);
        
        return [
            'id' => $obj->rowid,
            'societe' => ['id' => $obj->fk_societe, 'nom' => $obj->societe_nom],
            'contact' => ['id' => $obj->fk_contact, 'nom' => trim($obj->firstname . ' ' . $obj->lastname)],
            'projet' => ['id' => $obj->fk_projet, 'title' => $obj->projet_title],
            'date_creation' => $obj->date_created
        ];
    }
    
    return null;
}
?>