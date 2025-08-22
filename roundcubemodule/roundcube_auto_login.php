<?php
/**
 * Script d'autologin amélioré pour Roundcube depuis Dolibarr
 * Version avec connexion automatique sans saisie de mot de passe
 */

// Charger l'environnement Dolibarr
$res = 0;
$paths = ['../../main.inc.php', '../../../main.inc.php', '../../../../main.inc.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    die('Erreur: Impossible de charger main.inc.php');
}

// Vérifier la connexion utilisateur
if (empty($user->id)) {
    accessforbidden();
}

// Vérifier les droits d'accès au webmail
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    accessforbidden('Vous n\'avez pas les droits pour accéder au webmail');
}

// Fonction pour décrypter le mot de passe
function decryptPassword($encryptedPassword) {
    return base64_decode($encryptedPassword);
}

// Configuration URL Roundcube
$roundcube_url = '';
if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_url = $conf->global->ROUNDCUBE_URL;
    
    if (strpos($roundcube_url, 'http') !== 0) {
        if (strpos($roundcube_url, '/') === 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $roundcube_url = $protocol . $_SERVER['HTTP_HOST'] . $roundcube_url;
        } else {
            $roundcube_url = dol_buildpath($roundcube_url, 1);
        }
    }
} else {
    $roundcube_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube/';
}

if (substr($roundcube_url, -1) !== '/') {
    $roundcube_url .= '/';
}

$debug = !empty($conf->global->ROUNDCUBE_DEBUG);

// Récupérer l'ID du compte webmail
$accountid = null;
$account_data = null;

if (isset($_GET['accountid'])) {
    $accountid = intval($_GET['accountid']);
    
    // Vérifier que l'utilisateur a le droit d'accéder à ce compte
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts WHERE rowid = ".$accountid;
    $result = $db->query($sql);
    if ($result && $obj = $db->fetch_object($result)) {
        if ($obj->fk_user != $user->id && !$user->hasRight('roundcubemodule', 'admin', 'write')) {
            accessforbidden('Vous n\'avez pas les droits pour accéder à ce compte');
        }
        $account_data = $obj;
    }
} else {
    // Chercher le compte par défaut de l'utilisateur
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts ";
    $sql .= "WHERE fk_user = " . $user->id . " AND is_active = 1 ";
    $sql .= "ORDER BY is_default DESC, rowid ASC LIMIT 1";
    
    $result = $db->query($sql);
    if ($result && $obj = $db->fetch_object($result)) {
        $account_data = $obj;
        $accountid = $obj->rowid;
    }
}

// Si aucun compte trouvé, rediriger vers la configuration
if (!$account_data) {
    $config_url = dol_buildpath('/user/card.php?id='.$user->id.'&tab=webmail', 1);
    header('Location: ' . $config_url);
    exit;
}

// Construire l'URL avec autologin complet
$password = decryptPassword($account_data->password_encrypted);
$separator = (strpos($roundcube_url, '?') === false) ? '?' : '&';

$redirect_url = $roundcube_url . $separator . 
               '_user=' . urlencode($account_data->email) . 
               '&_pass=' . urlencode($password) . 
               '&_host=' . urlencode($account_data->imap_host);

if ($debug) {
    error_log("AUTOLOGIN DEBUG: Connexion automatique pour " . $account_data->email);
    error_log("AUTOLOGIN DEBUG: Redirection vers " . $redirect_url);
    
    // Mode debug: afficher les informations
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Debug Autologin Roundcube</title></head><body>";
    echo "<h2>🎉 Autologin automatique Dolibarr → Roundcube</h2>";
    
    echo "<div style='background:#d4edda; padding:15px; border:1px solid #28a745; border-radius:5px; margin:10px 0;'>";
    echo "<h3>✅ Connexion automatique activée !</h3>";
    echo "<p><strong>Utilisateur Dolibarr :</strong> " . $user->login . " (" . $user->getFullName($langs) . ")</p>";
    echo "<p><strong>Compte email :</strong> " . $account_data->email . "</p>";
    echo "<p><strong>Serveur IMAP :</strong> " . $account_data->imap_host . ":" . $account_data->imap_port . "</p>";
    echo "<p><strong>Compte :</strong> " . ($account_data->account_name ?: "Sans nom");
    if ($account_data->is_default) echo " <span style='color:#28a745;'>(Par défaut)</span>";
    echo "</p>";
    echo "</div>";
    
    echo "<div style='background:#f0f8ff; padding:15px; border:1px solid #007bff; border-radius:5px; margin:10px 0;'>";
    echo "<h3>🔗 Connexion automatique</h3>";
    echo "<p><a href='$redirect_url' target='_blank' style='background:#007bff; color:white; padding:12px 20px; text-decoration:none; border-radius:5px; font-size:16px;'>📧 Se connecter automatiquement</a></p>";
    echo "<p><em>Connexion automatique sans saisie de mot de passe !</em></p>";
    echo "</div>";
    
    echo "<div style='background:#fff3cd; padding:15px; border:1px solid #ffc107; border-radius:5px; margin:10px 0;'>";
    echo "<h3>⚙️ Configuration</h3>";
    echo "<p><strong>URL de redirection :</strong></p>";
    echo "<code style='background:#f8f9fa; padding:5px; display:block; word-wrap:break-word;'>$redirect_url</code>";
    echo "<p><strong>Fonctionnement :</strong></p>";
    echo "<ol>";
    echo "<li>✅ Authentification Dolibarr validée</li>";
    echo "<li>🔍 Récupération du compte par défaut</li>";
    echo "<li>🔓 Déchiffrement du mot de passe</li>";
    echo "<li>📧 Connexion automatique à Roundcube</li>";
    echo "</ol>";
    echo "</div>";
    
    // Liens de retour
    echo "<p>";
    if ($user->hasRight('roundcubemodule', 'config', 'write')) {
        $config_url = dol_buildpath('/custom/roundcubemodule/admin/roundcube_config.php', 1);
        echo "<a href='$config_url'>⚙️ Configuration du module</a> | ";
    }
    if ($user->hasRight('roundcubemodule', 'accounts', 'write')) {
        $accounts_url = dol_buildpath('/user/card.php?id='.$user->id.'&tab=webmail', 1);
        echo "<a href='$accounts_url'>📧 Mes comptes webmail</a> | ";
    }
    echo "<a href='".DOL_URL_ROOT."'>🏠 Retour à Dolibarr</a>";
    echo "</p>";
    
    echo "</body></html>";
    exit;
}

// Redirection automatique (si debug = false)
header('Location: ' . $redirect_url);
exit;
?>