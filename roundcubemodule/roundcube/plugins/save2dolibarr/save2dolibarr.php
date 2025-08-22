<?php

class save2dolibarr extends rcube_plugin
{
    public $task = 'mail';

    private $dolibarr_script_url;
    private $dolibarr_search_target_url;
    private $dolibarr_check_sender_url;
    private $dolibarr_active_modules_url;

    function init()
    {
        $this->load_dolibarr_urls();
        $this->include_script('save2dolibarr.js');
        $this->include_stylesheet('save2dolibarr.css');

        $this->add_button([
            'command' => 'plugin.save2dolibarr',
            'type' => 'link',
            'title' => 'Classer dans Dolibarr',
            'class' => 'button icon save2dolibarr',
            'label' => 'Classer',
        ], 'toolbar');

        $this->register_action('plugin.save2dolibarr', [$this, 'save_mail']);
        $this->register_action('plugin.save2dolibarr_search_targets', [$this, 'search_targets']);
        $this->register_action('plugin.get_sender_email', [$this, 'get_sender_email']);
        $this->register_action('plugin.get_active_dolibarr_modules', [$this, 'get_active_dolibarr_modules']); 
        $this->add_hook('render_page', [$this, 'inject_modal']);
    }

    private function load_dolibarr_urls()
    {
        $rcmail = rcmail::get_instance();
        $base_url_for_dolibarr_scripts = null;

        $script_name = $_SERVER['SCRIPT_NAME'];
        $request_scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $request_scheme = 'https';
        }
        $http_host = $_SERVER['HTTP_HOST'];

        $rcmail->write_log('plugin', "load_dolibarr_urls: SCRIPT_NAME actuel: " . $script_name);

        // Cas 1 : SCRIPT_NAME contient '/htdocs/', on le conserve
        $htdocs_pos = strpos($script_name, '/htdocs/');
        if ($htdocs_pos !== false) {
            $path_segment_to_htdocs = substr($script_name, 0, $htdocs_pos + strlen('/htdocs/'));
            $base_url_for_dolibarr_scripts = $request_scheme . '://' . $http_host . rtrim($path_segment_to_htdocs, '/');
            $rcmail->write_log('plugin', "load_dolibarr_urls: Cas 1 - htdocs trouvÃ© dans SCRIPT_NAME. Base Dolibarr: " . $base_url_for_dolibarr_scripts);
        } else {
            // Cas 2 : SCRIPT_NAME ne contient PAS '/htdocs/'
            $roundcube_relative_base = '/custom/roundcubemodule/roundcube/';
            $pos_roundcube_base = strrpos($script_name, $roundcube_relative_base);

            if ($pos_roundcube_base !== false) {
                $dolibarr_root_url_segment = substr($script_name, 0, $pos_roundcube_base);
                $base_url_for_dolibarr_scripts = $request_scheme . '://' . $http_host . rtrim($dolibarr_root_url_segment, '/');
                $rcmail->write_log('plugin', "load_dolibarr_urls: Cas 2 - Roundcube trouvÃ©. Base Dolibarr: " . $base_url_for_dolibarr_scripts);
            } else {
                // Fallback
                $base_url_for_dolibarr_scripts = $request_scheme . '://' . $http_host;
                $rcmail->write_log('plugin', "load_dolibarr_urls: Fallback - URL par dÃ©faut. Base Dolibarr: " . $base_url_for_dolibarr_scripts);
            }
        }

        $base_url_cleaned = rtrim($base_url_for_dolibarr_scripts, '/');

        // URLs corrigÃ©es vers roundcubemodule (PAS mailboxmodule)
        $this->dolibarr_script_url = $base_url_cleaned . '/custom/roundcubemodule/scripts/save_mails.php';
        $this->dolibarr_search_target_url = $base_url_cleaned . '/custom/roundcubemodule/scripts/search_targets.php';
        $this->dolibarr_check_sender_url = $base_url_cleaned . '/custom/roundcubemodule/scripts/check_sender.php';
        $this->dolibarr_active_modules_url = $base_url_cleaned . '/custom/roundcubemodule/scripts/get_active_modules_simple.php';

        $rcmail->write_log('plugin', "load_dolibarr_urls: URLs configurÃ©es pour roundcubemodule");
        $rcmail->write_log('plugin', "load_dolibarr_urls: Active modules URL: " . $this->dolibarr_active_modules_url);
    }

    function inject_modal($args)
    {
        $modal_html = '
           <div id="save2dolibarr_modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; border:1px solid #ccc; padding:25px; max-width:95%; width:1100px; max-height:95vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.15); border-radius:8px; z-index:1000;">
        <h3 style="margin-top:0; color:#333; padding-bottom:10px; border-bottom:1px solid #eee;">
            <span style="color:#f07c00;">ðŸ—‚</span> Classer dans Dolibarr
        </h3>

        <div style="margin:15px 0; padding:15px; background:#f8f9fa; border-radius:6px;">
            <table style="width:100%; border-collapse: collapse; font-size: 14px;">
                <tbody>
                    <tr>
                        <td style="width:120px; padding:8px; vertical-align: middle; font-weight:bold;">
                            Tiers :
                        </td>
                        <td style="padding:8px;">
                            <span id="sender_info" style="font-weight:500;">Recherche en cours...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p style="margin-bottom:15px; font-weight:bold; color:#444;">SÃ©lectionnez les modules et effectuez vos recherches :</p>

        <div style="margin-bottom:20px; background:#f8f9fa; padding:20px; border-radius:6px; border:1px solid #ddd; min-height:400px;">
            <div id="active_modules_checkbox_container" style="display: flex; flex-direction: column; gap: 15px; max-height:500px; overflow-y:auto; padding-right:10px;">
                <div style="text-align: center; padding: 20px;">
                    <span class="loading-spinner"></span> Chargement des modules...
                </div>
            </div>
        </div>

        <div style="margin-top:25px; text-align:right; padding-top:15px; border-top:1px solid #eee;">
            <button id="save2dolibarr_submit" style="margin-right:10px; padding:10px 20px; background:#f07c00; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
                ðŸ—‚ Classer le mail
            </button>
            <button id="save2dolibarr_submit_only" style="margin-right:10px; padding:10px 20px; background:#1565c0; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
                ðŸ’¾ Sauvegarder sans lien
            </button>
            <button id="save2dolibarr_close" style="padding:10px 20px; background:#757575; color:white; border:none; border-radius:4px; cursor:pointer;">
                Annuler
            </button>
        </div>
    </div>

    <div id="save2dolibarr_overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999;"></div>';

        $args['content'] .= $modal_html;
        $rcmail = rcmail::get_instance();
        $rcmail->output->set_env('save2dolibarr_check_sender_url', $this->dolibarr_check_sender_url);
        return $args;
    }

    function get_sender_email()
    {
        $rcmail = rcmail::get_instance();
        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC);

        if (is_array($uid) && count($uid) > 0) {
            $uid = $uid[0];
        }

        $rcmail->write_log('plugin', 'get_sender_email: UID reÃ§u = ' . print_r($uid, true));

        if (!$uid) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'UID manquant', 'debug' => $_POST]);
            exit;
        }

        try {
            $message = new rcube_message($uid);
            $from_header = $message->get_header('from');
            $sender_email = $this->extract_email($from_header);
            
            $rcmail->write_log('plugin', 'get_sender_email: From = ' . $from_header . ', Email extrait = ' . $sender_email);

            header('Content-Type: application/json');
            echo json_encode(['email' => $sender_email, 'from' => $from_header]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    private function extract_email($from_header) 
    {
        if (empty($from_header)) {
            return null;
        }
        
        if (preg_match('/<([^>]+)>/', $from_header, $matches)) {
            return $matches[1];
        }
        
        if (filter_var($from_header, FILTER_VALIDATE_EMAIL)) {
            return $from_header;
        }
        
        return null;
    }

    function save_mail()
    {
        $rcmail = rcmail::get_instance();
        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC);
        $targets_json = rcube_utils::get_input_value('_links', rcube_utils::INPUT_GPC);
        $fk_soc = rcube_utils::get_input_value('_fk_soc', rcube_utils::INPUT_GPC);
        
        $targets = json_decode($targets_json, true);
        if (is_null($targets)) {
            $targets = [];
        }

        if (is_array($uid)) $uid = $uid[0];

        $rcmail->write_log('plugin', 'save_mail: UID = ' . print_r($uid, true));

        if (!$uid) {
            $rcmail->output->command('display_message', 'âŒ Erreur : aucun mail sÃ©lectionnÃ©', 'error');
            $rcmail->output->send('plugin');
            return;
        }

        try {
            $storage = $rcmail->get_storage();
            $message = new rcube_message($uid);

            $raw_email_content = $storage->get_raw_body($uid);
            if (!$raw_email_content) {
                throw new Exception("Impossible de rÃ©cupÃ©rer le contenu du mail");
            }

            $from_header = $message->get_header('from');
            $subject = $message->subject ?: 'Sans sujet';
            $message_id = $message->get_header('message-id');
            $raw_date = $message->get_header('date');
            
            $datetime_sql = null;
            if (!empty($raw_date)) {
                $timestamp = strtotime($raw_date);
                if ($timestamp !== false) {
                    $datetime_sql = date('Y-m-d H:i:s', $timestamp);
                }
            }
            if (empty($datetime_sql)) {
                $datetime_sql = date('Y-m-d H:i:s');
            }

            // GÃ©rer les piÃ¨ces jointes
            $attachments = [];
            $upload_dir = dirname(dirname(dirname(__DIR__))) . '/data/fichier_join/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $parts = $message->mime_parts;
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if ($part->disposition == 'attachment' || 
                        ($part->disposition == 'inline' && !empty($part->filename))) {
                        
                        $filename = rcube_mime::decode_mime_string($part->filename, 'UTF-8');
                        if (!$filename) {
                            $filename = 'unnamed_' . uniqid() . '.bin';
                        }
                        
                        $filename = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $filename);
                        
                        $file_content = $message->get_part_content($part->mime_id);
                        if ($file_content !== false) {
                            $filepath = $upload_dir . $uid . '_' . $filename;
                            if (file_put_contents($filepath, $file_content)) {
                                $attachments[] = [
                                    'name' => $filename, 
                                    'path' => $filepath,
                                    'size' => strlen($file_content)
                                ];
                            }
                        }
                    }
                }
            }

            $payload = json_encode([
                'uid' => $uid,
                'mbox' => $mbox ?: 'INBOX',
                'fk_soc' => $fk_soc,
                'rc_user_email' => $rcmail->user->get_username(),
                'from' => $from_header,
                'subject' => $subject,
                'message_id' => $message_id,
                'date' => $datetime_sql,
                'raw_email' => $raw_email_content,
                'attachments' => $attachments,
                'links' => $targets
            ]);

            $ch = curl_init($this->dolibarr_script_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($curl_err) {
                throw new Exception("Erreur de connexion : $curl_err");
            }
            
            if ($http_code !== 200) {
                throw new Exception("Erreur serveur Dolibarr (HTTP $http_code)");
            }

            $json = json_decode($response, true);
            if (!$json) {
                throw new Exception("RÃ©ponse invalide de Dolibarr");
            }
            
            if (!isset($json['status']) || $json['status'] !== 'OK') {
                $msg = $json['message'] ?? 'Erreur inconnue';
                throw new Exception($msg);
            }

            $success_msg = 'âœ… Mail classÃ© dans Dolibarr';
            if (!empty($json['message'])) {
                $success_msg .= ' : ' . $json['message'];
            }
            
            $rcmail->output->command('display_message', $success_msg, 'confirmation');

        } catch (Throwable $e) {
            $error_msg = 'âŒ Erreur lors du classement : ' . $e->getMessage();
            $rcmail->output->command('display_message', $error_msg, 'error');
        }

        $rcmail->output->send('plugin');
    }

    function search_targets()
    {
        $q = rcube_utils::get_input_value('q', rcube_utils::INPUT_GPC);
        $type = rcube_utils::get_input_value('type', rcube_utils::INPUT_GPC);

        $url = $this->dolibarr_search_target_url . "?q=" . urlencode($q) . "&type=" . urlencode($type);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        header('Content-Type: application/json');
        
        if ($response === false || $http_code !== 200) {
            echo json_encode(['error' => 'Erreur de recherche', 'results' => []]);
        } else {
            echo $response;
        }
        
        exit;
    }

    function get_active_dolibarr_modules()
    {
        $rcmail = rcmail::get_instance();
        $dolibarr_url = $this->dolibarr_active_modules_url;
        
        $rcmail->write_log('plugin', "get_active_dolibarr_modules: Tentative d'accÃ¨s Ã  " . $dolibarr_url);

        $ch = curl_init($dolibarr_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $rcmail->write_log('plugin', "get_active_dolibarr_modules: HTTP Code = " . $http_code);
        $rcmail->write_log('plugin', "get_active_dolibarr_modules: Curl Error = " . $curl_error);
        $rcmail->write_log('plugin', "get_active_dolibarr_modules: Response COMPLÃˆTE = " . $response);

        header('Content-Type: application/json');

        if ($response === false || $http_code !== 200) {
            echo json_encode([
                'error' => 'Impossible de rÃ©cupÃ©rer les modules actifs',
                'details' => $curl_error,
                'http_code' => $http_code,
                'url' => $dolibarr_url,
                'raw_response' => $response
            ]);
        } else {
            // VÃ©rifier si c'est du JSON valide
            $json_test = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $rcmail->write_log('plugin', "get_active_dolibarr_modules: ERREUR JSON - " . json_last_error_msg());
                echo json_encode([
                    'error' => 'RÃ©ponse invalide du serveur Dolibarr',
                    'json_error' => json_last_error_msg(),
                    'raw_response' => substr($response, 0, 500),
                    'url' => $dolibarr_url
                ]);
            } else {
                echo $response;
            }
        }
        
        exit;
    }
}
?>