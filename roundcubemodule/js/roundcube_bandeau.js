<?php
/**
 * Page principale du module Roundcube avec gestion des droits et synchronisation des mails
 * Version simplifiée avec focus sur l'essentiel
 */

// Recherche de main.inc.php
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
    die('Erreur: Impossible de trouver main.inc.php. Vérifiez le chemin d\'installation.');
}

// Vérifier la connexion utilisateur
if (empty($user->id)) {
    accessforbidden();
}

// Vérifier les droits d'accès au webmail
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    accessforbidden('Vous n\'avez pas les droits pour accéder au webmail');
}

// Configuration
$conf->dol_hide_leftmenu = 1;
$langs->load("mails");

// Header
llxHeader('', 'Roundcube Module - Webmail Intégré');

// Configuration de l'URL Roundcube
// Essayer plusieurs emplacements possibles
$roundcube_base_url = '';

// D'abord vérifier la configuration globale
if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_base_url = $conf->global->ROUNDCUBE_URL;
} else {
    // Sinon, essayer de détecter automatiquement
    // Option 1: Dans le dossier du module
    $test_path = DOL_DOCUMENT_ROOT . '/custom/roundcubemodule/roundcube/index.php';
    if (file_exists($test_path)) {
        $roundcube_base_url = '/AVOCATS/htdocs/custom/roundcubemodule/roundcube/';
    }
    // Option 2: Installation séparée dans WAMP
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcube/index.php')) {
        $roundcube_base_url = '/roundcube/';
    }
    // Option 3: Installation dans roundcubemail
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcubemail/index.php')) {
        $roundcube_base_url = '/roundcubemail/';
    }
    // Option 4: Webmail
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/webmail/index.php')) {
        $roundcube_base_url = '/webmail/';
    }
    // Par défaut
    else {
        $roundcube_base_url = '/roundcube/';
    }
}

// Convertir en URL absolue si nécessaire
if (strpos($roundcube_base_url, 'http') !== 0) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Si le chemin ne commence pas par /, l'ajouter
    if (strpos($roundcube_base_url, '/') !== 0) {
        $roundcube_base_url = '/' . $roundcube_base_url;
    }
    
    $roundcube_base_url = $protocol . $host . $roundcube_base_url;
}

// S'assurer que l'URL se termine par /
if (substr($roundcube_base_url, -1) !== '/') {
    $roundcube_base_url .= '/';
}

// Construire l'URL finale
$roundcube_url = $roundcube_base_url;

// Ajouter l'autologin si les identifiants sont disponibles
$sql = "SELECT login_roundcube, password_roundcube FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$user->id;
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    if (!empty($obj->login_roundcube) && !empty($obj->password_roundcube)) {
        // Ajouter les paramètres d'autologin
        $separator = (strpos($roundcube_url, '?') === false) ? '?' : '&';
        $roundcube_url = $roundcube_url . $separator . '_user=' . urlencode($obj->login_roundcube) . '&_pass=' . urlencode($obj->password_roundcube);
    }
}

// Debug: afficher l'URL générée (à commenter en production)
if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    print '<!-- Roundcube URL: ' . htmlspecialchars($roundcube_url) . ' -->';
}

// Inclure les CSS et JS
?>
<link rel="stylesheet" href="css/roundcube_bandeau.css">
<script src="js/roundcube_bandeau.js"></script>

<style>
/* Styles simplifiés pour le bandeau */
#roundcube-container {
    display: flex;
    height: calc(100vh - 60px);
    position: relative;
}

#roundcube-iframe {
    flex: 1;
    border: none;
    background: #f5f5f5;
}

#bandeau {
    width: 320px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    overflow-y: auto;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
}

#bandeau .section {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

#bandeau h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.info-label {
    font-weight: 500;
    opacity: 0.9;
}

.module-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.module-tag {
    background: rgba(255,255,255,0.2);
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    text-decoration: none;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.module-tag:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
    text-decoration: none;
    color: white;
}

.btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    margin-right: 5px;
}

.btn:hover {
    background: rgba(255,255,255,0.3);
}

.btn-primary {
    background: rgba(76, 175, 80, 0.3);
    border-color: rgba(76, 175, 80, 0.5);
}

.btn-danger {
    background: rgba(244, 67, 54, 0.3);
    border-color: rgba(244, 67, 54, 0.5);
}

.no-mail-selected {
    text-align: center;
    padding: 30px;
    opacity: 0.7;
}

.no-mail-selected i {
    font-size: 48px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.rights-indicator {
    font-size: 11px;
    opacity: 0.8;
    margin-top: 10px;
    padding: 5px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
}

#notification {
    position: fixed;
    top: 70px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 4px;
    background: #333;
    color: white;
    font-size: 14px;
    z-index: 10000;
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.3s ease;
}

#notification.show {
    opacity: 1;
    transform: translateY(0);
}

#notification.success {
    background: #4caf50;
}

#notification.error {
    background: #f44336;
}

#notification.info {
    background: #2196f3;
}
</style>

<div id="roundcube-container">
    <!-- Message d'erreur si Roundcube ne charge pas -->
    <div id="roundcube-error" style="display:none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                                      background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                                      z-index: 1000; text-align: center;">
        <h3 style="color: #e74c3c;">⚠️ Roundcube non accessible</h3>
        <p>L'URL configurée ne répond pas : <br><code style="background: #f5f5f5; padding: 5px;"><?php echo htmlspecialchars($roundcube_url); ?></code></p>
        <p>Veuillez vérifier la configuration :</p>
        <a href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/roundcube_setup.php', 1); ?>" class="button">
            ⚙️ Configurer l'URL
        </a>
        <br><br>
        <details>
            <summary>Détails techniques</summary>
            <small style="text-align: left; display: block; margin-top: 10px;">
                URL testée : <?php echo htmlspecialchars($roundcube_url); ?><br>
                Serveur : <?php echo $_SERVER['HTTP_HOST']; ?><br>
                Document root : <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
            </small>
        </details>
    </div>
    
    <!-- Iframe Roundcube -->
    <iframe id="roundcube-iframe" 
            src="<?php echo htmlspecialchars($roundcube_url); ?>"
            onerror="handleIframeError()"
            style="width: 100%; height: 100%; border: none;">
    </iframe>
    
    <!-- Bandeau de gestion simplifié -->
    <div id="bandeau">
        <!-- Section Utilisateur -->
        <div class="section">
            <h3>👤 Utilisateur</h3>
            <div class="info-row">
                <span class="info-label">Nom:</span>
                <span><?php echo $user->getFullName($langs); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?php echo $user->email ?: 'Non défini'; ?></span>
            </div>
            <div class="rights-indicator">
                Droits: 
                <?php 
                $rights = [];
                if ($user->hasRight('roundcubemodule', 'webmail', 'read')) $rights[] = 'Lecture';
                if ($user->hasRight('roundcubemodule', 'accounts', 'write')) $rights[] = 'Comptes';
                if ($user->hasRight('roundcubemodule', 'admin', 'write')) $rights[] = 'Admin';
                echo implode(', ', $rights) ?: 'Aucun';
                ?>
            </div>
        </div>
        
        <!-- Section Mail Actuel -->
        <div class="section">
            <h3>📧 Mail sélectionné</h3>
            <div id="mail-info" style="display:none;">
                <div class="info-row">
                    <span class="info-label">Sujet:</span>
                </div>
                <div style="font-size: 13px; margin-bottom: 10px; word-break: break-word;">
                    <span id="mail-subject">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">De:</span>
                    <span id="mail-from" style="font-size: 12px;">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span id="mail-date" style="font-size: 12px;">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Statut:</span>
                    <span id="mail-status">-</span>
                </div>
                
                <!-- Modules liés -->
                <div id="mail-modules" style="margin-top: 15px; display: none;">
                    <div style="font-size: 12px; margin-bottom: 8px; opacity: 0.9;">Modules liés:</div>
                    <div id="modules-list" class="module-tags">
                        <!-- Les tags des modules seront insérés ici -->
                    </div>
                </div>
            </div>
            
            <div id="mail-no-selection" class="no-mail-selected">
                <div style="font-size: 32px; margin-bottom: 10px;">📭</div>
                <p style="font-size: 13px; opacity: 0.8;">Sélectionnez un mail dans Roundcube</p>
            </div>
        </div>
        
        <!-- Section Actions sur le mail -->
        <div class="section" id="mail-actions-section" style="display:none;">
            <h3>⚡ Actions</h3>
            <div id="mail-actions">
                <button id="save-mail-btn" class="btn btn-primary" onclick="saveMail()">
                    💾 Sauvegarder dans Dolibarr
                </button>
                <button id="link-mail-btn" class="btn" onclick="linkMail()" style="display:none;">
                    🔗 Lier à un module
                </button>
                <button id="unlink-mail-btn" class="btn" onclick="unlinkMail()" style="display:none;">
                    ✂️ Délier
                </button>
                <button id="refresh-mail-btn" class="btn" onclick="refreshMailStatus()">
                    🔄 Actualiser
                </button>
            </div>
        </div>
        
        <!-- Section Gestion -->
        <div class="section">
            <h3>⚙️ Configuration</h3>
            
            <?php if ($user->hasRight('roundcubemodule', 'accounts', 'write')): ?>
            <a class="btn" href="<?php echo dol_buildpath('/user/card.php?id='.$user->id.'&tab=webmail', 1); ?>">
                📧 Mes comptes mail
            </a>
            <?php endif; ?>
            
            <?php if ($user->hasRight('roundcubemodule', 'admin', 'write')): ?>
            <a class="btn" href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/accounts_list.php', 1); ?>">
                👥 Gérer les comptes
            </a>
            <?php endif; ?>
            
            <?php if ($user->hasRight('roundcubemodule', 'config', 'write')): ?>
            <a class="btn" href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/roundcube_config.php', 1); ?>">
                ⚙️ Paramètres
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Section Debug & Test -->
        <div class="section">
            <h3>🧪 Debug & Test</h3>
            <button class="btn" onclick="testMail()">📧 Simuler un mail</button>
            <button class="btn" onclick="forceUpdateFromIframe()">🔄 Forcer mise à jour</button>
            <button class="btn" onclick="checkIframeStatus()">🔍 Vérifier iframe</button>
            <button class="btn" onclick="testAjaxAPI()">🌐 Test API AJAX</button>
            <button class="btn" onclick="checkServerLogs()">📋 Logs serveur</button>
            <?php if (!empty($conf->global->ROUNDCUBE_DEBUG)): ?>
            <button class="btn" onclick="showDebug()">📊 Debug complet</button>
            <?php endif; ?>
            <div id="api-status" style="margin-top: 10px;">
                <div id="api-result" style="font-size: 11px; color: rgba(255,255,255,0.8);">En attente...</div>
            </div>
        </div>
    </div>
</div>

<!-- Notification -->
<div id="notification"></div>

<!-- Modal de sélection de module -->
<div id="module-modal" class="modal" style="display:none;">
    <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 100px auto;">
        <span class="close" onclick="closeModuleModal()" style="float: right; font-size: 24px; cursor: pointer;">&times;</span>
        <h3>Lier le mail à un module</h3>
        <div id="module-modal-body">
            <!-- Contenu dynamique -->
        </div>
    </div>
</div>

<script>
// Configuration PHP vers JavaScript
const CONFIG = {
    API_URL: '<?php echo dol_buildpath('/custom/roundcubemodule/api/mail_sync_api.php', 1); ?>',
    AJAX_URL: '<?php echo dol_buildpath('/custom/roundcubemodule/ajax/get_mail_modules.php', 1); ?>',
    SAVE_URL: '<?php echo dol_buildpath('/custom/roundcubemodule/scripts/save_mails.php', 1); ?>',
    ROUNDCUBE_URL: '<?php echo $roundcube_url; ?>',
    USER_ID: <?php echo $user->id; ?>,
    USER_EMAIL: '<?php echo $user->email; ?>',
    DEBUG: <?php echo !empty($conf->global->ROUNDCUBE_DEBUG) ? 'true' : 'false'; ?>,
    RIGHTS: {
        webmail: <?php echo $user->hasRight('roundcubemodule', 'webmail', 'read') ? 'true' : 'false'; ?>,
        accounts: <?php echo $user->hasRight('roundcubemodule', 'accounts', 'write') ? 'true' : 'false'; ?>,
        admin: <?php echo $user->hasRight('roundcubemodule', 'admin', 'write') ? 'true' : 'false'; ?>
    }
};

// Variables globales
let currentMailData = null;
let currentMailUID = null;
let currentMailId = null;
let isMailSaved = false;

function showDebug() {
    const debug = {
        config: CONFIG,
        currentMail: currentMailData,
        currentMailUID: currentMailUID,
        currentMailId: currentMailId,
        isMailSaved: isMailSaved,
        timestamp: new Date().toISOString()
    };
    console.log('Debug Info:', debug);
    alert('Debug Info:\n' + JSON.stringify(debug, null, 2));
}

// Test d'un mail (mode debug)
function testMail() {
    console.log('🧪 Test manuel d\'un mail...');
    
    const testData = {
        type: 'roundcube_mail_selected',
        data: {
            uid: 'test_' + Date.now(),
            message_id: '<test.' + Date.now() + '@example.com>',
            subject: 'Mail de test - ' + new Date().toLocaleTimeString(),
            from: 'Test User <test@example.com>',
            from_email: 'test@example.com',
            date: new Date().toISOString(),
            folder: 'INBOX',
            has_attachments: Math.random() > 0.5,
            is_read: false
        }
    };
    
    console.log('Envoi du mail de test:', testData);
    
    // Simuler la réception d'un message Roundcube
    window.handleRoundcubeMessage({ data: testData });
}

// Fonction pour forcer la mise à jour manuelle
function forceUpdateFromIframe() {
    console.log('🔄 Tentative de récupération forcée des données...');
    
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) {
        console.error('Iframe non trouvée');
        return;
    }
    
    try {
        // Essayer d'envoyer un message à l'iframe pour demander les données
        iframe.contentWindow.postMessage({
            type: 'request_current_mail',
            timestamp: new Date().toISOString()
        }, '*');
        console.log('Message envoyé à l\'iframe');
    } catch (e) {
        console.error('Erreur envoi message:', e);
    }
}

// Fonction pour vérifier le statut de l'iframe
function checkIframeStatus() {
    console.log('🔍 Vérification du statut de l\'iframe...');
    
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) {
        alert('❌ Iframe non trouvée');
        return;
    }
    
    let status = 'Iframe trouvée\n';
    status += 'URL: ' + iframe.src + '\n';
    
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (iframeDoc) {
            status += '✅ Accès au document OK (même origine)\n';
            
            // Vérifier si le script est injecté
            if (iframe.contentWindow.roundcubeDetectionActive) {
                status += '✅ Script de détection actif\n';
            } else {
                status += '❌ Script de détection non actif\n';
                status += '→ Tentative de réinjection...\n';
                injectScript();
            }
            
            // Vérifier si Roundcube est chargé
            if (iframe.contentWindow.rcmail) {
                status += '✅ Roundcube détecté\n';
                if (iframe.contentWindow.rcmail.env && iframe.contentWindow.rcmail.env.uid) {
                    status += '✅ Mail sélectionné: UID ' + iframe.contentWindow.rcmail.env.uid + '\n';
                } else {
                    status += '⚠️ Aucun mail sélectionné\n';
                }
            } else {
                status += '⚠️ Roundcube non détecté (page de login?)\n';
            }
        } else {
            status += '⚠️ Pas d\'accès au document (cross-origin?)\n';
        }
    } catch (e) {
        status += '⚠️ Cross-origin: ' + e.message + '\n';
        status += 'ℹ️ Normal si Roundcube est sur un autre domaine\n';
    }
    
    alert(status);
    console.log(status);
}

// Écouter les messages de l'iframe
window.addEventListener('message', function(e) {
    // Log TOUS les messages pour debug
    if (e.data && typeof e.data === 'object') {
        console.log('📨 Message window reçu:', e.data);
        
        // Passer au handler si c'est un message Roundcube
        if (e.data.type && e.data.type.includes('roundcube')) {
            handleRoundcubeMessage(e);
        }
    }
});

// Fonction d'injection du script (à appeler manuellement si besoin)
function injectScript() {
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) return;
    
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        
        if (iframeDoc && !iframeDoc.getElementById('roundcube-detection-script')) {
            const script = iframeDoc.createElement('script');
            script.id = 'roundcube-detection-script';
            script.textContent = getIframeDetectionScript();
            
            iframeDoc.head.appendChild(script);
            console.log('✅ Script réinjecté manuellement');
            
            showNotification('Script réinjecté', 'success');
        }
    } catch (error) {
        console.warn('⚠️ Impossible d\'injecter le script:', error.message);
    }
}

// Gestion des erreurs de chargement de l'iframe
function handleIframeError() {
    console.error('Erreur de chargement de Roundcube');
    document.getElementById('roundcube-error').style.display = 'block';
}

// Vérifier si l'iframe charge correctement
setTimeout(function() {
    const iframe = document.getElementById('roundcube-iframe');
    try {
        // Essayer d'accéder au document de l'iframe
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc || !iframeDoc.body || iframeDoc.body.innerHTML.includes('404') || iframeDoc.body.innerHTML.includes('Not Found')) {
            handleIframeError();
        }
    } catch(e) {
        // Cross-origin, on ne peut pas vérifier, mais c'est probablement OK
        console.log('Iframe cross-origin, impossible de vérifier le contenu');
    }
}, 3000);

// Écouter les messages de l'iframe
window.addEventListener('message', handleRoundcubeMessage);

// Initialisation du script d'injection dans l'iframe
document.addEventListener('DOMContentLoaded', function() {
    console.log('Module Roundcube simplifié - Chargé');
    
    const iframe = document.getElementById('roundcube-iframe');
    
    // Fonction pour injecter le script
    function injectScript() {
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            
            if (iframeDoc) {
                // Vérifier si le script n'est pas déjà injecté
                if (!iframeDoc.getElementById('roundcube-detection-script')) {
                    const script = iframeDoc.createElement('script');
                    script.id = 'roundcube-detection-script';
                    script.textContent = getIframeDetectionScript();
                    
                    iframeDoc.head.appendChild(script);
                    console.log('✅ Script de détection injecté avec succès');
                }
            } else {
                console.warn('⚠️ Impossible d\'accéder au contenu de l\'iframe (cross-origin)');
            }
        } catch (error) {
            console.warn('⚠️ Impossible d\'injecter le script de détection:', error.message);
        }
    }
    
    // Injecter le script au chargement de l'iframe
    iframe.onload = function() {
        console.log('Iframe Roundcube chargée');
        // Attendre un peu que Roundcube soit complètement chargé
        setTimeout(injectScript, 2000);
    };
    
    // Réinjecter le script périodiquement au cas où Roundcube recharge son contenu
    setInterval(function() {
        if (iframe.contentDocument || iframe.contentWindow) {
            injectScript();
        }
    }, 5000);
    
    // Test de l'API si en mode debug
    if (CONFIG && CONFIG.DEBUG) {
        testAPI();
    }
});

// Script à injecter dans l'iframe Roundcube
function getIframeDetectionScript() {
    return `
(function() {
    console.log('🔍 Script de détection Roundcube activé - Version 2.0');
    
    // Éviter les injections multiples
    if (window.roundcubeDetectionActive) {
        console.log('Script déjà actif, skip');
        return;
    }
    window.roundcubeDetectionActive = true;
    
    let currentMailData = null;
    let lastUID = null;
    
    // Fonction pour extraire les données du mail sélectionné
    function extractMailData() {
        let mailData = {
            subject: null,
            from: null,
            from_email: null,
            date: null,
            message_id: null,
            uid: null,
            folder: null,
            has_attachments: false,
            is_read: false
        };
        
        try {
            // Méthode 1: Via l'API Roundcube
            if (window.rcmail && window.rcmail.env) {
                console.log('API Roundcube détectée');
                if (window.rcmail.env.uid) {
                    mailData.uid = String(window.rcmail.env.uid);
                    mailData.folder = window.rcmail.env.mailbox || 'INBOX';
                    
                    // Essayer de récupérer plus d'infos depuis l'environnement
                    if (window.rcmail.env.subject) {
                        mailData.subject = window.rcmail.env.subject;
                    }
                    
                    console.log('UID détecté via API:', mailData.uid);
                }
            }
            
            // Méthode 2: Parser le DOM pour les détails du mail affiché
            // Chercher dans le header du message
            const messageHeader = document.querySelector('#messageheader, .message-header, .messageheader, #message-header');
            if (messageHeader) {
                console.log('Header du message trouvé');
                
                // Sujet
                const subjectEl = messageHeader.querySelector('.subject, [class*="subject"], #message-subject');
                if (subjectEl) {
                    mailData.subject = subjectEl.textContent.trim();
                    console.log('Sujet trouvé:', mailData.subject);
                }
                
                // Expéditeur
                const fromEl = messageHeader.querySelector('.from, [class*="from"], #message-from');
                if (fromEl) {
                    mailData.from = fromEl.textContent.trim();
                    // Extraire l'email
                    const emailMatch = mailData.from.match(/<([^>]+)>/) || mailData.from.match(/([^\\s]+@[^\\s]+)/);
                    if (emailMatch) {
                        mailData.from_email = emailMatch[1];
                    }
                    console.log('Expéditeur trouvé:', mailData.from_email);
                }
                
                // Date
                const dateEl = messageHeader.querySelector('.date, [class*="date"], #message-date');
                if (dateEl) {
                    mailData.date = dateEl.textContent.trim();
                }
                
                // Message-ID
                const messageIdEl = messageHeader.querySelector('[class*="message-id"]');
                if (messageIdEl) {
                    mailData.message_id = messageIdEl.textContent.trim();
                }
            }
            
            // Méthode 3: Chercher dans la liste des messages
            const selectedMessage = document.querySelector(
                '.messagelist .selected, ' +
                '#messagelist .selected, ' +
                'tr.selected, ' +
                '.message-list .selected, ' +
                '[id^="rcmrow"].selected'
            );
            
            if (selectedMessage) {
                console.log('Message sélectionné dans la liste trouvé');
                
                // Extraire l'UID depuis l'ID de l'élément
                if (selectedMessage.id && !mailData.uid) {
                    const uidMatch = selectedMessage.id.match(/\\d+/);
                    if (uidMatch) {
                        mailData.uid = uidMatch[0];
                        console.log('UID extrait de l\'ID:', mailData.uid);
                    }
                }
                
                // Sujet depuis la liste
                if (!mailData.subject) {
                    const subjectCell = selectedMessage.querySelector('.subject, td.subject');
                    if (subjectCell) {
                        mailData.subject = subjectCell.textContent.trim();
                    }
                }
                
                // Expéditeur depuis la liste
                if (!mailData.from) {
                    const fromCell = selectedMessage.querySelector('.from, td.from');
                    if (fromCell) {
                        mailData.from = fromCell.textContent.trim();
                    }
                }
                
                // Date depuis la liste
                if (!mailData.date) {
                    const dateCell = selectedMessage.querySelector('.date, td.date');
                    if (dateCell) {
                        mailData.date = dateCell.textContent.trim();
                    }
                }
                
                // Pièces jointes
                const attachIcon = selectedMessage.querySelector('.attachment, .icon.attachment');
                mailData.has_attachments = !!attachIcon;
                
                // Statut lu/non lu
                mailData.is_read = !selectedMessage.classList.contains('unread');
            }
            
            // Méthode 4: Essayer de récupérer depuis le contenu du mail
            const messageContent = document.querySelector('#messagecontent, .messagecontent, #message-content');
            if (messageContent && !mailData.subject) {
                // Parfois le sujet est dans le contenu
                const contentSubject = messageContent.querySelector('.subject');
                if (contentSubject) {
                    mailData.subject = contentSubject.textContent.trim();
                }
            }
            
        } catch (e) {
            console.error('Erreur extraction données mail:', e);
        }
        
        return mailData;
    }
    
    // Envoyer les données au parent
    function sendMailData(mailData) {
        // Ne pas envoyer si pas de données utiles
        if (!mailData.uid && !mailData.subject) {
            console.log('Pas de données à envoyer');
            return;
        }
        
        // Ne pas renvoyer le même mail
        if (mailData.uid && mailData.uid === lastUID) {
            console.log('Mail déjà envoyé, skip');
            return;
        }
        
        lastUID = mailData.uid;
        mailData.timestamp = new Date().toISOString();
        
        console.log('📧 Envoi des données du mail vers le parent:', mailData);
        
        // Envoyer au parent de plusieurs façons pour s'assurer que ça passe
        try {
            // Méthode 1: parent direct
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'roundcube_mail_selected',
                    data: mailData
                }, '*');
                console.log('Message envoyé via parent.postMessage');
            }
            
            // Méthode 2: top window
            if (window.top && window.top !== window) {
                window.top.postMessage({
                    type: 'roundcube_mail_selected',
                    data: mailData
                }, '*');
                console.log('Message envoyé via top.postMessage');
            }
        } catch (e) {
            console.error('Erreur envoi message:', e);
        }
    }
    
    // Observer les changements dans le DOM
    function initObserver() {
        const observer = new MutationObserver((mutations) => {
            // Débounce pour éviter trop d'appels
            clearTimeout(window.extractTimeout);
            window.extractTimeout = setTimeout(() => {
                const mailData = extractMailData();
                if (mailData.uid || mailData.subject) {
                    sendMailData(mailData);
                }
            }, 300);
        });
        
        // Observer tout le document
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'id']
        });
        
        console.log('Observer initialisé');
    }
    
    // Intercepter les clics pour détecter la sélection
    document.addEventListener('click', function(e) {
        console.log('Clic détecté sur:', e.target);
        
        // Attendre que le DOM se mette à jour
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    }, true);
    
    // Intercepter les changements de hash (navigation dans Roundcube)
    window.addEventListener('hashchange', function() {
        console.log('Changement de hash détecté');
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    });
    
    // Vérification périodique (fallback)
    setInterval(() => {
        const mailData = extractMailData();
        if (mailData.uid || mailData.subject) {
            // Vérifier si les données ont changé
            const dataString = JSON.stringify(mailData);
            if (dataString !== currentMailData) {
                currentMailData = dataString;
                sendMailData(mailData);
            }
        }
    }, 2000);
    
    // Hook sur les commandes Roundcube si possible
    if (window.rcmail) {
        const originalCommand = window.rcmail.command_handler;
        window.rcmail.command_handler = function(command, props, obj, event) {
            console.log('Commande Roundcube:', command);
            
            // Appeler la fonction originale
            const result = originalCommand.apply(this, arguments);
            
            // Si c'est une commande de sélection de mail
            if (command === 'show' || command === 'preview' || command === 'select') {
                setTimeout(() => {
                    const mailData = extractMailData();
                    if (mailData.uid || mailData.subject) {
                        sendMailData(mailData);
                    }
                }, 500);
            }
            
            return result;
        };
    }
    
    // Initialiser
    initObserver();
    
    // Essayer d'extraire les données immédiatement
    setTimeout(() => {
        const mailData = extractMailData();
        if (mailData.uid || mailData.subject) {
            sendMailData(mailData);
        }
    }, 1000);
    
    // Notification au parent que le script est prêt
    window.parent.postMessage({
        type: 'roundcube_detection_ready',
        message: 'Détection activée v2.0'
    }, '*');
    
    console.log('✅ Détection Roundcube initialisée avec succès');
})();
    `;
}

// Fonction pour tester l'API AJAX
function testAjaxAPI() {
    console.log('🧪 Test de l\'API AJAX get_mail_modules.php...');
    
    // Tester avec un UID fictif
    const testUID = '123456';
    const url = CONFIG.AJAX_URL + '?uid=' + testUID;
    
    console.log('URL de test:', url);
    
    fetch(url)
        .then(response => {
            console.log('Réponse HTTP:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('✅ API AJAX fonctionnelle:', data);
            
            let message = 'API AJAX OK\n';
            message += 'Status: ' + data.status + '\n';
            
            if (data.status === 'NOT_FOUND') {
                message += 'Mail non trouvé (normal pour un test)\n';
            } else if (data.status === 'OK') {
                message += 'Mail trouvé!\n';
                message += 'Modules: ' + (data.modules ? data.modules.length : 0) + '\n';
            } else if (data.status === 'ERROR') {
                message += 'Erreur: ' + data.message + '\n';
            }
            
            alert(message);
        })
        .catch(error => {
            console.error('❌ Erreur API AJAX:', error);
            alert('Erreur API AJAX:\n' + error.message);
        });
}

// Fonction pour vérifier les logs serveur
function checkServerLogs() {
    console.log('📋 Instructions pour vérifier les logs serveur:');
    console.log('1. Ouvrez le fichier de log PHP (généralement dans C:\\wamp64\\logs\\php_error.log)');
    console.log('2. Cherchez les lignes contenant "get_mail_modules.php"');
    console.log('3. Vérifiez les valeurs de uid, mail_id et message_id');
    console.log('4. Vérifiez les requêtes SQL exécutées');
    
    alert('Consultez la console pour les instructions de debug.\n\nLes logs PHP se trouvent généralement dans:\nC:\\wamp64\\logs\\php_error.log');
}
</script>

<?php
// Footer
llxFooter();
?>