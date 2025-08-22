<?php

class sentlogger extends rcube_plugin
{
    public $task = 'mail';
    private $dolibarr_config = [];

    public function init()
    {
        $this->add_hook('message_sent', [$this, 'log_sent_email']);
        $this->load_dolibarr_config();
    }

    private function load_dolibarr_config()
    {
        $rcmail = rcmail::get_instance();
        $current_dir = __DIR__;
        $max_depth = 10;

        $rcmail->write_log('sentlogger', "Recherche de conf.php depuis : " . $current_dir);

        for ($i = 0; $i < $max_depth; $i++) {
            $potential_path = $current_dir . '/conf/conf.php';
            $rcmail->write_log('sentlogger', "Test du chemin : " . $potential_path);

            if (file_exists($potential_path)) {
                // Charger la configuration en isolant le scope pour éviter les conflits de variables globales
                $config = (function () use ($potential_path) {
                    require $potential_path;
                    return get_defined_vars();
                })();

                $this->dolibarr_config = [
                    'dolibarr_main_db_host' => $config['dolibarr_main_db_host'] ?? '',
                    'dolibarr_main_db_port' => $config['dolibarr_main_db_port'] ?? '3306',
                    'dolibarr_main_db_name' => $config['dolibarr_main_db_name'] ?? '',
                    'dolibarr_main_db_user' => $config['dolibarr_main_db_user'] ?? '',
                    'dolibarr_main_db_pass' => $config['dolibarr_main_db_pass'] ?? '',
                    'table_prefix' => $config['dolibarr_main_db_prefix'] ?? (defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : 'llx_'),
                    'document_root' => $config['dolibarr_main_document_root'] ?? '',
                    'document_root_alt' => $config['dolibarr_main_document_root_alt'] ?? '',
                ];
                $rcmail->write_log('sentlogger', "Configuration chargée avec succès");
                return true;
            }

            $parent_dir = dirname($current_dir);
            if ($parent_dir === $current_dir) {
                break; // Stop if we can't go up further
            }
            $current_dir = $parent_dir;
        }

        $error_msg = "ERREUR: Impossible de trouver conf.php";
        $rcmail->write_log('sentlogger', $error_msg);
        error_log($error_msg); // Also log to PHP error log
        return false;
    }

    public function log_sent_email($args)
    {
        $rcmail = rcmail::get_instance();

        // 1. Charger la configuration Dolibarr
        if (!$this->load_dolibarr_config()) {
            error_log("sentlogger: Impossible de charger la configuration Dolibarr.");
            return $args; // Stop processing if config isn't loaded
        }

        $cfg = $this->dolibarr_config;

        // 2. Connexion à la base de données Dolibarr
        $mysqli = new mysqli(
            $cfg['dolibarr_main_db_host'],
            $cfg['dolibarr_main_db_user'],
            $cfg['dolibarr_main_db_pass'],
            $cfg['dolibarr_main_db_name'],
            (int)$cfg['dolibarr_main_db_port']
        );

        if ($mysqli->connect_error) {
            error_log("sentlogger: Erreur de connexion à la base de données Dolibarr: " . $mysqli->connect_error);
            return $args;
        }

        // 3. Récupération des informations du mail
        $to = is_array($args['headers']['To']) ? implode(',', $args['headers']['To']) : ($args['headers']['To'] ?? '');
        $from = $args['headers']['From'] ?? '';
        $subject = $args['headers']['Subject'] ?? '';
        $date = date('Y-m-d H:i:s');
        $message_id = $args['headers']['Message-ID'] ?? uniqid('', true); // Better unique ID
        $fk_soc = 0; // Default or determined by other logic

        // 4. Récupération du contenu brut de l'email
        $raw_email_content = '';
        if (isset($args['message']) && is_object($args['message'])) {
            if (method_exists($args['message'], 'getMessage')) {
                $raw_email_content = $args['message']->getMessage();
                $rcmail->write_log('sentlogger', "Contenu récupéré via getMessage() de l'objet message.");
            } elseif (property_exists($args['message'], 'output')) {
                // Fallback for objects that might store raw content in a public 'output' property
                $raw_email_content = $args['message']->output;
                $rcmail->write_log('sentlogger', "Contenu récupéré via output property (fallback).");
            }
        }
        $rcmail->write_log('sentlogger', "Taille du contenu brut de l'email: " . strlen($raw_email_content) . " octets");

        // 5. Sauvegarde du fichier .eml
        $data_dir = $cfg['document_root'] . '/custom/mailboxmodule/data/mails/';
        $rcmail->write_log('sentlogger', "Chemin de sauvegarde des emails .eml: " . $data_dir);

        if (!is_dir($data_dir)) {
            if (!mkdir($data_dir, 0775, true)) {
                $error = error_get_last();
                $rcmail->write_log('sentlogger', "ERREUR: Impossible de créer le dossier pour les emails .eml: " . ($error['message'] ?? 'Erreur inconnue'));
                // Continuer, car l'enregistrement du mail en DB est plus important que le .eml
            } else {
                 $rcmail->write_log('sentlogger', "Dossier .eml créé: " . $data_dir);
            }
        }

        $filename = 'email_' . date('Y-m-d_His') . '_' . substr(preg_replace('/[^\w\-]/', '_', $subject), 0, 20) . '.eml';
        $full_path = $data_dir . $filename;
        $file_path = ''; // Initialize relative path

        if (!empty($raw_email_content) && file_put_contents($full_path, $raw_email_content) !== false) {
            $file_path = 'custom/mailboxmodule/data/mails/' . $filename;
            $rcmail->write_log('sentlogger', "Fichier .eml enregistré avec succès: " . $full_path);
        } else {
            $error_info = error_get_last();
            $rcmail->write_log('sentlogger', "ERREUR: Impossible d'écrire le fichier .eml à: " . $full_path . " - " . ($error_info['message'] ?? 'Contenu vide ou erreur de permission'));
        }

        // 6. Insertion des informations du mail dans la base de données Dolibarr
        $sql = "INSERT INTO " . $cfg['table_prefix'] . "mailboxmodule_mail
                (message_id, subject, from_email, date_received, file_path, fk_soc, imap_mailbox, imap_uid, direction)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'sent')";

        $imap_mailbox = '[Gmail]/Messages envoyés'; // Ou autre dossier IMAP pertinent

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("sentlogger: Erreur de préparation de la requête SQL d'insertion du mail: " . $mysqli->error);
            $mysqli->close();
            return $args;
        }

        $stmt->bind_param(
            "sssssis",
            $message_id,
            $subject,
            $from,
            $date,
            $file_path, // Could be empty if .eml save failed, but still insert record
            $fk_soc,
            $imap_mailbox
        );

        if (!$stmt->execute()) {
            error_log("sentlogger: Erreur d'exécution de la requête SQL d'insertion du mail: " . $stmt->error);
            $stmt->close();
            $mysqli->close();
            return $args;
        }

        $new_mail_id = $mysqli->insert_id;
        $stmt->close();
        $rcmail->write_log('sentlogger', "Mail enregistré en base de données avec ID: " . $new_mail_id);

        // ---
        // 7. Gestion des pièces jointes
        // ---

        $attachments_dir = $cfg['document_root'] . '/custom/mailboxmodule/data/fichier_join/';
        $rcmail->write_log('sentlogger', "Chemin de sauvegarde des pièces jointes: " . $attachments_dir);

        if (!is_dir($attachments_dir)) {
            if (!mkdir($attachments_dir, 0775, true)) {
                $error = error_get_last();
                $rcmail->write_log('sentlogger', "ERREUR: Impossible de créer le dossier pour les pièces jointes: " . ($error['message'] ?? 'Erreur inconnue'));
                // Ne pas arrêter, juste logger l'erreur et essayer de continuer sans PJ
            } else {
                 $rcmail->write_log('sentlogger', "Dossier PJ créé: " . $attachments_dir);
            }
        }

        $nb_attachments = 0;

        // IMPORTANT: Dump the message object for debugging purposes
        $rcmail->write_log('sentlogger', "DUMP COMPLET DE ARGS['MESSAGE'] (pour débogage PJ):");
        $rcmail->write_log('sentlogger', print_r($args['message'], true));

        $message_parts = [];
        if (isset($args['message']) && is_object($args['message'])) {
            try {
                // Use Reflection to access the protected 'parts' property
                $reflection = new ReflectionClass($args['message']);
                $property = $reflection->getProperty('parts');
                $property->setAccessible(true); // Make the protected property accessible
                $message_parts = $property->getValue($args['message']); // Get its value

                if (is_array($message_parts)) {
                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Accès aux parts via Reflection réussi.");
                } else {
                    $message_parts = []; // Ensure it's an array even if Reflection returns non-array
                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Accès via Reflection réussi mais 'parts' n'est pas un tableau. (Possible si pas de PJ)");
                }

            } catch (ReflectionException $e) {
                $rcmail->write_log('sentlogger', "ERREUR DÉBOGAGE PJ: ReflectionException lors de l'accès à 'parts': " . $e->getMessage());
                // Fallback to empty array
                $message_parts = [];
            }
        } else {
            $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: L'objet message est absent ou invalide. Pas de traitement des pièces jointes.");
        }


        if (!empty($message_parts) && is_array($message_parts)) {
            $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Le bloc de traitement des pièces jointes est atteint. Nombre de parts trouvées: " . count($message_parts));

            // Use a stack to process all parts, including nested ones
            $stack = $message_parts;

            while (count($stack) > 0) {
                $part = array_pop($stack); // Get the last part from the stack

                $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Traitement d'une part. Keys disponibles: " . implode(', ', array_keys($part)));

                // If this part has sub-parts (e.g., multipart/alternative), add them to the stack for processing
                if (isset($part['parts']) && is_array($part['parts']) && count($part['parts']) > 0) {
                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Cette part contient " . count($part['parts']) . " sous-parts. Ajout à la pile.");
                    foreach ($part['parts'] as $subpart) {
                        $stack[] = $subpart;
                    }
                }

                // Determine content disposition (attachment, inline, etc.)
                $disposition = isset($part['disposition']) ? strtolower($part['disposition']) : '';
                $contentType = isset($part['c_type']) ? strtolower($part['c_type']) : '';
                $filename_att = $part['name'] ?? null; // Get filename directly from 'name' if available

                $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Part - Disposition: '" . $disposition . "', Content-Type: '" . $contentType . "', Nom (from 'name'): '" . ($filename_att ?? 'N/A') . "'");


                // Condition to identify attachments:
                // 1. Disposition is 'attachment'
                // 2. Disposition is 'inline' AND there's a filename (often for embedded images or files)
                // 3. Content-Type suggests a file (e.g., application/octet-stream) AND there's a filename
                if ($disposition === 'attachment' ||
                    ($disposition === 'inline' && !empty($filename_att)) ||
                    (!empty($filename_att) && strpos($contentType, 'application/') === 0 && !in_array($contentType, ['application/json', 'application/xml', 'application/x-www-form-urlencoded'])) // Exclude common non-file types if there's a name
                ) {
                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Part identifiée comme pièce jointe ou fichier embarqué avec nom.");

                    // Determine the filename for saving
                    if (empty($filename_att)) { // If 'name' was empty, try 'd_parameters'
                         if (!empty($part['d_parameters']['filename'])) {
                            $filename_att = $part['d_parameters']['filename'];
                        } else {
                            $filename_att = 'attachment_' . uniqid() . '.bin'; // Fallback generic name
                        }
                    }

                    $safe_name = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $filename_att);
                    $dest_filename = uniqid() . '_' . $safe_name;
                    $dest_path = $attachments_dir . $dest_filename;

                    // Get the content body
                    $content_body = $part['body'] ?? '';
                    $encoding = strtolower($part['encoding'] ?? '');

                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: '$filename_att' - Taille BODY avant décodage: " . strlen($content_body) . " octets. Encodage détecté: '" . $encoding . "'");

                    // Decode content based on encoding
                    $decoded_content = $content_body;
                    if ($encoding === 'base64') {
                        $decoded_content = base64_decode($content_body);
                        if ($decoded_content === false) {
                            $rcmail->write_log('sentlogger', "ERREUR CRITIQUE: Décodage base64 de la PJ '$filename_att' a échoué. Contenu peut être invalide.");
                            continue; // Skip this attachment
                        }
                    } elseif ($encoding === 'quoted-printable') {
                        $decoded_content = quoted_printable_decode($content_body);
                    }
                    // For '7bit', '8bit', 'binary', no decoding needed by PHP's built-in functions

                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: '$filename_att' - Taille CONTENT après décodage: " . strlen($decoded_content) . " octets.");

                    // Save the decoded content to a file
                    if (!empty($decoded_content) && file_put_contents($dest_path, $decoded_content) !== false) {
                        $relative_path = 'custom/mailboxmodule/data/fichier_join/' . $dest_filename;

                        //quand j Insert attachment info into DB
                        $sql_att = "INSERT INTO " . $cfg['table_prefix'] . "mailboxmodule_attachment
                                    (fk_mail, filename, filepath, entity, datec)
                                    VALUES (?, ?, ?, 1, NOW())";

                        $stmt_att = $mysqli->prepare($sql_att);
                        if ($stmt_att) {
                            $stmt_att->bind_param("iss", $new_mail_id, $safe_name, $relative_path);
                            if ($stmt_att->execute()) {
                                $rcmail->write_log('sentlogger', "PJ '$filename_att' enregistrée avec succès et DB MAJ: " . $relative_path);
                                $nb_attachments++;
                            } else {
                                $rcmail->write_log('sentlogger', "ERREUR: Échec d'exécution SQL pour la PJ '$filename_att': " . $stmt_att->error);
                            }
                            $stmt_att->close();
                        } else {
                            $rcmail->write_log('sentlogger', "ERREUR: Échec de préparation SQL pour la PJ '$filename_att': " . $mysqli->error);
                        }
                    } else {
                        $error_info = error_get_last();
                        $rcmail->write_log('sentlogger', "ERREUR: Impossible d'écrire la PJ '$filename_att' à $dest_path. Contenu vide après décodage ? " . (empty($decoded_content) ? 'Oui' : 'Non') . ". Erreur PHP: " . ($error_info['message'] ?? 'Aucune'));
                    }
                } else {
                    $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Part ignorée (pas une pièce jointe reconnue ou pas de nom de fichier).");
                }
            }
        } else {
            $rcmail->write_log('sentlogger', "DÉBOGAGE PJ: Aucune part de message trouvée ou structure inattendue.");
        }

        $rcmail->write_log('sentlogger', "$nb_attachments pièce(s) jointe(s) sauvegardée(s) au total.");
        $mysqli->close(); // Close DB connection
        return $args;
    }
}