<?php
/* Module RoundcubeModule - Configuration complète avec préférences, logos et droits
 * Copyright (C) 2025 - VERSION COMPLETE AVEC TOUTES FONCTIONNALITES
 */

// Démarrer l'output buffering pour éviter les problèmes de headers
ob_start();

// Load Dolibarr environment
$res = 0;
$paths = [
    dirname(dirname(dirname(__DIR__))).'/main.inc.php',
    '../../../main.inc.php',
    '../../main.inc.php',
    '../main.inc.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $res = @include $path;
        break;
    }
}

if (!$res) {
    die("Erreur : Impossible de charger main.inc.php.");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Load translation files
$langs->loadLangs(array("admin", "mails"));

// Vérification des droits - Seuls les utilisateurs avec le droit de configuration peuvent accéder
if (!$user->hasRight('roundcubemodule', 'config', 'write')) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'alpha');
if (empty($tab)) $tab = 'general';

// Chemins dynamiques
$module_path = dol_buildpath('/custom/roundcubemodule', 0);
$roundcube_path = $module_path . '/roundcube';
$config_file = $roundcube_path . '/config/config.inc.php';
$plugin_path = $roundcube_path . '/plugins/dolibarr_autologin';

// Récupération des paramètres DB depuis Dolibarr
$db_host = empty($conf->db->host) ? 'localhost' : $conf->db->host;
$db_name = $conf->db->name;
$db_user = $conf->db->user;
//$db_pass = $conf->db->pass;

// Fallback : essayer depuis le fichier conf.php si les paramètres Dolibarr sont vides
if (empty($db_name) || empty($db_user)) {
    $conf_file = DOL_DOCUMENT_ROOT.'/../conf/conf.php';
    if (file_exists($conf_file)) {
        include $conf_file;
        $db_host = isset($dolibarr_main_db_host) ? $dolibarr_main_db_host : $db_host;
        $db_name = isset($dolibarr_main_db_name) ? $dolibarr_main_db_name : $db_name;
        $db_user = isset($dolibarr_main_db_user) ? $dolibarr_main_db_user : $db_user;
        $db_pass = isset($dolibarr_main_db_pass) ? $dolibarr_main_db_pass : $db_pass;
    }
}

// Debug des paramètres de connexion (seulement si administrateur)
if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    error_log("ROUNDCUBE DEBUG DB: host=$db_host, name=$db_name, user=$db_user, pass_length=".strlen($db_pass));
}

// Langues et fuseaux horaires disponibles
$available_languages = [
    'fr_FR' => 'Français (France)',
    'en_US' => 'English (United States)',
    'en_GB' => 'English (United Kingdom)', 
    'es_ES' => 'Español (España)',
    'de_DE' => 'Deutsch (Deutschland)',
    'it_IT' => 'Italiano (Italia)',
    'pt_BR' => 'Português (Brasil)',
    'nl_NL' => 'Nederlands (Nederland)',
    'ru_RU' => 'Русский (Россия)',
    'zh_CN' => '中文 (简体)',
    'ja_JP' => '日本語 (日本)',
    'ar_SA' => 'العربية (السعودية)'
];

$available_timezones = [
    'Europe/Paris' => 'Europe/Paris (GMT+1)',
    'Europe/London' => 'Europe/London (GMT+0)',
    'Europe/Berlin' => 'Europe/Berlin (GMT+1)',
    'Europe/Madrid' => 'Europe/Madrid (GMT+1)',
    'Europe/Rome' => 'Europe/Rome (GMT+1)',
    'Europe/Brussels' => 'Europe/Brussels (GMT+1)',
    'Europe/Amsterdam' => 'Europe/Amsterdam (GMT+1)',
    'Europe/Zurich' => 'Europe/Zurich (GMT+1)',
    'America/New_York' => 'America/New_York (GMT-5)',
    'America/Los_Angeles' => 'America/Los_Angeles (GMT-8)',
    'America/Chicago' => 'America/Chicago (GMT-6)',
    'America/Toronto' => 'America/Toronto (GMT-5)',
    'Asia/Tokyo' => 'Asia/Tokyo (GMT+9)',
    'Asia/Shanghai' => 'Asia/Shanghai (GMT+8)',
    'Asia/Dubai' => 'Asia/Dubai (GMT+4)',
    'Australia/Sydney' => 'Australia/Sydney (GMT+10)',
    'Pacific/Auckland' => 'Pacific/Auckland (GMT+12)'
];

$available_themes = [
    'elastic' => 'Elastic (Moderne)',
    'larry' => 'Larry (Classique)',
    'classic' => 'Classic (Minimal)'
];

$date_formats = [
    'd/m/Y' => 'jj/mm/aaaa (français)',
    'Y-m-d' => 'aaaa-mm-jj (ISO)',
    'm/d/Y' => 'mm/jj/aaaa (américain)',
    'd.m.Y' => 'jj.mm.aaaa (allemand)',
    'j F Y' => 'j mois année (long)'
];

$time_formats = [
    'H:i' => '24h (15:30)',
    'g:i A' => '12h (3:30 PM)',
    'H:i:s' => '24h avec secondes'
];

/*
 * FONCTIONS D'INSTALLATION COMPLÈTE
 */

// Créer la table webmail_accounts avec TOUS les champs
function createWebmailAccountsTable($pdo, $table_name) {
    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `rowid` int NOT NULL AUTO_INCREMENT,
        `fk_user` int NOT NULL,
        `account_name` varchar(100) DEFAULT NULL,
        `email` varchar(255) NOT NULL,
        `password_encrypted` text,
        `imap_host` varchar(255) DEFAULT NULL,
        `imap_port` int DEFAULT 993,
        `imap_encryption` varchar(10) DEFAULT 'ssl',
        `smtp_host` varchar(255) DEFAULT NULL,
        `smtp_port` int DEFAULT 587,
        `smtp_encryption` varchar(10) DEFAULT 'tls',
        `smtp_auth` tinyint DEFAULT 1,
        `imap_folder_sent` varchar(100) DEFAULT 'Sent',
        `imap_folder_trash` varchar(100) DEFAULT 'Trash',
        `imap_folder_drafts` varchar(100) DEFAULT 'Drafts',
        `imap_folder_spam` varchar(100) DEFAULT 'Spam',
        `signature_text` text,
        `signature_html` text,
        `reply_to` varchar(255) DEFAULT NULL,
        `display_name` varchar(100) DEFAULT NULL,
        `organization` varchar(100) DEFAULT NULL,
        `user_language` varchar(10) DEFAULT 'fr_FR',
        `user_timezone` varchar(50) DEFAULT 'Europe/Paris',
        `user_theme` varchar(20) DEFAULT 'elastic',
        `date_format` varchar(20) DEFAULT 'd/m/Y',
        `time_format` varchar(20) DEFAULT 'H:i',
        `compose_mode` varchar(20) DEFAULT 'html',
        `reply_mode` varchar(20) DEFAULT 'quote',
        `draft_autosave_interval` int DEFAULT 300,
        `auto_mark_read` tinyint DEFAULT 1,
        `display_next_message` tinyint DEFAULT 0,
        `mail_refresh_interval` int DEFAULT 300,
        `logo_filename` varchar(255) DEFAULT NULL,
        `logo_type` varchar(20) DEFAULT 'global',
        `sync_enabled` tinyint DEFAULT 1,
        `sync_interval` int DEFAULT 300,
        `sync_folders` text,
        `last_sync` datetime DEFAULT NULL,
        `oauth_provider` varchar(50) DEFAULT NULL,
        `oauth_token` text,
        `oauth_refresh_token` text,
        `oauth_expires` datetime DEFAULT NULL,
        `is_default` tinyint DEFAULT 0,
        `is_active` tinyint DEFAULT 1,
        `last_connection` datetime DEFAULT NULL,
        `connection_count` int DEFAULT 0,
        `last_error` text,
        `error_count` int DEFAULT 0,
        `quota_used` bigint DEFAULT 0,
        `quota_total` bigint DEFAULT 0,
        `message_count` int DEFAULT 0,
        `date_creation` datetime NOT NULL,
        `date_modification` datetime DEFAULT NULL,
        `fk_user_creat` int DEFAULT NULL,
        `fk_user_modif` int DEFAULT NULL,
        `import_key` varchar(14) DEFAULT NULL,
        `status` int DEFAULT 1,
        `note_private` text,
        `note_public` text,
        PRIMARY KEY (`rowid`),
        KEY `fk_user` (`fk_user`),
        KEY `email` (`email`),
        KEY `is_default` (`is_default`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des comptes webmail avec préférences et logos'";
    
    return $pdo->exec($sql);
}

// Créer toutes les tables Roundcube
function createAllRoundcubeTables($pdo) {
    $tables_sql = [
        'session' => "CREATE TABLE IF NOT EXISTS `session` (
            `sess_id` varchar(128) NOT NULL PRIMARY KEY,
            `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `ip` varchar(40) NOT NULL,
            `vars` mediumtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'users' => "CREATE TABLE IF NOT EXISTS `users` (
            `user_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` varchar(128) BINARY NOT NULL,
            `mail_host` varchar(128) NOT NULL,
            `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `last_login` datetime DEFAULT NULL,
            `failed_login` datetime DEFAULT NULL,
            `failed_login_counter` int UNSIGNED DEFAULT NULL,
            `language` varchar(5),
            `preferences` longtext,
            PRIMARY KEY(`user_id`),
            UNIQUE(`username`, `mail_host`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'identities' => "CREATE TABLE IF NOT EXISTS `identities` (
            `identity_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` int UNSIGNED NOT NULL,
            `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `del` tinyint(1) NOT NULL DEFAULT 0,
            `standard` tinyint(1) NOT NULL DEFAULT 0,
            `name` varchar(128) NOT NULL,
            `organization` varchar(128) NOT NULL DEFAULT '',
            `email` varchar(128) NOT NULL,
            `reply_to` varchar(128) NOT NULL DEFAULT '',
            `bcc` varchar(128) NOT NULL DEFAULT '',
            `signature` longtext,
            `html_signature` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(`identity_id`),
            INDEX `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'contacts' => "CREATE TABLE IF NOT EXISTS `contacts` (
            `contact_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` int UNSIGNED NOT NULL,
            `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `del` tinyint(1) NOT NULL DEFAULT 0,
            `name` varchar(128) NOT NULL DEFAULT '',
            `email` text NOT NULL,
            `firstname` varchar(128) NOT NULL DEFAULT '',
            `surname` varchar(128) NOT NULL DEFAULT '',
            `vcard` longtext,
            `words` text,
            PRIMARY KEY(`contact_id`),
            INDEX `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'contactgroups' => "CREATE TABLE IF NOT EXISTS `contactgroups` (
            `contactgroup_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` int UNSIGNED NOT NULL,
            `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `del` tinyint(1) NOT NULL DEFAULT 0,
            `name` varchar(128) NOT NULL DEFAULT '',
            PRIMARY KEY(`contactgroup_id`),
            INDEX `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'contactgroupmembers' => "CREATE TABLE IF NOT EXISTS `contactgroupmembers` (
            `contactgroup_id` int UNSIGNED NOT NULL,
            `contact_id` int UNSIGNED NOT NULL,
            `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            PRIMARY KEY (`contactgroup_id`, `contact_id`),
            INDEX `contactgroupmembers_contact_index` (`contact_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'cache' => "CREATE TABLE IF NOT EXISTS `cache` (
            `user_id` int UNSIGNED NOT NULL,
            `cache_key` varchar(128) BINARY NOT NULL,
            `expires` datetime DEFAULT NULL,
            `data` longtext NOT NULL,
            PRIMARY KEY (`user_id`, `cache_key`),
            INDEX `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'cache_shared' => "CREATE TABLE IF NOT EXISTS `cache_shared` (
            `cache_key` varchar(255) BINARY NOT NULL,
            `expires` datetime DEFAULT NULL,
            `data` longtext NOT NULL,
            PRIMARY KEY (`cache_key`),
            INDEX `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'cache_messages' => "CREATE TABLE IF NOT EXISTS `cache_messages` (
            `user_id` int UNSIGNED NOT NULL,
            `mailbox` varchar(255) BINARY NOT NULL,
            `uid` int UNSIGNED NOT NULL,
            `expires` datetime DEFAULT NULL,
            `data` longtext NOT NULL,
            `flags` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`user_id`, `mailbox`, `uid`),
            INDEX `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'cache_thread' => "CREATE TABLE IF NOT EXISTS `cache_thread` (
            `user_id` int UNSIGNED NOT NULL,
            `mailbox` varchar(255) BINARY NOT NULL,
            `expires` datetime DEFAULT NULL,
            `data` longtext NOT NULL,
            PRIMARY KEY (`user_id`, `mailbox`),
            INDEX `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'cache_index' => "CREATE TABLE IF NOT EXISTS `cache_index` (
            `user_id` int UNSIGNED NOT NULL,
            `mailbox` varchar(255) BINARY NOT NULL,
            `expires` datetime DEFAULT NULL,
            `valid` tinyint(1) NOT NULL DEFAULT 0,
            `data` longtext NOT NULL,
            PRIMARY KEY (`user_id`, `mailbox`),
            INDEX `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
            `message_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` int UNSIGNED NOT NULL,
            `del` tinyint(1) NOT NULL DEFAULT 0,
            `cache_key` varchar(128) BINARY NOT NULL,
            `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
            `data` longtext NOT NULL,
            PRIMARY KEY(`message_id`),
            INDEX `user_id` (`user_id`),
            INDEX `cache_key` (`cache_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'headers' => "CREATE TABLE IF NOT EXISTS `headers` (
            `message_id` int UNSIGNED NOT NULL,
            `header` varchar(255) NOT NULL,
            `value` text NOT NULL,
            INDEX `message_id` (`message_id`),
            INDEX `header` (`header`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $created_tables = [];
    foreach ($tables_sql as $table => $sql) {
        try {
            $pdo->exec($sql);
            $created_tables[] = $table;
        } catch (Exception $e) {
            throw new Exception("Erreur création table $table : " . $e->getMessage());
        }
    }
    
    return $created_tables;
}

// Créer la structure de dossiers logos
function createLogoDirectories()
{
    global $conf;

    // Toujours privilégier la constante DOL_DATA_ROOT
    $base_dir = (defined('DOL_DATA_ROOT') ? DOL_DATA_ROOT : $conf->file->dol_data_root) . '/doctemplates';

    $directories = [
        $base_dir . '/mail',
        $base_dir . '/mail/logo'
    ];

    $created = [];
    $errors = [];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            $res = dol_mkdir($dir);
            if ($res >= 0) {
                $created[] = $dir;
                @chmod($dir, 0755);
            } else {
                $errors[] = "Impossible de créer le dossier " . $dir;
            }
        }
    }

    // Créer .htaccess de sécurité
    $htaccess_file = $base_dir . '/mail/logo/.htaccess';
    $htaccess_content = "# Sécurité dossier logos\n";
    $htaccess_content .= "Options -Indexes\n";
    $htaccess_content .= "<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
    $htaccess_content .= "    Order allow,deny\n";
    $htaccess_content .= "    Deny from all\n";
    $htaccess_content .= "</Files>\n";

    if (@file_put_contents($htaccess_file, $htaccess_content)) {
        $created[] = $htaccess_file;
    } else {
        $errors[] = "Impossible de créer " . $htaccess_file;
    }

    // Créer index.php de protection
    $index_file = $base_dir . '/mail/logo/index.php';
    $index_content = '<?php header("HTTP/1.1 403 Forbidden"); exit("Accès interdit"); ?>';

    if (@file_put_contents($index_file, $index_content)) {
        $created[] = $index_file;
    } else {
        $errors[] = "Impossible de créer " . $index_file;
    }

    return ['created' => $created, 'errors' => $errors];
}


// Fonction pour générer la configuration Roundcube
function generateRoundcubeConfig($db_user, $db_pass, $db_host, $db_name) {
    return '<?php

/* Local configuration for Roundcube Webmail - Configuration dynamique avec préférences utilisateur */

// Base de données
$config[\'db_dsnw\'] = \'mysql://'.$db_user.':'.$db_pass.'@'.$db_host.'/'.$db_name.'\';

// Configuration par défaut
$config[\'language\'] = \'fr_FR\';
$config[\'timezone\'] = \'Europe/Paris\';
$config[\'date_format\'] = \'d/m/Y\';
$config[\'time_format\'] = \'H:i\';

// Serveurs mail par défaut
$config[\'default_host\'] = \'localhost\';
$config[\'default_port\'] = 143;
$config[\'smtp_server\'] = \'localhost\';
$config[\'smtp_port\'] = 587;

// Plugins
$config[\'plugins\'] = [
    \'archive\', 
    \'password\', 
    \'userinfo\', 
    \'save2dolibarr\', 
    \'dolibarr_autologin\', 
    \'autologon\', 
    \'sentlogger\'
];

// Fonctionnalités
$config[\'auto_create_user\'] = true;
$config[\'login_autocomplete\'] = 2;
$config[\'session_lifetime\'] = 0;
$config[\'session_auth_name\'] = \'roundcube_dolibarr\';

// Interface
$config[\'skin\'] = \'elastic\';
$config[\'product_name\'] = \'Roundcube Webmail pour Dolibarr\';

// Sécurité
$config[\'des_key\'] = \''.generateRandomKey().'\';
$config[\'support_url\'] = \'\';
$config[\'enable_installer\'] = false;

// Options SSL/TLS
$config[\'imap_conn_options\'] = [
    \'ssl\' => [
        \'verify_peer\'       => false,
        \'verify_peer_name\'  => false,
        \'allow_self_signed\' => true,
        \'ciphers\'           => \'DEFAULT:!DH\',
    ],
];

$config[\'smtp_conn_options\'] = [
    \'ssl\' => [
        \'verify_peer\'       => false,
        \'verify_peer_name\'  => false,
        \'allow_self_signed\' => true,
        \'ciphers\'           => \'DEFAULT:!DH\',
    ],
];

// Tables Roundcube
$config[\'db_table_users\'] = \'users\';
$config[\'db_table_identities\'] = \'identities\';
$config[\'db_table_contacts\'] = \'contacts\';
$config[\'db_table_contactgroups\'] = \'contactgroups\';
$config[\'db_table_contactgroupmembers\'] = \'contactgroupmembers\';
$config[\'db_table_session\'] = \'session\';
$config[\'db_table_cache\'] = \'cache\';
$config[\'db_table_cache_shared\'] = \'cache_shared\';
$config[\'db_table_cache_messages\'] = \'cache_messages\';
$config[\'db_table_cache_thread\'] = \'cache_thread\';
$config[\'db_table_cache_index\'] = \'cache_index\';
$config[\'db_table_messages\'] = \'messages\';
$config[\'db_table_headers\'] = \'headers\';

// Dossiers IMAP par défaut
$config[\'sent_mbox\'] = \'Sent\';
$config[\'trash_mbox\'] = \'Trash\';
$config[\'drafts_mbox\'] = \'Drafts\';
$config[\'junk_mbox\'] = \'Spam\';

// Configuration autologin Dolibarr
$config[\'autologin_db_dsn\'] = \'mysql://'.$db_user.':'.$db_pass.'@'.$db_host.'/'.$db_name.'\';
$config[\'autologin_db_table\'] = \'llx_user\';
$config[\'autologin_db_userid_field\'] = \'login\';
$config[\'autologin_db_passwd_field\'] = \'pass_crypted\';
$config[\'autologin_db_email_field\'] = \'email\';

$config[\'webmail_accounts_table\'] = \'llx_mailboxmodule_webmail_accounts\';

$config[\'autologon_db_dsn\'] = \'mysql://'.$db_user.':'.$db_pass.'@'.$db_host.'/'.$db_name.'\';
$config[\'autologon_db_table\'] = \'llx_user\';
$config[\'autologon_db_userid_field\'] = \'login\';
$config[\'autologon_db_passwd_field\'] = \'pass_crypted\';
$config[\'autologon_db_email_field\'] = \'email\';

// Debug
$config[\'debug_level\'] = 1;
$config[\'log_driver\'] = \'file\';
$config[\'log_dir\'] = RCUBE_INSTALL_PATH . \'logs/\';
$config[\'per_user_logging\'] = true;
$config[\'imap_debug\'] = false;
$config[\'smtp_debug\'] = false;

// Fonctionnalités avancées
$config[\'compose_save_localstorage\'] = true;
$config[\'draft_autosave\'] = 300;
$config[\'message_show_email\'] = true;
$config[\'prefer_html\'] = true;
$config[\'htmleditor\'] = 1;
$config[\'db_max_length\'] = 512000;
$config[\'max_message_size\'] = \'25M\';
$config[\'temp_dir_ttl\'] = \'48h\';
$config[\'search_mods\'] = [\'mail\' => \'header\', \'addressbook\' => \'name\'];
$config[\'address_book_type\'] = \'sql\';
$config[\'autocomplete_addressbooks\'] = [\'sql\'];
';
}

function generateRandomKey() {
    return bin2hex(random_bytes(12));
}

/*
 * ACTIONS NOUVELLES - INSTALLATION COMPLÈTE
 */

// Installation complète en une seule action
if ($action == 'install_complete') {
    try {
        // Vérifier que nous avons les paramètres de connexion
        if (empty($db_name) || empty($db_user)) {
            throw new Exception("Paramètres de base de données manquants. Host: $db_host, DB: $db_name, User: $db_user");
        }
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $results = [];
        
        // 1. Créer la table webmail_accounts complète
        $table_name = MAIN_DB_PREFIX . "mailboxmodule_webmail_accounts";
        try {
            createWebmailAccountsTable($pdo, $table_name);
            $results[] = "✅ Table $table_name créée avec tous les champs";
        } catch (Exception $e) {
            $results[] = "⚠️ Table $table_name : " . $e->getMessage();
        }
        
        // 2. Créer toutes les tables Roundcube
        try {
            $roundcube_tables = createAllRoundcubeTables($pdo);
            $results[] = "✅ Tables Roundcube créées : " . implode(', ', $roundcube_tables);
        } catch (Exception $e) {
            $results[] = "⚠️ Tables Roundcube : " . $e->getMessage();
        }
        
        // 3. Créer la structure des dossiers logos
        try {
            $logo_result = createLogoDirectories();
            if (!empty($logo_result['created'])) {
                $results[] = "✅ Structure logos créée : " . count($logo_result['created']) . " éléments";
            }
            if (!empty($logo_result['errors'])) {
                $results[] = "⚠️ Erreurs logos : " . implode(', ', $logo_result['errors']);
            }
        } catch (Exception $e) {
            $results[] = "⚠️ Logos : " . $e->getMessage();
        }
        
        // 4. Créer le fichier de configuration Roundcube
        try {
            if (!file_exists($config_file)) {
                $config_content = generateRoundcubeConfig($db_user, $db_pass, $db_host, $db_name);
                $config_dir = dirname($config_file);
                if (!is_dir($config_dir)) {
                    dol_mkdir($config_dir);
                }
                
                if (file_put_contents($config_file, $config_content)) {
                    $results[] = "✅ Configuration Roundcube créée";
                } else {
                    $results[] = "❌ Erreur création configuration Roundcube";
                }
            } else {
                $results[] = "ℹ️ Configuration Roundcube existe déjà";
            }
        } catch (Exception $e) {
            $results[] = "⚠️ Configuration : " . $e->getMessage();
        }
        
        setEventMessages(implode('<br>', $results), null, 'mesgs');
        
    } catch (Exception $e) {
        $error_msg = "Erreur installation : " . $e->getMessage();
        
        // Debug supplémentaire en cas d'erreur de connexion
        if (strpos($e->getMessage(), 'Accès refusé') !== false) {
            $error_msg .= "<br><br><strong>🔍 Debug connexion DB :</strong><br>";
            $error_msg .= "• Host : " . htmlspecialchars($db_host) . "<br>";
            $error_msg .= "• Database : " . htmlspecialchars($db_name) . "<br>";
            $error_msg .= "• User : " . htmlspecialchars($db_user) . "<br>";
            $error_msg .= "• Password : " . (empty($db_pass) ? "❌ VIDE" : "✅ Défini (" . strlen($db_pass) . " caractères)") . "<br>";
            $error_msg .= "<br>💡 <strong>Solutions :</strong><br>";
            $error_msg .= "• Vérifiez les paramètres dans conf/conf.php<br>";
            $error_msg .= "• Testez la connexion avec phpMyAdmin<br>";
            $error_msg .= "• Vérifiez les droits MySQL de l'utilisateur";
        }
        
        setEventMessages($error_msg, null, 'errors');
    }
    
    header("Location: ".$_SERVER['PHP_SELF']."?tab=install");
    exit;
}

// Actions existantes (sauvegarder configuration générale, etc.)
if ($action == 'save_general') {
    $roundcube_url = GETPOST('roundcube_url', 'alpha');
    dolibarr_set_const($db, "ROUNDCUBE_URL", $roundcube_url, 'chaine', 0, '', $conf->entity);
    
    $use_autologin = GETPOST('use_autologin', 'int');
    dolibarr_set_const($db, "ROUNDCUBE_USE_AUTOLOGIN", $use_autologin, 'chaine', 0, '', $conf->entity);
    
    setEventMessages("Configuration sauvegardée", null, 'mesgs');
    header("Location: ".$_SERVER['PHP_SELF']."?tab=general");
    exit;
}

// NOUVELLE ACTION : Sauvegarder configuration autologin
if ($action == 'save_autologin') {
    $auto_redirect = GETPOST('auto_redirect', 'int');
    $redirect_delay = GETPOST('redirect_delay', 'int');
    $menu_autologin = GETPOST('menu_autologin', 'int');
    
    dolibarr_set_const($db, "ROUNDCUBE_AUTO_REDIRECT", $auto_redirect, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_REDIRECT_DELAY", $redirect_delay, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_MENU_AUTOLOGIN", $menu_autologin, 'chaine', 0, '', $conf->entity);
    
    setEventMessages("Configuration autologin sauvegardée", null, 'mesgs');
    header("Location: ".$_SERVER['PHP_SELF']."?tab=autologin");
    exit;
}

// Sauvegarder les préférences par défaut
if ($action == 'save_default_preferences') {
    $default_language = GETPOST('default_language', 'alpha');
    $default_timezone = GETPOST('default_timezone', 'alpha');
    $default_theme = GETPOST('default_theme', 'alpha');
    $default_date_format = GETPOST('default_date_format', 'alpha');
    $default_time_format = GETPOST('default_time_format', 'alpha');
    $default_compose_mode = GETPOST('default_compose_mode', 'alpha');
    $default_reply_mode = GETPOST('default_reply_mode', 'alpha');
    $draft_autosave = GETPOST('draft_autosave', 'int');
    $default_signature = GETPOST('default_signature', 'none');
    $auto_mark_read = GETPOST('auto_mark_read', 'int');
    $display_next = GETPOST('display_next', 'int');
    $mail_refresh = GETPOST('mail_refresh', 'int');
    $default_sent_folder = GETPOST('default_sent_folder', 'alpha');
    $default_trash_folder = GETPOST('default_trash_folder', 'alpha');
    $default_drafts_folder = GETPOST('default_drafts_folder', 'alpha');
    $default_spam_folder = GETPOST('default_spam_folder', 'alpha');
    
    // Sauvegarder dans la configuration Dolibarr
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_LANGUAGE", $default_language, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_TIMEZONE", $default_timezone, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_THEME", $default_theme, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_DATE_FORMAT", $default_date_format, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_TIME_FORMAT", $default_time_format, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_COMPOSE_MODE", $default_compose_mode, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_REPLY_MODE", $default_reply_mode, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DRAFT_AUTOSAVE", $draft_autosave, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_SIGNATURE", $default_signature, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_AUTO_MARK_READ", $auto_mark_read, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DISPLAY_NEXT", $display_next, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_MAIL_REFRESH", $mail_refresh, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_SENT_FOLDER", $default_sent_folder, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_TRASH_FOLDER", $default_trash_folder, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_DRAFTS_FOLDER", $default_drafts_folder, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "ROUNDCUBE_DEFAULT_SPAM_FOLDER", $default_spam_folder, 'chaine', 0, '', $conf->entity);
    
    setEventMessages("Préférences par défaut sauvegardées", null, 'mesgs');
    header("Location: ".$_SERVER['PHP_SELF']."?tab=preferences");
    exit;
}

/*
 * View
 */

llxHeader('', 'Configuration Roundcube', '');

print '<h1>Configuration Module Roundcube</h1>';

// Tabs améliorés avec liens dynamiques
print '<div class="tabs">';
print '<a class="tab'.($tab == 'general' ? ' active' : '').'" href="'.$_SERVER['PHP_SELF'].'?tab=general">Général</a>';
print '<a class="tab'.($tab == 'preferences' ? ' active' : '').'" href="'.$_SERVER['PHP_SELF'].'?tab=preferences">Préférences par défaut</a>';
print '<a class="tab'.($tab == 'install' ? ' active' : '').'" href="'.$_SERVER['PHP_SELF'].'?tab=install">Installation complète</a>';
print '<a class="tab'.($tab == 'autologin' ? ' active' : '').'" href="'.$_SERVER['PHP_SELF'].'?tab=autologin">Autologin</a>';
print '<a class="tab'.($tab == 'test' ? ' active' : '').'" href="'.$_SERVER['PHP_SELF'].'?tab=test">Test</a>';
print '</div>';

print '<div class="tabBar">';

// Tab Général
if ($tab == 'general') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save_general">';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">Paramètres généraux</td></tr>';
    
    print '<tr class="oddeven">';
    print '<td width="300">URL Roundcube</td>';
    print '<td>';
    
    // URL par défaut SANS lien en dur
    $default_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube/';
    $current_url = isset($conf->global->ROUNDCUBE_URL) ? $conf->global->ROUNDCUBE_URL : $default_url;
    
    print '<input type="text" name="roundcube_url" size="60" value="'.$default_url.'">';
    print '<br><small>Utilisez un chemin relatif (ex: /custom/roundcubemodule/roundcube/) ou une URL complète</small>';
    print '</td>';
    print '</tr>';
    
    print '<tr class="oddeven">';
    print '<td>Activer connexion automatique</td>';
    print '<td><input type="checkbox" name="use_autologin" value="1"'.(empty($conf->global->ROUNDCUBE_USE_AUTOLOGIN) ? '' : ' checked').'></td>';
    print '</tr>';
    
    print '</table>';
    
    print '<br><div class="center"><input type="submit" class="button" value="Enregistrer"></div>';
    print '</form>';
    
    // Informations sur la configuration
    print '<br><div class="info">';
    print '<strong>ℹ️ Configuration de l\'URL Roundcube :</strong><br>';
    print '• <strong>Chemin relatif :</strong> /custom/roundcubemodule/roundcube/ (recommandé)<br>';
    print '• <strong>URL complète :</strong> https://votre-domaine.com/roundcube/<br>';
    print '• <strong>Port différent :</strong> http://localhost:8080/roundcube/<br>';
    print '<br><strong>URL actuelle calculée :</strong> ';
    
    // Calculer l'URL finale comme dans roundcube_auto_login.php
    $final_url = '';
    if (!empty($conf->global->ROUNDCUBE_URL)) {
        $final_url = $conf->global->ROUNDCUBE_URL;
        if (strpos($final_url, 'http') !== 0) {
            if (strpos($final_url, '/') === 0) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $final_url = $protocol . $_SERVER['HTTP_HOST'] . $final_url;
            } else {
                $final_url = dol_buildpath($final_url, 1);
            }
        }
    } else {
        $final_url = dol_buildpath('/custom/roundcubemodule/roundcube/', 1);
    }
    
    print '<code>' . htmlspecialchars($final_url) . '</code>';
    print '</div>';
}

// TAB PRÉFÉRENCES PAR DÉFAUT
elseif ($tab == 'preferences') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save_default_preferences">';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">🌍 Localisation et affichage</td></tr>';
    
    // Langue par défaut
    print '<tr class="oddeven">';
    print '<td width="300">Langue par défaut</td>';
    print '<td>';
    print '<select name="default_language" class="flat">';
    $current_lang = isset($conf->global->ROUNDCUBE_DEFAULT_LANGUAGE) ? $conf->global->ROUNDCUBE_DEFAULT_LANGUAGE : 'fr_FR';
    foreach ($available_languages as $code => $name) {
        print '<option value="'.$code.'"'.($current_lang == $code ? ' selected' : '').'>'.$name.'</option>';
    }
    print '</select>';
    print '</td></tr>';
    
    // Fuseau horaire par défaut
    print '<tr class="oddeven">';
    print '<td>Fuseau horaire par défaut</td>';
    print '<td>';
    print '<select name="default_timezone" class="flat">';
    $current_tz = isset($conf->global->ROUNDCUBE_DEFAULT_TIMEZONE) ? $conf->global->ROUNDCUBE_DEFAULT_TIMEZONE : 'Europe/Paris';
    foreach ($available_timezones as $tz => $name) {
        print '<option value="'.$tz.'"'.($current_tz == $tz ? ' selected' : '').'>'.$name.'</option>';
    }
    print '</select>';
    print '</td></tr>';
    
    // Thème par défaut
    print '<tr class="oddeven">';
    print '<td>Thème par défaut</td>';
    print '<td>';
    print '<select name="default_theme" class="flat">';
    $current_theme = isset($conf->global->ROUNDCUBE_DEFAULT_THEME) ? $conf->global->ROUNDCUBE_DEFAULT_THEME : 'elastic';
    foreach ($available_themes as $theme => $name) {
        print '<option value="'.$theme.'"'.($current_theme == $theme ? ' selected' : '').'>'.$name.'</option>';
    }
    print '</select>';
    print '</td></tr>';
    
    // Format de date
    print '<tr class="oddeven">';
    print '<td>Format de date par défaut</td>';
    print '<td>';
    print '<select name="default_date_format" class="flat">';
    $current_date = isset($conf->global->ROUNDCUBE_DEFAULT_DATE_FORMAT) ? $conf->global->ROUNDCUBE_DEFAULT_DATE_FORMAT : 'd/m/Y';
    foreach ($date_formats as $format => $desc) {
        print '<option value="'.$format.'"'.($current_date == $format ? ' selected' : '').'>'.$desc.'</option>';
    }
    print '</select>';
    print '</td></tr>';
    
    // Format d'heure
    print '<tr class="oddeven">';
    print '<td>Format d\'heure par défaut</td>';
    print '<td>';
    print '<select name="default_time_format" class="flat">';
    $current_time = isset($conf->global->ROUNDCUBE_DEFAULT_TIME_FORMAT) ? $conf->global->ROUNDCUBE_DEFAULT_TIME_FORMAT : 'H:i';
    foreach ($time_formats as $format => $desc) {
        print '<option value="'.$format.'"'.($current_time == $format ? ' selected' : '').'>'.$desc.'</option>';
    }
    print '</select>';
    print '</td></tr>';
    
    print '<tr class="liste_titre"><td colspan="2">📝 Composition et réponses</td></tr>';
    
    // Mode de composition
    print '<tr class="oddeven">';
    print '<td>Mode de composition par défaut</td>';
    print '<td>';
    print '<select name="default_compose_mode" class="flat">';
    $current_compose = isset($conf->global->ROUNDCUBE_DEFAULT_COMPOSE_MODE) ? $conf->global->ROUNDCUBE_DEFAULT_COMPOSE_MODE : 'html';
    print '<option value="html"'.($current_compose == 'html' ? ' selected' : '').'>HTML (enrichi)</option>';
    print '<option value="text"'.($current_compose == 'text' ? ' selected' : '').'>Texte brut</option>';
    print '</select>';
    print '</td></tr>';
    
    // Mode de réponse
    print '<tr class="oddeven">';
    print '<td>Mode de réponse par défaut</td>';
    print '<td>';
    print '<select name="default_reply_mode" class="flat">';
    $current_reply = isset($conf->global->ROUNDCUBE_DEFAULT_REPLY_MODE) ? $conf->global->ROUNDCUBE_DEFAULT_REPLY_MODE : 'quote';
    print '<option value="quote"'.($current_reply == 'quote' ? ' selected' : '').'>Citer le message original</option>';
    print '<option value="top"'.($current_reply == 'top' ? ' selected' : '').'>Répondre en haut</option>';
    print '<option value="bottom"'.($current_reply == 'bottom' ? ' selected' : '').'>Répondre en bas</option>';
    print '</select>';
    print '</td></tr>';
    
    // Sauvegarde automatique brouillons
    print '<tr class="oddeven">';
    print '<td>Sauvegarde automatique (secondes)</td>';
    print '<td>';
    $current_autosave = isset($conf->global->ROUNDCUBE_DRAFT_AUTOSAVE) ? $conf->global->ROUNDCUBE_DRAFT_AUTOSAVE : 300;
    print '<input type="number" name="draft_autosave" value="'.$current_autosave.'" min="60" max="3600" class="flat"> secondes';
    print '</td></tr>';
    
    // Signature par défaut
    print '<tr class="oddeven">';
    print '<td>Signature par défaut</td>';
    print '<td>';
    $current_signature = isset($conf->global->ROUNDCUBE_DEFAULT_SIGNATURE) ? $conf->global->ROUNDCUBE_DEFAULT_SIGNATURE : '';
    print '<textarea name="default_signature" rows="4" cols="60" class="flat">'.$current_signature.'</textarea>';
    print '<br><small>Cette signature sera proposée par défaut pour tous les nouveaux comptes</small>';
    print '</td></tr>';
    
    print '<tr class="liste_titre"><td colspan="2">📁 Dossiers par défaut</td></tr>';
    
    // Dossiers IMAP
    $folders = [
        'default_sent_folder' => ['Envoyés', 'Sent'],
        'default_trash_folder' => ['Corbeille', 'Trash'], 
        'default_drafts_folder' => ['Brouillons', 'Drafts'],
        'default_spam_folder' => ['Spam', 'Spam']
    ];
    
    foreach ($folders as $field => $info) {
        print '<tr class="oddeven">';
        print '<td>Dossier '.$info[0].'</td>';
        print '<td>';
        $current_folder = isset($conf->global->{'ROUNDCUBE_'.strtoupper($field)}) ? $conf->global->{'ROUNDCUBE_'.strtoupper($field)} : $info[1];
        print '<input type="text" name="'.$field.'" value="'.$current_folder.'" class="flat">';
        print '</td></tr>';
    }
    
    print '<tr class="liste_titre"><td colspan="2">⚙️ Comportement</td></tr>';
    
    // Marquer comme lu automatiquement
    print '<tr class="oddeven">';
    print '<td>Marquer comme lu automatiquement</td>';
    print '<td>';
    $auto_read = isset($conf->global->ROUNDCUBE_AUTO_MARK_READ) ? $conf->global->ROUNDCUBE_AUTO_MARK_READ : 1;
    print '<input type="checkbox" name="auto_mark_read" value="1"'.($auto_read ? ' checked' : '').'>';
    print '</td></tr>';
    
    // Afficher le message suivant après suppression
    print '<tr class="oddeven">';
    print '<td>Afficher le message suivant après suppression</td>';
    print '<td>';
    $display_next = isset($conf->global->ROUNDCUBE_DISPLAY_NEXT) ? $conf->global->ROUNDCUBE_DISPLAY_NEXT : 0;
    print '<input type="checkbox" name="display_next" value="1"'.($display_next ? ' checked' : '').'>';
    print '</td></tr>';
    
    // Intervalle de rafraîchissement
    print '<tr class="oddeven">';
    print '<td>Rafraîchissement automatique (secondes)</td>';
    print '<td>';
    $refresh = isset($conf->global->ROUNDCUBE_MAIL_REFRESH) ? $conf->global->ROUNDCUBE_MAIL_REFRESH : 300;
    print '<input type="number" name="mail_refresh" value="'.$refresh.'" min="60" max="3600" class="flat"> secondes';
    print '</td></tr>';
    
    print '</table>';
    
    print '<br><div class="center"><input type="submit" class="button button-save" value="💾 Enregistrer les préférences par défaut"></div>';
    print '</form>';
    
    print '<br><div class="info">';
    print '<strong>ℹ️ Information :</strong> Ces paramètres seront utilisés comme valeurs par défaut pour tous les nouveaux comptes webmail créés. ';
    print 'Les utilisateurs pourront ensuite personnaliser individuellement leurs préférences dans la gestion de leurs comptes.';
    print '</div>';
}

// TAB INSTALLATION COMPLÈTE
elseif ($tab == 'install') {
    // Vérifications préalables
    $roundcube_ok = is_dir($roundcube_path) && file_exists($roundcube_path.'/index.php');
    $config_ok = file_exists($config_file);
    $db_ok = false;
    $tables_ok = false;
    $webmail_table_ok = false;
    $logo_dirs_ok = false;
    
    try {
        $pdo = new PDO("mysql:host=$db_host", $db_user);
        $result = $pdo->query("SHOW DATABASES LIKE '$db_name'");
        $db_ok = ($result && $result->rowCount() > 0);
        
        if ($db_ok) {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user);
            
            // Vérifier tables Roundcube
            $result = $pdo->query("SHOW TABLES LIKE 'session'");
            $tables_ok = ($result && $result->rowCount() > 0);
            
            // Vérifier table webmail_accounts
            $table_name = MAIN_DB_PREFIX . "mailboxmodule_webmail_accounts";
            $result = $pdo->query("SHOW TABLES LIKE '$table_name'");
            $webmail_table_ok = ($result && $result->rowCount() > 0);
        }
    } catch (Exception $e) {
        // Erreur silencieuse
    }
    
    // Vérifier structure logos
    
    //$logo_base = $dolibarr_main_data_root . '/doctemplates/mail/logo';

    $logo_base =  DOL_DATA_ROOT. '/doctemplates/mail/logo';
    $logo_dirs_ok = is_dir($logo_base) && is_writable($logo_base);
    
    print '<h2>🔧 Installation complète du module Roundcube</h2>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td>Composant</td><td>Statut</td><td>Description</td></tr>';
    
    // Vérification Roundcube
    print '<tr class="oddeven">';
    print '<td>Roundcube</td>';
    if ($roundcube_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Installation Roundcube détectée</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Manquant</span></td>';
        print '<td>Roundcube non installé dans '.$roundcube_path.'</td>';
    }
    print '</tr>';
    
    // Base de données
    print '<tr class="oddeven">';
    print '<td>Base de données</td>';
    if ($db_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Connexion DB réussie ('.$db_name.')</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Erreur</span></td>';
        print '<td>Impossible de se connecter à la base '.$db_name.'</td>';
    }
    print '</tr>';
    
    // Tables Roundcube
    print '<tr class="oddeven">';
    print '<td>Tables Roundcube</td>';
    if ($tables_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Tables Roundcube présentes</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Manquant</span></td>';
        print '<td>Tables Roundcube non créées</td>';
    }
    print '</tr>';
    
    // Table webmail
    print '<tr class="oddeven">';
    print '<td>Table comptes webmail</td>';
    if ($webmail_table_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Table des comptes webmail présente</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Manquant</span></td>';
        print '<td>Table des comptes webmail non créée</td>';
    }
    print '</tr>';
    
    // Configuration
    print '<tr class="oddeven">';
    print '<td>Configuration Roundcube</td>';
    if ($config_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Fichier de configuration présent</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Manquant</span></td>';
        print '<td>Configuration Roundcube non générée</td>';
    }
    print '</tr>';
    
    // Dossiers logos
    print '<tr class="oddeven">';
    print '<td>Structure logos</td>';
    if ($logo_dirs_ok) {
        print '<td><span class="badge badge-status4">✅ OK</span></td>';
        print '<td>Dossiers logos créés et accessibles</td>';
    } else {
        print '<td><span class="badge badge-status8">❌ Manquant</span></td>';
        print '<td>Structure des dossiers logos manquante</td>';
    }
    print '</tr>';
    
    print '</table>';
    
    print '<br>';
    
    // Bouton installation complète
    if (!$tables_ok || !$webmail_table_ok || !$config_ok || !$logo_dirs_ok) {
        print '<div class="center">';
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="install_complete">';
        print '<input type="hidden" name="tab" value="install">';
        print '<input type="submit" class="button button-save" value="🚀 Installation complète automatique" onclick="return confirm(\'Procéder à l\\\'installation complète ?\')">';
        print '</form>';
        print '</div>';
        
        print '<br><div class="info">';
        print '<strong>ℹ️ L\'installation complète va :</strong><br>';
        print '• Créer toutes les tables Roundcube nécessaires<br>';
        print '• Créer la table des comptes webmail avec tous les champs<br>';
        print '• Générer la configuration Roundcube optimisée<br>';
        print '• Créer la structure des dossiers pour les logos<br>';
        print '• Configurer les permissions de sécurité';
        print '</div>';
    } else {
        print '<div class="center">';
        print '<span class="badge badge-status4" style="font-size: 16px;">🎉 Installation complète réussie !</span>';
        print '</div>';
        
        print '<br><div class="center">';
  $accounts_list_url = dol_buildpath('/custom/roundcubemodule/admin/accounts_list.php', 1);
    print '<a href="'.$accounts_list_url.'" class="button">Gérer les boîtes mails</a> ';
        if ($roundcube_ok) {
            $roundcube_url = dol_buildpath('/custom/roundcubemodule/roundcube/', 1);
            print '<a href="'.$roundcube_url.'" class="button" target="_blank">Ouvrir Roundcube</a>';
        }
        print '</div>';
    }
}

// TAB AUTOLOGIN AMÉLIORÉ
elseif ($tab == 'autologin') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save_autologin">';
    
    print '<h2>🔐 Configuration de l\'autologin</h2>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">Options d\'autologin</td></tr>';
    
    // Redirection automatique
    print '<tr class="oddeven">';
    print '<td width="300">Redirection automatique activée</td>';
    print '<td>';
    $auto_redirect = isset($conf->global->ROUNDCUBE_AUTO_REDIRECT) ? $conf->global->ROUNDCUBE_AUTO_REDIRECT : 1;
    print '<input type="checkbox" name="auto_redirect" value="1"'.($auto_redirect ? ' checked' : '').'>';
    print '<br><small>Si activé, redirige automatiquement vers Roundcube après authentification</small>';
    print '</td></tr>';
    
    // Délai de redirection
    print '<tr class="oddeven">';
    print '<td>Délai de redirection (secondes)</td>';
    print '<td>';
    $redirect_delay = isset($conf->global->ROUNDCUBE_REDIRECT_DELAY) ? $conf->global->ROUNDCUBE_REDIRECT_DELAY : 0;
    print '<input type="number" name="redirect_delay" value="'.$redirect_delay.'" min="0" max="10" class="flat"> secondes';
    print '<br><small>0 = redirection immédiate, >0 = attendre X secondes avant redirection</small>';
    print '</td></tr>';
    
    // Autologin depuis le menu
    print '<tr class="oddeven">';
    print '<td>Connexion automatique depuis le menu Webmail</td>';
    print '<td>';
    $menu_autologin = isset($conf->global->ROUNDCUBE_MENU_AUTOLOGIN) ? $conf->global->ROUNDCUBE_MENU_AUTOLOGIN : 1;
    print '<input type="checkbox" name="menu_autologin" value="1"'.($menu_autologin ? ' checked' : '').'>';
    print '<br><small>Si activé, le clic sur "Webmail" dans le menu connecte automatiquement au compte par défaut</small>';
    print '</td></tr>';
    
    print '</table>';
    
    print '<br><div class="center"><input type="submit" class="button button-save" value="💾 Enregistrer la configuration autologin"></div>';
    print '</form>';
    
    print '<br><div class="info">';
    print '<strong>ℹ️ Comment ça fonctionne :</strong><br>';
    print '• <strong>Redirection automatique :</strong> Après connexion à Dolibarr, redirige vers Roundcube<br>';
    print '• <strong>Délai de redirection :</strong> Permet de personnaliser le délai avant redirection<br>';
    print '• <strong>Menu Webmail :</strong> Connexion directe au compte par défaut via le menu<br>';
    print '• <strong>Compte par défaut :</strong> Défini dans la gestion des comptes utilisateur';
    print '</div>';
}

// Tab Test
elseif ($tab == 'test') {
    print '<h2>🧪 Test de la configuration</h2>';
    
    print '<div class="center">';
    $autologin_url = dol_buildpath('/custom/roundcubemodule/roundcube_auto_login.php', 1);
    print '<a href="'.$autologin_url.'" class="button">Tester la connexion automatique</a>';
    print '</div>';
    
    print '<br><div class="info">';
    print '<strong>ℹ️ Ce test va :</strong><br>';
    print '• Vérifier la connexion à Roundcube<br>';
    print '• Tester l\'autologin avec votre session Dolibarr<br>';
    print '• Valider la configuration des comptes email<br>';
    print '<br><strong>URL testée :</strong> <code>' . htmlspecialchars($autologin_url) . '</code>';
    print '</div>';
}

print '</div>'; // tabBar

llxFooter();
$db->close();
?>