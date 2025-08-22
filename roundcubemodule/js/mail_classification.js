<?php
/**
 * Page principale du module Roundcube avec bandeau de classement intégré
 * Version modifiée pour intégrer le classement dans le bandeau
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

// Configuration de l'URL Roundcube (conservée identique)
$roundcube_base_url = '';

if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_base_url = $conf->global->ROUNDCUBE_URL;
} else {
    $test_path = DOL_DOCUMENT_ROOT . '/custom/roundcubemodule/roundcube/index.php';
    if (file_exists($test_path)) {
        $roundcube_base_url = '/AVOCATS/htdocs/custom/roundcubemodule/roundcube/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcube/index.php')) {
        $roundcube_base_url = '/roundcube/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcubemail/index.php')) {
        $roundcube_base_url = '/roundcubemail/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/webmail/index.php')) {
        $roundcube_base_url = '/webmail/';
    }
    else {
        $roundcube_base_url = '/roundcube/';
    }
}

if (strpos($roundcube_base_url, 'http') !== 0) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($roundcube_base_url, '/') !== 0) {
        $roundcube_base_url = '/' . $roundcube_base_url;
    }
    
    $roundcube_base_url = $protocol . $host . $roundcube_base_url;
}

if (substr($roundcube_base_url, -1) !== '/') {
    $roundcube_base_url .= '/';
}

$roundcube_url = $roundcube_base_url;

// Ajouter l'autologin si les identifiants sont disponibles
$sql = "SELECT login_roundcube, password_roundcube FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$user->id;
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    if (!empty($obj->login_roundcube) && !empty($obj->password_roundcube)) {
        $separator = (strpos($roundcube_url, '?') === false) ? '?' : '&';
        $roundcube_url = $roundcube_url . $separator . '_user=' . urlencode($obj->login_roundcube) . '&_pass=' . urlencode($obj->password_roundcube);
    }
}

if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    print '<!-- Roundcube URL: ' . htmlspecialchars($roundcube_url) . ' -->';
}

// Inclure les CSS et JS (ajout du fichier de classement)
?>
<link rel="stylesheet" href="css/roundcube_bandeau.css">
<script src="js/roundcube_bandeau.js"></script>
<script src="js/mail_classification.js"></script>

<style>
/* Styles conservés et adaptés pour le bandeau de classement */
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
    width: 350px; /* Légèrement plus large pour le classement */
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
    margin-bottom: 5px;
}

.btn:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    text-decoration: none;
}

.btn-primary {
    background: rgba(76, 175, 80, 0.3);
    border-color: rgba(76, 175, 80, 0.5);
}

.btn-primary:hover {
    background: rgba(76, 175, 80, 0.5);
}

.btn-danger {
    background: rgba(244, 67, 54, 0.3);
    border-color: rgba(244, 67, 54, 0.5);
}

.btn-danger:hover {
    background: rgba(244, 67, 54, 0.5);
}

.no-mail-selected {
    text-align: center;
    padding: 30px;
    opacity: 0.7;
}

.no-mail-selected div:first-child {
    font-size: 32px;
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

#notification.warning {
    background: #ff9800;
}

/* Animation de chargement */
.loading-spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* MASQUER LE BOUTON SAVE2DOLIBARR EXISTANT */
.toolbar a.button.save2dolibarr,
.toolbar .button.save2dolibarr,
a.button.save2dolibarr,
.save2dolibarr {
    display: none !important;
}

#save2dolibarr_modal,
#save2dolibarr_overlay {
    display: none !important;
}

/* S'assurer que les éléments du bandeau sont visibles */
#bandeau .section {
    display: block !important;
}

#classification-container {
    display: block !important;
}

#classification-form {
    min-height: 100px;
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
    
    <!-- NOUVEAU BANDEAU DE CLASSEMENT -->
    <div id="bandeau">
        <!-- Section Utilisateur (conservée) -->
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
        
        <!-- SECTION CLASSEMENT (NOUVELLE - remplace "Mail sélectionné") -->
        <div class="section">
            <h3>📧 Classement du mail</h3>
            <div id="classification-container">
                <div id="classification-no-selection" class="no-mail-selected">
                    <div>📭</div>
                    <p style="font-size: 13px; opacity: 0.8;">Sélectionnez un mail pour le classer</p>
                </div>
                
                <div id="classification-form" style="display: none;">
                    <!-- Contenu dynamique géré par mail_classification.js -->
                </div>
            </div>
        </div>
        
        <!-- Section GED (nouvelle, vide pour l'instant) -->
        <div class="section">
            <h3>📁 GED</h3>
            <div style="text-align: center; padding: 20px; opacity: 0.7;">
                <div style="font-size: 32px; margin-bottom: 10px;">🚧</div>
                <p style="font-size: 13px;">Fonctionnalité à venir</p>
            </div>
        </div>
        
        <!-- Section Configuration (conservée mais simplifiée) -->
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
        
        <!-- Section Debug (conservée mais simplifiée) -->
        <?php if (!empty($conf->global->ROUNDCUBE_DEBUG)): ?>
        <div class="section">
            <h3>🧪 Debug</h3>
            <button class="btn" onclick="testMail()">🔧 Simuler mail</button>
            <button class="btn" onclick="showDebug()">📊 Debug complet</button>
            <div id="api-status" style="margin-top: 10px;">
                <div id="api-result" style="font-size: 11px; color: rgba(255,255,255,0.8);">En attente...</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Notification -->
<div id="notification"></div>

<script>
// Configuration PHP vers JavaScript (étendue pour le classement)
const CONFIG = {
    API_URL: '<?php echo dol_buildpath('/custom/roundcubemodule/mail_api_simple.php', 1); ?>',
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

// Variables globales (conservées)
let currentMailData = null;
let currentMailUID = null;
let currentMailId = null;

// Fonctions conservées du fichier original
function showDebug() {
    const debug = {
        config: CONFIG,
        currentMail: currentMailData,
        currentMailUID: currentMailUID,
        currentMailId: currentMailId,
        classificationState: mailClassificationState || null,
        timestamp: new Date().toISOString()
    };
    console.log('Debug Info:', debug);
    alert('Debug Info:\n' + JSON.stringify(debug, null, 2));
}

function testMail() {
    console.log('🧪 Test manuel d\'un mail...');
    
    // Test du système de classement
    console.log('🔧 Test du système de classement...');
    
    if (typeof initMailClassification === 'function') {
        console.log('✅ initMailClassification disponible');
        initMailClassification();
    } else {
        console.error('❌ initMailClassification non disponible');
    }
    
    const testData = {
        type: 'roundcube_mail_selected',
        data: {
            uid: 'test_' + Date.now(),
            message_id: '<test.' + Date.now() + '@example.com>',
            subject: 'Mail de test - Classement - ' + new Date().toLocaleTimeString(),
            from: 'Test User <test@example.com>',
            from_email: 'test@example.com',
            date: new Date().toISOString(),
            folder: 'INBOX',
            has_attachments: Math.random() > 0.5,
            is_read: false
        }
    };
    
    console.log('Envoi du mail de test:', testData);
    window.handleRoundcubeMessage({ data: testData });
}

function forceUpdateFromIframe() {
    console.log('🔄 Tentative de récupération forcée des données...');
    
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) {
        console.error('Iframe non trouvée');
        return;
    }
    
    try {
        iframe.contentWindow.postMessage({
            type: 'request_current_mail',
            timestamp: new Date().toISOString()
        }, '*');
        console.log('Message envoyé à l\'iframe');
    } catch (e) {
        console.error('Erreur envoi message:', e);
    }
}

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
            
            if (iframe.contentWindow.roundcubeDetectionActive) {
                status += '✅ Script de détection actif\n';
            } else {
                status += '❌ Script de détection non actif\n';
                status += '↳ Tentative de réinjection...\n';
                injectScript();
            }
            
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

// Fonction handleRoundcubeMessage modifiée pour intégrer le classement
window.handleRoundcubeMessage = function(e) {
    if (e.data && typeof e.data === 'object') {
        console.log('📨 Message window reçu:', e.data);
        
        if (e.data.type && e.data.type.includes('roundcube')) {
            // Traitement original (mise à jour des variables globales)
            if (e.data.type === 'roundcube_mail_selected' && e.data.data) {
                currentMailData = e.data.data;
                currentMailUID = e.data.data.uid;
                currentMailId = e.data.data.message_id;
                
                console.log('📧 Mail sélectionné:', e.data.data);
                
                // NOUVEAU: Déclencher le classement
                if (typeof updateMailClassification === 'function') {
                    updateMailClassification(e.data.data);
                }
            }
        }
    }
};

// Fonction showNotification pour l'interface
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;
    
    notification.className = 'show ' + type;
    notification.textContent = message;
    
    setTimeout(() => {
        notification.className = '';
    }, 4000);
}

// Gestion des erreurs de chargement de l'iframe (conservée)
function handleIframeError() {
    console.error('Erreur de chargement de Roundcube');
    document.getElementById('roundcube-error').style.display = 'block';
}

// Écouter les messages de l'iframe (conservé)
window.addEventListener('message', handleRoundcubeMessage);

// Initialisation (conservée et étendue)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Module Roundcube avec classement - Chargé');
    
    const iframe = document.getElementById('roundcube-iframe');
    
    function injectScript() {
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            
            if (iframeDoc) {
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
    
    iframe.onload = function() {
        console.log('Iframe Roundcube chargée');
        setTimeout(injectScript, 2000);
    };
    
    setInterval(function() {
        if (iframe.contentDocument || iframe.contentWindow) {
            injectScript();
        }
    }, 5000);
    
    if (CONFIG && CONFIG.DEBUG) {
        testAPI();
    }
});

// Script à injecter dans l'iframe Roundcube (conservé identique)
function getIframeDetectionScript() {
    return `
(function() {
    console.log('🔍 Script de détection Roundcube activé - Version 2.0');
    
    if (window.roundcubeDetectionActive) {
        console.log('Script déjà actif, skip');
        return;
    }
    window.roundcubeDetectionActive = true;
    
    let currentMailData = null;
    let lastUID = null;
    
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
            if (window.rcmail && window.rcmail.env) {
                console.log('API Roundcube détectée');
                if (window.rcmail.env.uid) {
                    mailData.uid = String(window.rcmail.env.uid);
                    mailData.folder = window.rcmail.env.mailbox || 'INBOX';
                    
                    if (window.rcmail.env.subject) {
                        mailData.subject = window.rcmail.env.subject;
                    }
                    
                    console.log('UID détecté via API:', mailData.uid);
                }
            }
            
            const messageHeader = document.querySelector('#messageheader, .message-header, .messageheader, #message-header');
            if (messageHeader) {
                console.log('Header du message trouvé');
                
                const subjectEl = messageHeader.querySelector('.subject, [class*="subject"], #message-subject');
                if (subjectEl) {
                    mailData.subject = subjectEl.textContent.trim();
                    console.log('Sujet trouvé:', mailData.subject);
                }
                
                const fromEl = messageHeader.querySelector('.from, [class*="from"], #message-from');
                if (fromEl) {
                    mailData.from = fromEl.textContent.trim();
                    const emailMatch = mailData.from.match(/<([^>]+)>/) || mailData.from.match(/([^\\s]+@[^\\s]+)/);
                    if (emailMatch) {
                        mailData.from_email = emailMatch[1];
                    }
                    console.log('Expéditeur trouvé:', mailData.from_email);
                }
                
                const dateEl = messageHeader.querySelector('.date, [class*="date"], #message-date');
                if (dateEl) {
                    mailData.date = dateEl.textContent.trim();
                }
                
                const messageIdEl = messageHeader.querySelector('[class*="message-id"]');
                if (messageIdEl) {
                    mailData.message_id = messageIdEl.textContent.trim();
                }
            }
            
            const selectedMessage = document.querySelector(
                '.messagelist .selected, ' +
                '#messagelist .selected, ' +
                'tr.selected, ' +
                '.message-list .selected, ' +
                '[id^="rcmrow"].selected'
            );
            
            if (selectedMessage) {
                console.log('Message sélectionné dans la liste trouvé');
                
                if (selectedMessage.id && !mailData.uid) {
                    const uidMatch = selectedMessage.id.match(/\\d+/);
                    if (uidMatch) {
                        mailData.uid = uidMatch[0];
                        console.log('UID extrait de l\'ID:', mailData.uid);
                    }
                }
                
                if (!mailData.subject) {
                    const subjectCell = selectedMessage.querySelector('.subject, td.subject');
                    if (subjectCell) {
                        mailData.subject = subjectCell.textContent.trim();
                    }
                }
                
                if (!mailData.from) {
                    const fromCell = selectedMessage.querySelector('.from, td.from');
                    if (fromCell) {
                        mailData.from = fromCell.textContent.trim();
                    }
                }
                
                if (!mailData.date) {
                    const dateCell = selectedMessage.querySelector('.date, td.date');
                    if (dateCell) {
                        mailData.date = dateCell.textContent.trim();
                    }
                }
                
                const attachIcon = selectedMessage.querySelector('.attachment, .icon.attachment');
                mailData.has_attachments = !!attachIcon;
                
                mailData.is_read = !selectedMessage.classList.contains('unread');
            }
            
            const messageContent = document.querySelector('#messagecontent, .messagecontent, #message-content');
            if (messageContent && !mailData.subject) {
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
    
    function sendMailData(mailData) {
        if (!mailData.uid && !mailData.subject) {
            console.log('Pas de données à envoyer');
            return;
        }
        
        if (mailData.uid && mailData.uid === lastUID) {
            console.log('Mail déjà envoyé, skip');
            return;
        }
        
        lastUID = mailData.uid;
        mailData.timestamp = new Date().toISOString();
        
        console.log('📧 Envoi des données du mail vers le parent:', mailData);
        
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'roundcube_mail_selected',
                    data: mailData
                }, '*');
                console.log('Message envoyé via parent.postMessage');
            }
            
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
    
    function initObserver() {
        const observer = new MutationObserver((mutations) => {
            clearTimeout(window.extractTimeout);
            window.extractTimeout = setTimeout(() => {
                const mailData = extractMailData();
                if (mailData.uid || mailData.subject) {
                    sendMailData(mailData);
                }
            }, 300);
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'id']
        });
        
        console.log('Observer initialisé');
    }
    
    document.addEventListener('click', function(e) {
        console.log('Clic détecté sur:', e.target);
        
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    }, true);
    
    window.addEventListener('hashchange', function() {
        console.log('Changement de hash détecté');
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    });
    
    setInterval(() => {
        const mailData = extractMailData();
        if (mailData.uid || mailData.subject) {
            const dataString = JSON.stringify(mailData);
            if (dataString !== currentMailData) {
                currentMailData = dataString;
                sendMailData(mailData);
            }
        }
    }, 2000);
    
    if (window.rcmail) {
        const originalCommand = window.rcmail.command_handler;
        window.rcmail.command_handler = function(command, props, obj, event) {
            console.log('Commande Roundcube:', command);
            
            const result = originalCommand.apply(this, arguments);
            
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
    
    initObserver();
    
    setTimeout(() => {
        const mailData = extractMailData();
        if (mailData.uid || mailData.subject) {
            sendMailData(mailData);
        }
    }, 1000);
    
    window.parent.postMessage({
        type: 'roundcube_detection_ready',
        message: 'Détection activée v2.0'
    }, '*');
    
    console.log('✅ Détection Roundcube initialisée avec succès');
})();
    `;
}

function testAPI() {
    console.log('🧪 Test de l\'API...');
    
    fetch(CONFIG.API_URL + '?test=1')
        .then(response => response.json())
        .then(data => {
            console.log('✅ API fonctionnelle:', data);
            const apiResult = document.getElementById('api-result');
            if (apiResult) {
                apiResult.innerHTML = `<span style="color: green;">✅ API OK</span>`;
            }
        })
        .catch(error => {
            console.error('❌ API non accessible:', error);
            const apiResult = document.getElementById('api-result');
            if (apiResult) {
                apiResult.innerHTML = '<span style="color: red;">❌ API Error</span>';
            }
        });
}
</script>

<?php
llxFooter();
?>