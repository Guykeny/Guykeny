<?php

class autologon extends rcube_plugin
{
    public $task = 'login|mail';
    private $db;
    private $config;
    private $dolibarr_config = []; 

    function init()
    {
        $this->add_hook('startup', [$this, 'startup']);
        $this->add_hook('authenticate', [$this, 'authenticate']);
        rcmail::get_instance()->write_log('autologon', "Plugin autologon: Initialisation...");
        $this->load_dolibarr_config();
        $this->init_config();
    }

    private function load_dolibarr_config()
    {
        $rcmail = rcmail::get_instance(); 

        $current_dir = __DIR__;
        $dolibarr_htdocs_path = null;
        $found_conf_path = null;

        $rcmail->write_log('autologon', "load_dolibarr_config: Recherche de conf.php. Répertoire actuel: " . $current_dir);

        
        $temp_dir = $current_dir;
        for ($i = 0; $i < 10; $i++) {
            $potential_conf_path = $temp_dir . '/conf/conf.php';
            $rcmail->write_log('autologon', "load_dolibarr_config: Tentative de chemin #" . ($i + 1) . ": " . $potential_conf_path);
            if (file_exists($potential_conf_path)) {
                $dolibarr_htdocs_path = $temp_dir;
                $found_conf_path = $potential_conf_path;
                $rcmail->write_log('autologon', "load_dolibarr_config: Dolibarr conf.php trouvé : " . $found_conf_path);
                break;
            }
            $temp_dir = dirname($temp_dir);
            if ($temp_dir === '/' || $temp_dir === $current_dir) {
                $rcmail->write_log('autologon', "load_dolibarr_config: Atteint la racine du système de fichiers ou chemin inchangé. Arrêt de la recherche.");
                break;
            }
            $current_dir = $temp_dir; // Mise à jour pour la prochaine itération
        }

        if ($found_conf_path) {
            require_once $found_conf_path;
            
            
            $this->dolibarr_config['dolibarr_main_db_host'] = isset($dolibarr_main_db_host) ? $dolibarr_main_db_host : '';
            $this->dolibarr_config['dolibarr_main_db_port'] = isset($dolibarr_main_db_port) ? $dolibarr_main_db_port : '3306';
            $this->dolibarr_config['dolibarr_main_db_name'] = isset($dolibarr_main_db_name) ? $dolibarr_main_db_name : '';
            $this->dolibarr_config['dolibarr_main_db_user'] = isset($dolibarr_main_db_user) ? $dolibarr_main_db_user : '';
            $this->dolibarr_config['dolibarr_main_db_pass'] = isset($dolibarr_main_db_pass) ? $dolibarr_main_db_pass : '';
            // Pour le préfixe de table, vérifier la variable $dolibarr_main_db_prefix d'abord, puis la constante MAIN_DB_PREFIX en dernier recours
            $this->dolibarr_config['table_prefix'] = isset($dolibarr_main_db_prefix) ? $dolibarr_main_db_prefix : (defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : '');
            
            $rcmail->write_log('autologon', "load_dolibarr_config: Configuration Dolibarr chargée. Hôte: " . $this->dolibarr_config['dolibarr_main_db_host'] . 
                                           ", Base: " . $this->dolibarr_config['dolibarr_main_db_name'] . 
                                           ", Utilisateur: " . $this->dolibarr_config['dolibarr_main_db_user'] . 
                                           ", Préfixe: " . $this->dolibarr_config['table_prefix']);
            return;
        }
        
        error_log("Autologon: Fichier de configuration Dolibarr non trouvé dans aucun emplacement standard.");
        $rcmail->write_log('autologon', "Autologon: Fichier de configuration Dolibarr non trouvé. Utilisation des paramètres de base de données par défaut.");
    }

    private function init_config()
    {
        $rcmail = rcmail::get_instance();

        // Paramètres par défaut pour le plugin autologon
        $this->config = [
            'shared_secret' => 'MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdW',
            'allowed_ips' => ['127.0.0.1', '::1'] 
        ];

        // Remplacer par les valeurs de Dolibarr si détectées
        if (!empty($this->dolibarr_config['dolibarr_main_db_host'])) { // Vérifier si au moins l'hôte est défini
            $this->config['db_host'] = $this->dolibarr_config['dolibarr_main_db_host'];
            $this->config['db_port'] = $this->dolibarr_config['dolibarr_main_db_port'];
            $this->config['db_name'] = $this->dolibarr_config['dolibarr_main_db_name'];
            $this->config['db_user'] = $this->dolibarr_config['dolibarr_main_db_user'];
            $this->config['db_pass'] = $this->dolibarr_config['dolibarr_main_db_pass'];
            $this->config['table_prefix'] = $this->dolibarr_config['table_prefix'];
            $rcmail->write_log('autologon', "init_config: Paramètres DB mis à jour avec la configuration Dolibarr.");
        } else {
            // Valeurs par défaut si Dolibarr n'est pas détecté
            $this->config['db_host'] = 'localhost';
            $this->config['db_port'] = '3306';
            $this->config['db_name'] = 'doli';
            $this->config['db_user'] = 'root';
            $this->config['db_pass'] = '';
            $this->config['table_prefix'] = 'llx_';
            $rcmail->write_log('autologon', "init_config: Utilisation des paramètres DB par défaut.");
        }
        $rcmail->write_log('autologon', "init_config: Configuration DB finale - Hôte: " . $this->config['db_host'] . 
                                       ", Base: " . $this->config['db_name'] . 
                                       ", Utilisateur: " . $this->config['db_user'] . 
                                       ", Préfixe: " . $this->config['table_prefix']);
    }

    private function init_db()
    {
        $rcmail = rcmail::get_instance();
        $rcmail->write_log('autologon', "init_db: Tentative de connexion à la base de données Dolibarr...");
        try {
            $dsn = "mysql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_name']};charset=utf8";
            $this->db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $rcmail->write_log('autologon', "init_db: Connexion à la base de données réussie.");
            return true;
        } catch (PDOException $e) {
            error_log("Autologon DB Error: " . $e->getMessage());
            $rcmail->write_log('autologon', "init_db: Erreur de connexion à la base de données: " . $e->getMessage());
            return false;
        }
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();
        $rcmail->write_log('autologon', "startup: Début de la hook startup. Session utilisateur vide: " . (empty($_SESSION['user_id']) ? 'oui' : 'non'));
        if (empty($_SESSION['user_id']) && $this->is_autologin_request()) {
            $args['action'] = 'login';
            $rcmail->write_log('autologon', "startup: Requête d'autologin détectée et session vide. Action forcée sur 'login'.");
        }
        return $args;
    }

    function authenticate($args)
    {
        $rcmail = rcmail::get_instance();
        $rcmail->write_log('autologon', "authenticate: Début de la hook authenticate.");

        if ($this->is_autologin_request() && $this->init_db()) {
            $dolibarr_id = isset($_GET['dolibarr_id']) ? $_GET['dolibarr_id'] : 'NON_DEFINI';
            $rcmail->write_log('autologon', "authenticate: Requête d'autologin valide. dolibarr_id: " . $dolibarr_id);

            if ($dolibarr_id === 'NON_DEFINI' || !is_numeric($dolibarr_id)) {
                 $rcmail->write_log('autologon', "authenticate: dolibarr_id est manquant ou non numérique. Impossible de continuer l'authentification.");
                 return $args; // Retourner les arguments sans authentification
            }

            $stmt = $this->db->prepare(
                "SELECT mail_login, mail_password, mail_host 
                 FROM {$this->config['table_prefix']}user_roundcube 
                 WHERE fk_user = ?"
            );
            
            try {
                $stmt->execute([$dolibarr_id]);
                $rcmail->write_log('autologon', "authenticate: Exécution de la requête SQL réussie pour fk_user = " . $dolibarr_id);
            } catch (PDOException $e) {
                error_log("Autologon Authenticate DB Error: " . $e->getMessage());
                $rcmail->write_log('autologon', "authenticate: Erreur lors de l'exécution de la requête SQL: " . $e->getMessage());
                return $args;
            }

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $args['user'] = $row['mail_login'];
                $args['pass'] = $row['mail_password']; 
                $args['host'] = !empty($row['mail_host']) ? $row['mail_host'] : 'ssl://imap.gmail.com:993';
                $args['cookiecheck'] = false;
                $args['valid'] = true;
                $rcmail->write_log('autologon', "authenticate: Utilisateur trouvé dans la base. Login: " . $args['user'] . 
                                               ", Hôte IMAP: " . $args['host'] . 
                                               ", Authentification Roundcube valide: " . ($args['valid'] ? 'oui' : 'non'));
            } else {
                $rcmail->write_log('autologon', "authenticate: Aucun utilisateur trouvé dans la table {$this->config['table_prefix']}user_roundcube pour fk_user = " . $dolibarr_id);
            }
        } else {
            $rcmail->write_log('autologon', "authenticate: Requête d'autologin non valide ou connexion DB échouée.");
        }
        return $args;
    }

    private function is_autologin_request()
    {
        $rcmail = rcmail::get_instance();
        $ip_ok = in_array($_SERVER['REMOTE_ADDR'], $this->config['allowed_ips']);
        $secret_ok = !empty($_GET['secret']) && hash_equals($this->config['shared_secret'], $_GET['secret']);
        $autologin_param_exists = !empty($_GET['_autologin']);

        $rcmail->write_log('autologon', "is_autologin_request: Vérification des paramètres d'autologin - _autologin=" . ($autologin_param_exists ? 'oui' : 'non') . 
                                       ", IP OK: " . ($ip_ok ? 'oui' : 'non') . 
                                       ", Secret OK: " . ($secret_ok ? 'oui' : 'non') . 
                                       ", IP Client: " . $_SERVER['REMOTE_ADDR']);

        return $autologin_param_exists && ($ip_ok || $secret_ok);
    }
}