<?php

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modRoundcubeModule extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 104010;
        $this->rights_class = 'roundcubemodule';
        $this->family = "crm";
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Accès au webmail Roundcube depuis Dolibarr avec gestion des droits";
        $this->version = '2.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-envelope';
        $this->config_page_url = array('roundcube_config.php@roundcubemodule');
        $this->langfiles = array('roundcubemodule@roundcubemodule');
        
        // Définition des droits
        $this->rights = array();
        $r = 0;
        
        // Droit de base : utiliser le webmail
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Utiliser le webmail Roundcube';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'webmail';
        $this->rights[$r][5] = 'read';
        $r++;
        
        // Droit : gérer ses propres comptes
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Gérer ses comptes webmail';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'accounts';
        $this->rights[$r][5] = 'write';
        $r++;
        
        // Droit : administrer les comptes de tous les utilisateurs
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Administrer tous les comptes webmail';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = 'write';
        $r++;
        
        // Droit : configurer le module
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Configurer le module Roundcube';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 1; // Droit admin seulement
        $this->rights[$r][4] = 'config';
        $this->rights[$r][5] = 'write';
        $r++;
        
        // ONGLETS - Méthode native Dolibarr
        $this->tabs = array(
            'user:+roundcube:Roundcube:roundcubemodule@roundcubemodule:/custom/roundcubemodule/user_webmail_tab.php?id=__ID__'
        );
        
        $this->dirs = array("/roundcubemodule/temp");

        // Menu principal - avec droits
        $this->menu = array();
        $r = 0;
        
        // Menu principal Webmail
        $this->menu[$r] = array(
            'fk_menu' => 0,
            'type' => 'top',
            'titre' => 'Webmail',
            'mainmenu' => 'roundcube',
            'leftmenu' => '',
            'url' => '/custom/roundcubemodule/roundcube.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 100,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "webmail", "read")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu : Mes comptes
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Mes comptes',
            'leftmenu' => 'roundcube_accounts',
            'url' => '/user/card.php?id=__USER_ID__&tab=roundcube',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 110,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "accounts", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu admin : Configuration
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Configuration',
            'leftmenu' => 'roundcube_config',
            'url' => '/custom/roundcubemodule/admin/roundcube_config.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 120,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "config", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu admin : Gestion des comptes
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Gestion des comptes',
            'leftmenu' => 'roundcube_admin',
            'url' => '/custom/roundcubemodule/admin/accounts_list.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 130,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "admin", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Pas de hooks nécessaires avec la méthode tabs
        $this->module_parts = array();
    }

    public function init($options = '')
    {
        global $conf, $db;

        // Créer les constantes par défaut
        $sql = array();
        
        // URL Roundcube par défaut (chemin relatif)
        if (!isset($conf->global->ROUNDCUBE_URL)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_URL', '/custom/roundcubemodule/roundcube/', 'chaine', 0, ".$conf->entity.")";
        }
        
        // Activer autologin par défaut
        if (!isset($conf->global->ROUNDCUBE_USE_AUTOLOGIN)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_USE_AUTOLOGIN', '1', 'chaine', 0, ".$conf->entity.")";
        }
        
        // Configuration autologin
        if (!isset($conf->global->ROUNDCUBE_AUTO_REDIRECT)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_AUTO_REDIRECT', '0', 'chaine', 0, ".$conf->entity.")";
        }
        
        if (!isset($conf->global->ROUNDCUBE_MENU_AUTOLOGIN)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_MENU_AUTOLOGIN', '1', 'chaine', 0, ".$conf->entity.")";
        }
        
        // Exécuter les requêtes SQL
        foreach ($sql as $query) {
            $db->query($query);
        }

        // Créer la structure de base de données si nécessaire
        $this->createDatabaseStructure();

        return parent::init($options);
    }
    
    /**
     * Créer la structure de base de données
     */
    private function createDatabaseStructure()
    {
        global $db, $conf;
        
        try {
            // Table des comptes webmail avec tous les champs
            $table_name = MAIN_DB_PREFIX."mailboxmodule_webmail_accounts";
            
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->query($sql);
            
            // Créer la structure des dossiers logos
            $logo_base = $conf->file->dol_data_root . '/doctemplates/mail/logo';
            if (!is_dir($logo_base)) {
                dol_mkdir($logo_base);
            }
            
        } catch (Exception $e) {
            dol_syslog("Erreur création structure Roundcube: " . $e->getMessage(), LOG_ERR);
        }
    }
    
    public function remove($options = '')
    {
        global $conf, $db;
        
        // Supprimer les constantes du module
        $sql = array();
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'ROUNDCUBE_%'";
        
        foreach ($sql as $query) {
            $db->query($query);
        }
        
        return parent::remove($options);
    }
}