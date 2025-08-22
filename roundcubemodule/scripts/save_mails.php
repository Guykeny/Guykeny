<?php
/**
 * Script de sauvegarde des emails dans Dolibarr
 * Version avec gestion des reclassements (ajout / suppression / synchro)
 */

$debug_log = dirname(__FILE__) . '/debug.log';
function debug_log($message) {
    global $debug_log;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

debug_log("=== DÉBUT SAVE_MAILS DEBUG (MAILBOXMODULE) ===");
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

try {
    // === Initialisation Dolibarr ===
    $dolibarr_main_document_root = dirname(dirname(dirname(__DIR__)));
    $main_inc_path = $dolibarr_main_document_root . '/main.inc.php';
    if (!file_exists($main_inc_path)) {
        $main_inc_path = $dolibarr_main_document_root . '/htdocs/main.inc.php';
        if (!file_exists($main_inc_path)) throw new Exception('main.inc.php non trouvé');
    }
    define('NOLOGIN', 1); define('NOCSRFCHECK', 1);
    define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1);
    require_once $main_inc_path;
    global $db, $conf;

    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // === Lecture input JSON ===
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) throw new Exception('Données JSON invalides: '.json_last_error_msg());

    $uid         = $db->escape($data['uid'] ?? '');
    $mbox        = $db->escape($data['mbox'] ?? 'INBOX');
    $message_id  = $db->escape($data['message_id'] ?? '');
    $subject     = $db->escape($data['subject'] ?? 'Sans sujet');
    $from_raw    = $data['from'] ?? '';
    $raw_email   = $data['raw_email'] ?? '';
    $links       = $data['links'] ?? [];
    $action      = $data['action'] ?? null;

    if (!$from_raw || !$raw_email) throw new Exception("Données mail incomplètes");

    // Extraire email expéditeur
    if (preg_match('/<([^>]+)>/', $from_raw, $m)) $from_email = $db->escape($m[1]);
    else $from_email = $db->escape($from_raw);

    // === Vérification existence mail ===
    $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                  WHERE message_id='".$message_id."' 
                  OR (imap_uid=".(int)$uid." AND imap_mailbox='".$mbox."')";
    $res = $db->query($sql_check);

    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $mail_id = $obj->rowid;
        debug_log("Mail existant ID=$mail_id");

        // Charger liens existants
        $sql_links = "SELECT target_type, target_id, target_name 
                      FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links 
                      WHERE fk_mail=".$mail_id;
        $res_links = $db->query($sql_links);
        $existing = [];
        while ($row = $db->fetch_object($res_links)) {
            $existing[] = [
                'type' => $row->target_type,
                'id'   => $row->target_id,
                'name' => $row->target_name
            ];
        }

        // Calculer différences
        $proposed = $links;
        $to_add = []; $to_delete = []; $unchanged = [];

        foreach ($proposed as $lnk) {
            $found = false;
            foreach ($existing as $ex) {
                if ($lnk['type']==$ex['type'] && $lnk['id']==$ex['id']) {
                    $found = true; $unchanged[]=$lnk;
                }
            }
            if (!$found) $to_add[]=$lnk;
        }
        foreach ($existing as $ex) {
            $found = false;
            foreach ($proposed as $lnk) {
                if ($lnk['type']==$ex['type'] && $lnk['id']==$ex['id']) $found=true;
            }
            if (!$found) $to_delete[]=$ex;
        }

        if (empty($to_add) && empty($to_delete)) {
            echo json_encode(['status'=>'ALREADY_CLASSIFIED','message'=>'Mail déjà classé','links'=>$existing]);
            exit;
        }

        // Action demandée ?
        if ($action === 'add_links') {
            foreach ($to_add as $lnk) {
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links
                        (fk_mail,target_type,target_id,target_name,date_created)
                        VALUES (
                          ".$mail_id.",
                          '".$db->escape($lnk['type'])."',
                          ".(int)$lnk['id'].",
                          '".$db->escape($lnk['name'])."',
                          '".$db->idate(dol_now())."')";
                $db->query($sql);
            }
            echo json_encode(['status'=>'UPDATED','message'=>'Liens ajoutés','added'=>$to_add,'unchanged'=>$unchanged]);
            exit;
        }
        elseif ($action === 'delete_links') {
            foreach ($to_delete as $ex) {
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links
                        WHERE fk_mail=".$mail_id."
                        AND target_type='".$db->escape($ex['type'])."'
                        AND target_id=".(int)$ex['id'];
                $db->query($sql);
            }
            echo json_encode(['status'=>'UPDATED','message'=>'Liens supprimés','deleted'=>$to_delete,'unchanged'=>$unchanged]);
            exit;
        }
        elseif ($action === 'sync_links') {
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links WHERE fk_mail=".$mail_id);
            foreach ($proposed as $lnk) {
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links
                        (fk_mail,target_type,target_id,target_name,date_created)
                        VALUES (
                          ".$mail_id.",
                          '".$db->escape($lnk['type'])."',
                          ".(int)$lnk['id'].",
                          '".$db->escape($lnk['name'])."',
                          '".$db->idate(dol_now())."')";
                $db->query($sql);
            }
            echo json_encode(['status'=>'UPDATED','message'=>'Liens synchronisés','new_links'=>$proposed]);
            exit;
        }
        else {
            // Retourner proposition à l’utilisateur
            echo json_encode([
                'status'=>'DIFFERENT_LINKS',
                'message'=>'Le mail est déjà classé différemment',
                'mail_id'=>$mail_id,
                'existing'=>$existing,
                'proposed'=>$proposed,
                'to_add'=>$to_add,
                'to_delete'=>$to_delete,
                'unchanged'=>$unchanged
            ]);
            exit;
        }
    }

    // === Si pas existant → insertion normale (exemple simplifié) ===
    $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail
        (message_id,subject,from_email,date_received,imap_mailbox,imap_uid,direction)
        VALUES (
            '".$message_id."',
            '".$subject."',
            '".$from_email."',
            '".$db->idate(dol_now())."',
            '".$mbox."',
            ".(int)$uid.",
            'received')";
    $db->query($sql_insert);
    $mail_id = $db->last_insert_id(MAIN_DB_PREFIX."mailboxmodule_mail");

    foreach ($links as $lnk) {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links
                (fk_mail,target_type,target_id,target_name,date_created)
                VALUES (
                  ".$mail_id.",
                  '".$db->escape($lnk['type'])."',
                  ".(int)$lnk['id'].",
                  '".$db->escape($lnk['name'])."',
                  '".$db->idate(dol_now())."')";
        $db->query($sql);
    }

    echo json_encode(['status'=>'OK','message'=>'Mail enregistré','mail_id'=>$mail_id]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'ERROR','message'=>$e->getMessage()]);
}
debug_log("=== FIN SAVE_MAILS DEBUG (MAILBOXMODULE) ===\n");
exit;
?>
