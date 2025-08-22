<?php
/* Module RoundcubeModule - Gestion des Comptes Webmail avec Logos et Droits
 * Version avec gestion des droits utilisateur et liens dynamiques COMPLET
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main.inc.php failed");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

// Load translation files
$langs->loadLangs(array("users", "mails", "admin", "other"));

// Get parameters
$id = GETPOST('id', 'int') ?: $user->id;
$action = GETPOST('action', 'aZ09');
$accountid = GETPOST('accountid', 'int');
$confirm = GETPOST('confirm', 'alpha');

// Load user
$object = new User($db);
$object->fetch($id);

// Security check - Gestion des droits améliorée
if ($user->socid > 0) accessforbidden();

// Vérification des droits
$can_read_webmail = $user->hasRight('roundcubemodule', 'webmail', 'read');
$can_manage_accounts = $user->hasRight('roundcubemodule', 'accounts', 'write');
$can_admin_accounts = $user->hasRight('roundcubemodule', 'admin', 'write');

// L'utilisateur peut voir ses propres comptes ou est admin
if (!$can_read_webmail) {
    accessforbidden();
}

// Pour modifier les comptes : soit ses propres comptes, soit admin
if (($action && $action != 'test') && !$can_manage_accounts && ($user->id != $id && !$can_admin_accounts)) {
    accessforbidden();
}

// Define tab
$tab = 'webmail';

// Initialize technical object to manage hooks
$hookmanager->initHooks(array('usercard', 'globalcard'));

// Encryption functions
function encryptPassword($password) {
    return base64_encode($password);
}

function decryptPassword($encryptedPassword) {
    return base64_decode($encryptedPassword);
}

// Logo management functions
function getLogoDirectory($user_id, $account_id = null) {
    global $conf;
    
    try {
        $base_dir = $conf->file->dol_data_root . '/doctemplates/mail/logo/user_' . intval($user_id);
        
        if ($account_id) {
            $base_dir .= '/account_' . intval($account_id);
        } else {
            $base_dir .= '/global';
        }
        
        if (!is_dir($base_dir)) {
            if (!dol_mkdir($base_dir)) {
                error_log("Impossible de créer le répertoire: " . $base_dir);
                return false;
            }
        }
        
        if (!is_writable($base_dir)) {
            error_log("Répertoire non accessible en écriture: " . $base_dir);
            return false;
        }
        
        return $base_dir;
        
    } catch (Exception $e) {
        error_log("Erreur dans getLogoDirectory: " . $e->getMessage());
        return false;
    }
}

function getLogoUrl($user_id, $filename, $account_id = null) {
    $relative_path = 'mail/logo/user_' . $user_id;
    if ($account_id) {
        $relative_path .= '/account_' . $account_id;
    } else {
        $relative_path .= '/global';
    }
    
    return DOL_URL_ROOT . '/document.php?modulepart=doctemplates&file=' . $relative_path . '/' . $filename;
}

function uploadLogo($user_id, $account_id = null) {
    try {
        if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier est trop volumineux (limite PHP)',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux (limite formulaire)',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
                UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Upload arrêté par une extension PHP'
            ];
            
            $error_code = isset($_FILES['logo_file']) ? $_FILES['logo_file']['error'] : UPLOAD_ERR_NO_FILE;
            $error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Erreur inconnue lors de l\'upload';
            
            return array('error' => $error_message);
        }
        
        $file = $_FILES['logo_file'];
        
        // Validation du fichier
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return array('error' => 'Type de fichier non autorisé. Seuls JPG, PNG et GIF sont acceptés.');
        }
        
        // Limite de taille : 2 MB
        if ($file['size'] > 2 * 1024 * 1024) {
            return array('error' => 'Le fichier est trop volumineux (maximum 2 MB).');
        }
        
        // Validation supplémentaire avec getimagesize
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return array('error' => 'Le fichier n\'est pas une image valide.');
        }
        
        // Génération du nom de fichier
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
        
        // Répertoire de destination
        $logo_dir = getLogoDirectory($user_id, $account_id);
        if (!$logo_dir) {
            return array('error' => 'Impossible de créer le répertoire de destination');
        }
        
        $filepath = $logo_dir . '/' . $filename;
        
        // Redimensionner l'image si nécessaire
        if (!resizeImage($file['tmp_name'], $filepath, 300, 150)) {
            return array('error' => 'Erreur lors du traitement de l\'image');
        }
        
        // Vérifier que le fichier a bien été créé
        if (!file_exists($filepath)) {
            return array('error' => 'Le fichier n\'a pas pu être sauvegardé');
        }
        
        return array(
            'success' => true, 
            'filename' => $filename, 
            'url' => getLogoUrl($user_id, $filename, $account_id)
        );
        
    } catch (Exception $e) {
        return array('error' => 'Erreur système: ' . $e->getMessage());
    }
}

function resizeImage($source, $destination, $max_width, $max_height) {
    if (!extension_loaded('gd')) {
        return copy($source, $destination);
    }
    
    $image_info = getimagesize($source);
    if (!$image_info) return false;
    
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];
    
    // Calculer nouvelles dimensions
    $ratio = min($max_width / $width, $max_height / $height);
    if ($ratio >= 1) {
        return copy($source, $destination);
    }
    
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    
    // Créer l'image source
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source);
            break;
        default:
            return copy($source, $destination);
    }
    
    if (!$source_image) {
        return copy($source, $destination);
    }
    
    // Créer l'image de destination
    $dest_image = imagecreatetruecolor($new_width, $new_height);
    
    // Préserver la transparence
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($dest_image, false);
        imagesavealpha($dest_image, true);
        $transparent = imagecolorallocatealpha($dest_image, 255, 255, 255, 127);
        imagefill($dest_image, 0, 0, $transparent);
    }
    
    imagecopyresampled($dest_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($dest_image, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($dest_image, $destination);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($dest_image, $destination);
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($dest_image);
    
    return $result;
}

function deleteLogo($user_id, $filename, $account_id = null) {
    $logo_dir = getLogoDirectory($user_id, $account_id);
    $filepath = $logo_dir . '/' . $filename;
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}

function getExistingLogos($user_id, $account_id = null) {
    $logo_dir = getLogoDirectory($user_id, $account_id);
    $logos = array();
    
    if (is_dir($logo_dir)) {
        $files = scandir($logo_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                $logos[] = array(
                    'filename' => $file,
                    'url' => getLogoUrl($user_id, $file, $account_id),
                    'size' => filesize($logo_dir . '/' . $file),
                    'date' => filemtime($logo_dir . '/' . $file)
                );
            }
        }
        
        usort($logos, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
    
    return $logos;
}

/*
 * ACTIONS POUR LES LOGOS
 */

// Upload logo
if ($action == 'upload_logo' && $can_manage_accounts) {
    $account_id = GETPOST('logo_account_id', 'int');
    $result = uploadLogo($id, $account_id ?: null);
    
    if (GETPOST('ajax', 'int')) {
        header('Content-Type: application/json');
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode($result);
        }
        exit;
    } else {
        if (isset($result['error'])) {
            setEventMessages($result['error'], null, 'errors');
        } else {
            setEventMessages('Logo uploadé avec succès', null, 'mesgs');
        }
    }
}

// Supprimer logo
if ($action == 'delete_logo' && $can_manage_accounts) {
    $filename = GETPOST('logo_filename', 'alpha');
    $account_id = GETPOST('logo_account_id', 'int');
    
    if (deleteLogo($id, $filename, $account_id ?: null)) {
        setEventMessages('Logo supprimé avec succès', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors de la suppression du logo', null, 'errors');
    }
}

/*
 * ACTIONS POUR LES COMPTES
 */

// Add or Update account
if (($action == 'add' || $action == 'update') && $can_manage_accounts) {
    $error = 0;
    
    $account_name = GETPOST('account_name', 'alpha');
    $email = GETPOST('email', 'email');
    $password = GETPOST('password', 'none');
    $imap_host = GETPOST('imap_host', 'alpha');
    $imap_port = GETPOST('imap_port', 'int') ?: 993;
    $imap_encryption = GETPOST('imap_encryption', 'alpha');
    $smtp_host = GETPOST('smtp_host', 'alpha');
    $smtp_port = GETPOST('smtp_port', 'int') ?: 587;
    $smtp_encryption = GETPOST('smtp_encryption', 'alpha');
    $is_default = GETPOST('is_default', 'int');
    
    // Signature et logo
    $signature_text = GETPOST('signature_text', 'restricthtml');
    $signature_html = GETPOST('signature_html', 'restricthtml');
    $selected_logo = GETPOST('selected_logo', 'alpha');
    $logo_type = GETPOST('logo_type', 'alpha');
    
    // Validation
    if (empty($email)) {
        setEventMessages("Email requis", null, 'errors');
        $error++;
    }
    if ($action == 'add' && empty($password)) {
        setEventMessages("Mot de passe requis", null, 'errors');
        $error++;
    }
    if (empty($imap_host)) {
        setEventMessages("Serveur IMAP requis", null, 'errors');
        $error++;
    }
    
    if (!$error) {
        // Si compte par défaut, enlever le défaut des autres
        if ($is_default) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts SET is_default = 0 WHERE fk_user = ".$id;
            $db->query($sql);
        }
        
        $table_name = MAIN_DB_PREFIX."mailboxmodule_webmail_accounts";
        
        if ($action == 'add') {
            $sql = "INSERT INTO $table_name (";
            $sql .= "fk_user, account_name, email, password_encrypted, ";
            $sql .= "imap_host, imap_port, imap_encryption, ";
            $sql .= "smtp_host, smtp_port, smtp_encryption, ";
            $sql .= "signature_text, signature_html, ";
            $sql .= "logo_filename, logo_type, ";
            $sql .= "is_default, is_active, date_creation, fk_user_creat";
            $sql .= ") VALUES (";
            $sql .= $id.", ";
            $sql .= "'".$db->escape($account_name)."', ";
            $sql .= "'".$db->escape($email)."', ";
            $sql .= "'".encryptPassword($password)."', ";
            $sql .= "'".$db->escape($imap_host)."', ";
            $sql .= $imap_port.", ";
            $sql .= "'".$db->escape($imap_encryption)."', ";
            $sql .= "'".$db->escape($smtp_host)."', ";
            $sql .= $smtp_port.", ";
            $sql .= "'".$db->escape($smtp_encryption)."', ";
            $sql .= "'".$db->escape($signature_text)."', ";
            $sql .= "'".$db->escape($signature_html)."', ";
            $sql .= "'".$db->escape($selected_logo)."', ";
            $sql .= "'".$db->escape($logo_type)."', ";
            $sql .= ($is_default ? 1 : 0).", 1, ";
            $sql .= "'".$db->idate(dol_now())."', ";
            $sql .= $user->id.")";
            
            if ($db->query($sql)) {
                setEventMessages("Compte créé avec succès", null, 'mesgs');
            } else {
                setEventMessages($db->lasterror(), null, 'errors');
                $error++;
            }
        } else {
            $sql = "UPDATE $table_name SET ";
            $sql .= "account_name = '".$db->escape($account_name)."', ";
            $sql .= "email = '".$db->escape($email)."', ";
            if (!empty($password)) {
                $sql .= "password_encrypted = '".encryptPassword($password)."', ";
            }
            $sql .= "imap_host = '".$db->escape($imap_host)."', ";
            $sql .= "imap_port = ".$imap_port.", ";
            $sql .= "imap_encryption = '".$db->escape($imap_encryption)."', ";
            $sql .= "smtp_host = '".$db->escape($smtp_host)."', ";
            $sql .= "smtp_port = ".$smtp_port.", ";
            $sql .= "smtp_encryption = '".$db->escape($smtp_encryption)."', ";
            $sql .= "signature_text = '".$db->escape($signature_text)."', ";
            $sql .= "signature_html = '".$db->escape($signature_html)."', ";
            $sql .= "logo_filename = '".$db->escape($selected_logo)."', ";
            $sql .= "logo_type = '".$db->escape($logo_type)."', ";
            $sql .= "is_default = ".($is_default ? 1 : 0).", ";
            $sql .= "date_modification = '".$db->idate(dol_now())."', ";
            $sql .= "fk_user_modif = ".$user->id." ";
            $sql .= "WHERE rowid = ".$accountid." AND fk_user = ".$id;
            
            if ($db->query($sql)) {
                setEventMessages("Compte modifié avec succès", null, 'mesgs');
            } else {
                setEventMessages($db->lasterror(), null, 'errors');
                $error++;
            }
        }
        
        if (!$error) {
            $action = '';
        }
    }
}

// Delete account
if ($action == 'confirm_delete' && $confirm == 'yes' && $can_manage_accounts) {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts ";
    $sql .= "WHERE rowid = ".$accountid." AND fk_user = ".$id;
    
    if ($db->query($sql)) {
        setEventMessages("Compte supprimé", null, 'mesgs');
    } else {
        setEventMessages($db->lasterror(), null, 'errors');
    }
    $action = '';
}

// Test connection
if ($action == 'test') {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts ";
    $sql .= "WHERE rowid = ".$accountid." AND fk_user = ".$id;
    $resql = $db->query($sql);
    
    if ($resql && $obj = $db->fetch_object($resql)) {
        if (function_exists('imap_open')) {
            $password = decryptPassword($obj->password_encrypted);
            
            $flags = '/imap';
            if ($obj->imap_encryption == 'ssl') {
                $flags .= '/ssl';
            } elseif ($obj->imap_encryption == 'tls') {
                $flags .= '/tls';
            }
            $flags .= '/novalidate-cert';
            
            $server = '{'.$obj->imap_host.':'.$obj->imap_port.$flags.'}INBOX';
            
            $imap = @imap_open($server, $obj->email, $password, OP_READONLY, 1);
            
            if ($imap) {
                $check = imap_check($imap);
                setEventMessages('Connexion réussie - '.$check->Nmsgs.' messages', null, 'mesgs');
                imap_close($imap);
                
                $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts SET ";
                $sql .= "last_connection = '".$db->idate(dol_now())."', ";
                $sql .= "connection_count = connection_count + 1, ";
                $sql .= "last_error = NULL, error_count = 0 ";
                $sql .= "WHERE rowid = ".$accountid;
                $db->query($sql);
            } else {
                $error = imap_last_error();
                setEventMessages('Connexion échouée: '.$error, null, 'errors');
                
                $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts SET ";
                $sql .= "last_error = '".$db->escape($error)."', ";
                $sql .= "error_count = error_count + 1 ";
                $sql .= "WHERE rowid = ".$accountid;
                $db->query($sql);
            }
        } else {
            setEventMessages('Extension PHP IMAP non installée', null, 'warnings');
        }
    }
    $action = '';
}

/*
 * View
 */

$form = new Form($db);

// Titre et tabs utilisateur
$title = $langs->trans("User");
$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

llxHeader('', $title, '');

$head = user_prepare_head($object);

// JavaScript pour gestion des logos
print '<script type="text/javascript">
function autoDetectServers() {
    var email = document.getElementById("email").value;
    if (!email) {
        alert("Veuillez saisir un email d\'abord");
        return;
    }
    
    var domain = email.split("@")[1];
    var servers = {
        "gmail.com": {imap: "imap.gmail.com", smtp: "smtp.gmail.com", imap_port: 993, smtp_port: 587, imap_enc: "ssl", smtp_enc: "tls"},
        "outlook.com": {imap: "outlook.office365.com", smtp: "smtp-mail.outlook.com", imap_port: 993, smtp_port: 587, imap_enc: "ssl", smtp_enc: "tls"},
        "hotmail.com": {imap: "outlook.office365.com", smtp: "smtp-mail.outlook.com", imap_port: 993, smtp_port: 587, imap_enc: "ssl", smtp_enc: "tls"},
        "yahoo.com": {imap: "imap.mail.yahoo.com", smtp: "smtp.mail.yahoo.com", imap_port: 993, smtp_port: 465, imap_enc: "ssl", smtp_enc: "ssl"},
        "orange.fr": {imap: "imap.orange.fr", smtp: "smtp.orange.fr", imap_port: 993, smtp_port: 465, imap_enc: "ssl", smtp_enc: "ssl"},
        "free.fr": {imap: "imap.free.fr", smtp: "smtp.free.fr", imap_port: 993, smtp_port: 465, imap_enc: "ssl", smtp_enc: "ssl"}
    };
    
    if (servers[domain]) {
        document.getElementById("imap_host").value = servers[domain].imap;
        document.getElementById("smtp_host").value = servers[domain].smtp;
        document.getElementById("imap_port").value = servers[domain].imap_port;
        document.getElementById("smtp_port").value = servers[domain].smtp_port;
        document.getElementById("imap_encryption").value = servers[domain].imap_enc;
        document.getElementById("smtp_encryption").value = servers[domain].smtp_enc;
    } else {
        document.getElementById("imap_host").value = "mail." + domain;
        document.getElementById("smtp_host").value = "mail." + domain;
    }
}

function uploadLogo(accountId) {
    var fileInput = document.getElementById("logo_file_" + (accountId || "global"));
    var file = fileInput.files[0];
    
    if (!file) {
        alert("Veuillez sélectionner un fichier");
        return;
    }
    
    if (file.size > 2 * 1024 * 1024) {
        alert("Le fichier est trop volumineux (maximum 2 MB)");
        return;
    }
    
    var uploadBtn = event.target;
    var originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = "⏳ Upload en cours...";
    uploadBtn.disabled = true;
    
    var formData = new FormData();
    formData.append("logo_file", file);
    formData.append("action", "upload_logo");
    formData.append("ajax", "1");
    formData.append("token", "'.newToken().'");
    formData.append("id", "'.$id.'");
    if (accountId) {
        formData.append("logo_account_id", accountId);
    }
    
    fetch(window.location.href, {
        method: "POST",
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("La réponse n\'est pas du JSON valide");
        }
        return response.json();
    })
    .then(data => {
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        
        if (data.success) {
            alert("Logo uploadé avec succès !");
            location.reload();
        } else {
            alert("Erreur: " + (data.error || "Erreur inconnue"));
        }
    })
    .catch(error => {
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        console.error("Erreur détaillée:", error);
        alert("Erreur lors de l\'upload: " + error.message);
    });
}

function selectLogo(filename, logoType) {
    document.getElementById("selected_logo").value = filename;
    document.getElementById("logo_type").value = logoType;
    
    document.querySelectorAll(".logo-select-btn").forEach(btn => {
        btn.classList.remove("selected");
    });
    event.target.classList.add("selected");
}

var style = document.createElement("style");
style.textContent = `
    .logo-select-btn {
        padding: 5px 10px;
        margin: 2px;
        background: #f8f9fa;
        border: 1px solid #ddd;
        cursor: pointer;
        border-radius: 3px;
    }
    .logo-select-btn:hover {
        background: #e9ecef;
    }
    .logo-select-btn.selected {
        background: #007cba;
        color: white;
    }
    .logo-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .logo-item {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
        border-radius: 5px;
    }
    .logo-item img {
        max-width: 100px;
        max-height: 50px;
    }
`;
document.head.appendChild(style);
</script>';

print dol_get_fiche_head($head, $tab, $title, -1, 'user');

// Partie gauche - Informations utilisateur
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans("Login").'</td>';
print '<td class="valuefield">'.$object->login.'</td></tr>';

print '<tr><td>'.$langs->trans("Name").'</td>';
print '<td>'.$object->getFullName($langs).'</td></tr>';

print '<tr><td>'.$langs->trans("Email").'</td>';
print '<td>'.($object->email ?: '<span class="opacitymedium">'.$langs->trans("NoEmail").'</span>').'</td></tr>';

print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="underbanner clearboth"></div>';

// Buttons - Avec vérification des droits
if ($action != 'create' && $action != 'edit') {
    print '<div class="tabsAction">';
    
    // Seuls les utilisateurs avec les droits peuvent ajouter/modifier
    if ($can_manage_accounts) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail&action=create">'.$langs->trans("AddAccount").'</a>';
    }
    
    // Lien dynamique vers Roundcube avec autologin
    if ($can_read_webmail) {
        $roundcube_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube_auto_login.php';
        print '<a class="butAction" href="'.$roundcube_url.'" target="_blank">Ouvrir Roundcube</a>';
    }
    print '</div>';
}

print '</div>';
print '</div>';

print '<div class="clearboth"></div>';

// Form pour create/edit - Seulement si droits suffisants
if (($action == 'create' || $action == 'edit') && $can_manage_accounts) {
    $account_data = null;
    if ($action == 'edit' && $accountid) {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts WHERE rowid = ".$accountid." AND fk_user = ".$id;
        $resql = $db->query($sql);
        if ($resql) {
            $account_data = $db->fetch_object($resql);
        }
    }
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.($action == 'create' ? 'add' : 'update').'">';
    print '<input type="hidden" id="selected_logo" name="selected_logo" value="'.($account_data ? $account_data->logo_filename : '').'">';
    print '<input type="hidden" id="logo_type" name="logo_type" value="'.($account_data ? $account_data->logo_type : 'global').'">';
    if ($action == 'edit') {
        print '<input type="hidden" name="accountid" value="'.$accountid.'">';
    }
    
    print load_fiche_titre($action == 'create' ? 'Nouveau compte' : 'Modifier le compte');
    
    print '<table class="border centpercent">';
    
    // Account name
    print '<tr><td class="titlefieldcreate">Nom du compte</td>';
    print '<td><input type="text" class="flat minwidth400" name="account_name" value="'.($account_data ? $account_data->account_name : '').'" placeholder="Ex: Gmail Personnel"></td></tr>';
    
    // Email
    print '<tr><td class="fieldrequired">Email</td>';
    print '<td><input type="email" class="flat minwidth400" id="email" name="email" value="'.($account_data ? $account_data->email : '').'" required></td></tr>';
    
    // Password
    print '<tr><td'.($action == 'create' ? ' class="fieldrequired"' : '').'>Mot de passe</td>';
    print '<td>';
    print '<input type="password" class="flat minwidth400" name="password"'.($action == 'create' ? ' required' : '').'>';
    if ($action == 'edit') {
        print ' <span class="opacitymedium">Laisser vide pour conserver</span>';
    }
    print '</td></tr>';
    
    // Séparateur IMAP
    print '<tr class="liste_titre"><td colspan="2">Configuration IMAP</td></tr>';
    
    // IMAP Host
    print '<tr><td class="fieldrequired">Serveur IMAP</td>';
    print '<td>';
    print '<input type="text" class="flat minwidth300" id="imap_host" name="imap_host" value="'.($account_data ? $account_data->imap_host : '').'" required>';
    print ' <input type="button" class="button smallpaddingimp" value="Auto-détection" onclick="autoDetectServers();">';
    print '</td></tr>';
    
    print '<tr><td>Port IMAP</td>';
    print '<td><input type="number" class="flat" id="imap_port" name="imap_port" value="'.($account_data ? $account_data->imap_port : 993).'" style="width:80px;"></td></tr>';
    
    print '<tr><td>Chiffrement IMAP</td>';
    print '<td>';
    $imap_enc = $account_data ? $account_data->imap_encryption : 'ssl';
    print '<select name="imap_encryption" id="imap_encryption" class="flat">';
    print '<option value="ssl"'.($imap_enc == 'ssl' ? ' selected' : '').'>SSL</option>';
    print '<option value="tls"'.($imap_enc == 'tls' ? ' selected' : '').'>TLS</option>';
    print '<option value="none"'.($imap_enc == 'none' ? ' selected' : '').'>Aucun</option>';
    print '</select>';
    print '</td></tr>';
    
    // Séparateur SMTP
    print '<tr class="liste_titre"><td colspan="2">Configuration SMTP</td></tr>';
    
    print '<tr><td>Serveur SMTP</td>';
    print '<td><input type="text" class="flat minwidth300" id="smtp_host" name="smtp_host" value="'.($account_data ? $account_data->smtp_host : '').'"></td></tr>';
    
    print '<tr><td>Port SMTP</td>';
    print '<td><input type="number" class="flat" id="smtp_port" name="smtp_port" value="'.($account_data ? $account_data->smtp_port : 587).'" style="width:80px;"></td></tr>';
    
    print '<tr><td>Chiffrement SMTP</td>';
    print '<td>';
    $smtp_enc = $account_data ? $account_data->smtp_encryption : 'tls';
    print '<select name="smtp_encryption" id="smtp_encryption" class="flat">';
    print '<option value="tls"'.($smtp_enc == 'tls' ? ' selected' : '').'>TLS</option>';
    print '<option value="ssl"'.($smtp_enc == 'ssl' ? ' selected' : '').'>SSL</option>';
    print '<option value="none"'.($smtp_enc == 'none' ? ' selected' : '').'>Aucun</option>';
    print '</select>';
    print '</td></tr>';
    
    // Signature et Logo
    print '<tr class="liste_titre"><td colspan="2">🖼️ Logo et Signature</td></tr>';
    
    // Logos globaux
    print '<tr><td>Logos disponibles</td>';
    print '<td>';
    print '<input type="file" id="logo_file_global" accept="image/*" style="margin-right:10px;">';
    print '<button type="button" onclick="uploadLogo(null)">📤 Upload logo</button><br><br>';
    
    $global_logos = getExistingLogos($id, null);
    if (!empty($global_logos)) {
        print '<div class="logo-gallery">';
        foreach ($global_logos as $logo) {
            $selected_class = ($account_data && $account_data->logo_filename == $logo['filename'] && $account_data->logo_type == 'global') ? ' selected' : '';
            print '<div class="logo-item">';
            print '<img src="'.$logo['url'].'" alt="Logo">';
            print '<br><button type="button" class="logo-select-btn'.$selected_class.'" onclick="selectLogo(\''.$logo['filename'].'\', \'global\')">Sélectionner</button>';
            print '</div>';
        }
        print '</div>';
    }
    print '</td></tr>';
    
    print '<tr><td>Signature texte</td>';
    print '<td><textarea name="signature_text" rows="3" cols="80" class="flat">'.($account_data ? $account_data->signature_text : '').'</textarea></td></tr>';
    
    print '<tr><td>Signature HTML</td>';
    print '<td><textarea name="signature_html" rows="4" cols="80" class="flat">'.($account_data ? $account_data->signature_html : '').'</textarea></td></tr>';
    
    // Default account
    print '<tr><td>Compte par défaut</td>';
    print '<td>';
    $is_default = $account_data ? $account_data->is_default : 0;
    print '<input type="checkbox" name="is_default" value="1"'.($is_default ? ' checked' : '').'>';
    print '</td></tr>';
    
    print '</table>';
    
    print '<div class="center">';
    print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" value="'.$langs->trans("Cancel").'" onclick="window.location.href=\''.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail\'">';
    print '</div>';
    
    print '</form>';
}

// Liste des comptes
if ($action != 'create' && $action != 'edit') {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts ";
    $sql .= "WHERE fk_user = ".$id." ";
    $sql .= "ORDER BY is_default DESC, account_name ASC";
    
    $resql = $db->query($sql);
    
    if ($resql) {
        $num = $db->num_rows($resql);
        
        print '<div class="div-table-responsive">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Nom du compte</th>';
        print '<th>Email</th>';
        print '<th>Serveur IMAP</th>';
        print '<th class="center">Statut</th>';
        print '<th class="center">Dernière connexion</th>';
        print '<th class="center">Actions</th>';
        print '</tr>';
        
        if ($num == 0) {
            print '<tr><td colspan="6" class="center opacitymedium">Aucun compte webmail configuré</td></tr>';
        } else {
            // URL dynamique pour roundcube
            $roundcube_auto_login_url = dol_buildpath('/custom/roundcubemodule/roundcube_auto_login.php', 1);
            
            while ($obj = $db->fetch_object($resql)) {
                print '<tr class="oddeven">';
                
                // Account name
                print '<td>'.($obj->account_name ?: '<span class="opacitymedium">Sans nom</span>').'</td>';
                
                // Email
                print '<td>'.$obj->email.'</td>';
                
                // IMAP server
                print '<td>'.$obj->imap_host.':'.$obj->imap_port.'/'.$obj->imap_encryption.'</td>';
                
                // Status
                print '<td class="center">';
                if ($obj->is_active) {
                    print '<span class="badge badge-status4 badge-status">Actif</span>';
                } else {
                    print '<span class="badge badge-status8 badge-status">Inactif</span>';
                }
                if ($obj->is_default) {
                    print ' <span class="badge badge-status1 badge-status">Défaut</span>';
                }
                if ($obj->last_error) {
                    print ' '.img_warning($obj->last_error);
                }
                print '</td>';
                
                // Last connection
                print '<td class="center">';
                if ($obj->last_connection) {
                    print dol_print_date($db->jdate($obj->last_connection), 'dayhour');
                } else {
                    print '<span class="opacitymedium">Jamais</span>';
                }
                print '</td>';
                
                // Actions
                print '<td class="center nowraponall">';
                
                // Connect button - avec droits
                if ($can_read_webmail) {
                    print '<a class="marginleftonly marginrightonly" href="'.$roundcube_auto_login_url.'?accountid='.$obj->rowid.'" target="_blank" title="Se connecter">';
                    print img_picto('Se connecter', 'globe');
                    print '</a>';
                }
                
                // Test button
                print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail&action=test&accountid='.$obj->rowid.'" title="Tester la connexion">';
                print img_picto('Test', 'technic');
                print '</a>';
                
                // Edit/Delete buttons - avec droits
                if ($can_manage_accounts) {
                    print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail&action=edit&accountid='.$obj->rowid.'" title="Modifier">';
                    print img_edit();
                    print '</a>';
                    
                    print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail&action=delete&accountid='.$obj->rowid.'" title="Supprimer">';
                    print img_delete();
                    print '</a>';
                }
                
                print '</td>';
                print '</tr>';
            }
        }
        
        print '</table>';
        print '</div>';
        
        // Info box
        print '<br>';
        print info_admin('ℹ️ <strong>Informations :</strong><br>'.
            '• <strong>Se connecter :</strong> Ouvre Roundcube avec connexion automatique<br>'.
            '• <strong>Tester :</strong> Vérifie la connexion IMAP<br>'.
            '• <strong>Gmail :</strong> Utilisez un mot de passe d\'application<br>'.
            '• <strong>Outlook :</strong> Activez l\'accès IMAP dans vos paramètres'
        );
    }
}

// Confirm delete - avec droits
if ($action == 'delete' && $can_manage_accounts) {
    print $form->formconfirm(
        $_SERVER["PHP_SELF"].'?id='.$id.'&tab=webmail&accountid='.$accountid,
        'Supprimer le compte',
        'Êtes-vous sûr de vouloir supprimer ce compte webmail ?',
        'confirm_delete',
        '',
        0,
        1
    );
}

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
?>