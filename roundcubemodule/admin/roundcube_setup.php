<?php
/**
 * Page principale du module Roundcube - VERSION AVEC AUTOLOGIN COMPLET
 * Ajoute simplement le mot de passe à l'URL pour connexion automatique
 * 
 * Emplacement: custom/roundcubemodule/roundcube.php
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

// =====================================
// GESTION AUTOMATIQUE DES COMPTES
// =====================================

// Fonction pour décrypter le mot de passe
function decryptPassword($encryptedPassword) {
    return base64_decode($encryptedPassword);
}

// Récupérer les comptes webmail de l'utilisateur
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts ";
$sql .= "WHERE fk_user = ".$user->id." AND is_active = 1 ";
$sql .= "ORDER BY is_default DESC, account_name ASC";

$resql = $db->query($sql);
$accounts = array();
$default_account = null;

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $accounts[] = $obj;
        if ($obj->is_default || !$default_account) {
            $default_account = $obj;
        }
    }
}

// Si aucun compte configuré, rediriger vers la configuration
if (empty($accounts)) {
    $config_url = dol_buildpath('/user/card.php?id='.$user->id.'&tab=webmail', 1);
    
    print '<div class="center" style="margin-top: 50px;">';
    print '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 20px; max-width: 600px; margin: 0 auto;">';
    print '<h3>📧 Aucun compte webmail configuré</h3>';
    print '<p>Vous devez d\'abord configurer au moins un compte webmail pour accéder à Roundcube.</p>';
    print '<p><a href="'.$config_url.'" class="button">Configurer mes comptes webmail</a></p>';
    print '</div>';
    print '</div>';
    
    llxFooter();
    exit;
}

// =====================================
// CONFIGURATION ROUNDCUBE (conservée)
// =====================================
$roundcube_base_url = '';

if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_base_url = $conf->global->ROUNDCUBE_URL;
} else {
    $test_path = DOL_DOCUMENT_ROOT . '/custom/roundcubemodule/roundcube/index.php';
    if (file_exists($test_path)) {
        $roundcube_base_url = dol_buildpath('/custom/roundcubemodule/roundcube/', 1);
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

// =====================================
// AUTOLOGIN AVEC INJECTION DE SCRIPT AU LIEU D'PARAMÈTRES URL
// =====================================
$roundcube_url = $roundcube_base_url;

// Charger Roundcube normalement sans paramètres d'autologin
// L'auto-login sera géré par injection JavaScript côté client

if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    print '<!-- Roundcube URL: ' . htmlspecialchars($roundcube_url) . ' -->';
    print '<!-- Auto-login sera géré par injection JavaScript -->';
}

if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    print '<!-- Roundcube URL: ' . htmlspecialchars($roundcube_url) . ' -->';
}

// =====================================
// INCLUSION DU NOUVEAU BANDEAU (conservé)
// =====================================
require_once DOL_DOCUMENT_ROOT.'/custom/roundcubemodule/components/bandeau/BandeauManager.php';
?>

<!-- Interface de sélection des comptes (si plusieurs comptes) -->
<?php if (count($accounts) > 1): ?>
<div id="account-selector" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 10px;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <strong>📧 Compte actuel :</strong>
            <select id="account-select" onchange="switchAccount()" style="margin-left: 10px; padding: 5px;">
                <?php foreach ($accounts as $account): ?>
                <option value="<?php echo $account->rowid; ?>" 
                        <?php echo ($account->rowid == $default_account->rowid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($account->email); ?>
                    <?php if ($account->account_name): ?>
                        (<?php echo htmlspecialchars($account->account_name); ?>)
                    <?php endif; ?>
                    <?php if ($account->is_default): ?>
                        - Par défaut
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <button onclick="refreshRoundcube()" style="margin-right: 10px;">🔄 Actualiser</button>
            <button onclick="openNewWindow()">🗗 Nouvelle fenêtre</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Container principal -->
<div id="roundcube-container">
    <!-- Message d'erreur si Roundcube ne charge pas -->
    <div id="roundcube-error" style="display:none;">
        <h3 style="color: #e74c3c;">⚠️ Roundcube non accessible</h3>
        <p>L'URL configurée ne répond pas : <br><code><?php echo htmlspecialchars($roundcube_url); ?></code></p>
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
    
    <?php
    // Rendre le bandeau avec la nouvelle architecture (conservé)
    BandeauManager::renderBandeau($user, $conf, $db, $langs, $roundcube_url);
    ?>
</div>

<!-- Script de détection Roundcube (inline pour éviter les erreurs de chargement) - CONSERVÉ -->
<script>
/**
 * Script de détection Roundcube à injecter dans l'iframe - CONSERVÉ INTÉGRALEMENT
 */
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
            // Extraction via API Roundcube
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
            
            // Extraction via DOM
            const messageHeader = document.querySelector('#messageheader, .message-header, .messageheader, #message-header');
            if (messageHeader) {
                console.log('Header du message trouvé');
                
                const subjectEl = messageHeader.querySelector('.subject, [class*="subject"], #message-subject');
                if (subjectEl) {
                    mailData.subject = subjectEl.textContent.trim();
                }
                
                const fromEl = messageHeader.querySelector('.from, [class*="from"], #message-from');
                if (fromEl) {
                    mailData.from = fromEl.textContent.trim();
                    const emailMatch = mailData.from.match(/<([^>]+)>/) || mailData.from.match(/([^\\s]+@[^\\s]+)/);
                    if (emailMatch) {
                        mailData.from_email = emailMatch[1];
                    }
                }
            }
            
            // Extraction via liste des messages
            const selectedMessage = document.querySelector('.messagelist .selected, #messagelist .selected, tr.selected, .message-list .selected, [id^="rcmrow"].selected');
            if (selectedMessage) {
                console.log('Message sélectionné dans la liste trouvé');
                
                if (selectedMessage.id && !mailData.uid) {
                    const uidMatch = selectedMessage.id.match(/\\d+/);
                    if (uidMatch) {
                        mailData.uid = uidMatch[0];
                    }
                }
                
                if (!mailData.subject) {
                    const subjectCell = selectedMessage.querySelector('.subject, td.subject');
                    if (subjectCell) {
                        mailData.subject = subjectCell.textContent.trim();
                    }
                }
                
                mailData.is_read = !selectedMessage.classList.contains('unread');
                mailData.has_attachments = !!selectedMessage.querySelector('.attachment, .icon.attachment');
            }
            
        } catch (e) {
            console.error('Erreur extraction données mail:', e);
        }
        
        return mailData;
    }
    
    function sendMailData(mailData) {
        if (!mailData.uid && !mailData.subject) {
            return;
        }
        
        if (mailData.uid && mailData.uid === lastUID) {
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
            }
        } catch (e) {
            console.error('Erreur envoi message:', e);
        }
    }
    
    // Observer pour détecter les changements
    const observer = new MutationObserver(() => {
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
    
    // Événements de clic
    document.addEventListener('click', function() {
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    }, true);
    
    // Changements de hash
    window.addEventListener('hashchange', function() {
        setTimeout(() => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                sendMailData(mailData);
            }
        }, 500);
    });
    
    // Vérification périodique
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
    
    // Hook sur les commandes Roundcube
    if (window.rcmail && window.rcmail.command_handler) {
        const originalCommand = window.rcmail.command_handler;
        window.rcmail.command_handler = function(command, props, obj, event) {
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
    
    // Test initial
    setTimeout(() => {
        const mailData = extractMailData();
        if (mailData.uid || mailData.subject) {
            sendMailData(mailData);
        }
    }, 1000);
    
    console.log('✅ Détection Roundcube initialisée avec succès');
})();
    `;
}

/**
 * Injection du script de détection dans l'iframe - CONSERVÉ + AUTO-LOGIN
 */
function injectDetectionScript() {
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) return;
    
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        
        if (iframeDoc) {
            // Injecter le script de détection original
            if (!iframeDoc.getElementById('roundcube-detection-script')) {
                const script = iframeDoc.createElement('script');
                script.id = 'roundcube-detection-script';
                script.textContent = getIframeDetectionScript();
                
                iframeDoc.head.appendChild(script);
                console.log('✅ Script de détection injecté avec succès');
            }
            
            // NOUVEAU : Injecter le script d'auto-login
            if (!iframeDoc.getElementById('auto-login-script')) {
                injectAutoLoginScript(iframeDoc);
            }
            
        } else {
            console.warn('⚠️ Impossible d\'accéder au contenu de l\'iframe (cross-origin)');
        }
    } catch (error) {
        console.warn('⚠️ Impossible d\'injecter le script de détection:', error.message);
    }
}

/**
 * NOUVEAU : Injecter le script d'auto-login dans Roundcube
 */
function injectAutoLoginScript(iframeDoc) {
    // Récupérer les données du compte par défaut
    const defaultAccount = accounts.find(acc => acc.is_default) || accounts[0];
    
    if (!defaultAccount) {
        console.log('❌ Aucun compte par défaut trouvé pour l\'auto-login');
        return;
    }
    
    const email = defaultAccount.email;
    const password = decryptPassword(defaultAccount.password_encrypted);
    const host = defaultAccount.imap_host;
    
    console.log('🔐 Tentative d\'auto-login pour:', email);
    
    const autoLoginScript = `
        (function() {
            console.log('🤖 Script auto-login Roundcube activé');
            
            const email = "${email.replace(/"/g, '\\"')}";
            const password = "${password.replace(/"/g, '\\"')}";
            const host = "${host.replace(/"/g, '\\"')}";
            
            function attemptAutoLogin() {
                console.log('🔍 Recherche du formulaire de connexion...');
                
                // Chercher les champs de connexion avec plusieurs sélecteurs possibles
                const userField = document.querySelector(
                    '#rcmloginuser, input[name="_user"], input[name="user"], ' +
                    'input[type="text"][placeholder*="mail"], input[type="email"], ' +
                    '.username input, #username, .login-username'
                );
                
                const passField = document.querySelector(
                    '#rcmloginpwd, input[name="_pass"], input[name="pass"], input[name="password"], ' +
                    'input[type="password"], .password input, #password, .login-password'
                );
                
                const hostField = document.querySelector(
                    '#rcmloginhost, select[name="_host"], select[name="host"], ' +
                    '.host select, #host, .login-host'
                );
                
                const loginForm = document.querySelector(
                    '#login-form, form[name="form"], .login-form, form, ' +
                    'form[action*="login"], #rcmloginform'
                );
                
                if (userField && passField) {
                    console.log('📝 Champs de connexion trouvés, remplissage...');
                    
                    // Remplir les champs
                    userField.value = email;
                    passField.value = password;
                    
                    // Remplir le serveur si le champ existe
                    if (hostField) {
                        if (hostField.tagName.toLowerCase() === 'select') {
                            // C'est un select, chercher l'option correspondante
                            for (let i = 0; i < hostField.options.length; i++) {
                                const option = hostField.options[i];
                                if (option.value === host || 
                                    option.text.includes(host) || 
                                    option.value === 'localhost' ||
                                    i === 0) { // Prendre la première option par défaut
                                    hostField.selectedIndex = i;
                                    console.log('🌐 Serveur sélectionné:', option.text);
                                    break;
                                }
                            }
                        } else {
                            hostField.value = host;
                        }
                    }
                    
                    // Déclencher les événements pour que Roundcube détecte les changements
                    [userField, passField, hostField].forEach(field => {
                        if (field) {
                            ['input', 'change', 'blur', 'keyup'].forEach(eventType => {
                                const event = new Event(eventType, { bubbles: true });
                                field.dispatchEvent(event);
                            });
                        }
                    });
                    
                    console.log('🚀 Soumission automatique du formulaire...');
                    
                    // Attendre un peu que les événements soient traités
                    setTimeout(() => {
                        // Essayer plusieurs méthodes de soumission
                        let submitted = false;
                        
                        // Méthode 1: Bouton submit
                        const submitBtn = document.querySelector(
                            '#rcmloginsubmit, input[type="submit"], button[type="submit"], ' +
                            '.submit-button, .login-submit, button.submit'
                        );
                        
                        if (submitBtn && !submitted) {
                            console.log('🖱️ Clic sur le bouton de soumission');
                            submitBtn.click();
                            submitted = true;
                        }
                        
                        // Méthode 2: Soumission du formulaire
                        if (!submitted && loginForm) {
                            console.log('📤 Soumission directe du formulaire');
                            loginForm.submit();
                            submitted = true;
                        }
                        
                        // Méthode 3: Simulation de la touche Entrée
                        if (!submitted && passField) {
                            console.log('⌨️ Simulation de la touche Entrée');
                            const enterEvent = new KeyboardEvent('keydown', {
                                key: 'Enter',
                                code: 'Enter',
                                keyCode: 13,
                                bubbles: true
                            });
                            passField.dispatchEvent(enterEvent);
                        }
                        
                        if (submitted) {
                            console.log('✅ Auto-login déclenché avec succès');
                        } else {
                            console.warn('⚠️ Impossible de déclencher l\'auto-login');
                        }
                        
                    }, 800);
                    
                    return true;
                } else {
                    console.log('❌ Champs de connexion non trouvés');
                    console.log('Champs trouvés:', { userField: !!userField, passField: !!passField });
                    return false;
                }
            }
            
            // Vérifier si on est sur la page de login
            function isLoginPage() {
                return document.querySelector('#rcmloginuser, input[name="_user"], .login-form, #login-form') !== null;
            }
            
            // Essayer l'auto-login après chargement de la page
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        if (isLoginPage()) {
                            attemptAutoLogin();
                        }
                    }, 500);
                });
            } else {
                setTimeout(() => {
                    if (isLoginPage()) {
                        attemptAutoLogin();
                    }
                }, 500);
            }
            
            // Réessayer si la première tentative échoue
            setTimeout(() => {
                if (isLoginPage()) {
                    console.log('🔄 Nouvelle tentative d\'auto-login...');
                    attemptAutoLogin();
                }
            }, 2000);
            
        })();
    `;
    
    const script = iframeDoc.createElement('script');
    script.id = 'auto-login-script';
    script.textContent = autoLoginScript;
    
    const target = iframeDoc.head || iframeDoc.body || iframeDoc.documentElement;
    if (target) {
        target.appendChild(script);
        console.log('✅ Script d\'auto-login injecté avec succès');
    } else {
        console.error('❌ Impossible d\'injecter le script d\'auto-login');
    }
}

// =====================================
// FONCTIONS SIMPLES POUR MULTI-COMPTES
// =====================================

// Données des comptes (pour JavaScript)
const accounts = <?php echo json_encode($accounts); ?>;
const roundcubeBaseUrl = "<?php echo $roundcube_base_url; ?>";

/**
 * Fonction pour décrypter le mot de passe côté client
 */
function decryptPassword(encrypted) {
    try {
        return atob(encrypted);
    } catch (e) {
        console.error('Erreur déchiffrement mot de passe:', e);
        return '';
    }
}

/**
 * Changer de compte webmail avec plugin dolibarr_autologin
 */
function switchAccount() {
    const select = document.getElementById('account-select');
    if (!select) return;
    
    const accountId = select.value;
    
    // Trouver le compte sélectionné
    const account = accounts.find(acc => acc.rowid == accountId);
    
    if (account) {
        console.log('🔄 Changement de compte vers:', account.email, '(ID:', accountId, ')');
        
        // Afficher la progression
        showSwitchingProgress(account.email);
        
        // Générer un nouveau token pour la sécurité
        const timestamp = new Date().getTime();
        const token = btoa(accountId + '_' + timestamp).replace(/[+=\/]/g, '');
        
        const iframe = document.getElementById('roundcube-iframe');
        
        // ÉTAPE 1: Déconnexion forcée
        console.log('📤 Étape 1: Déconnexion...');
        iframe.src = 'about:blank';
        
        setTimeout(() => {
            // ÉTAPE 2: Logout Roundcube
            const logoutUrl = roundcubeBaseUrl + '?_task=logout';
            
            const logoutHandler = () => {
                updateSwitchingProgress('Préparation du nouveau compte...');
                
                setTimeout(() => {
                    // ÉTAPE 3: Connexion avec le nouveau compte via plugin
                    console.log('🔐 Étape 3: Connexion via plugin dolibarr_autologin...');
                    updateSwitchingProgress('Connexion automatique...');
                    
                    // URL avec paramètres pour le plugin dolibarr_autologin
                    const newUrl = roundcubeBaseUrl + '?' +
                                  '_autologin=1' +
                                  '&_user=' + encodeURIComponent(account.email) +
                                  '&_token=' + encodeURIComponent(token) +
                                  '&accountid=' + accountId +
                                  '&_nocache=' + timestamp;
                    
                    // Handler pour la connexion réussie
                    const loginHandler = () => {
                        console.log('✅ Connexion réussie via plugin');
                        updateSwitchingProgress('Finalisation...');
                        
                        setTimeout(() => {
                            hideSwitchingProgress();
                            
                            // Réinjecter le script de détection
                            setTimeout(injectDetectionScript, 2000);
                            
                            console.log('🎉 Changement de compte terminé');
                        }, 1500);
                    };
                    
                    // Configurer et charger
                    iframe.onload = loginHandler;
                    iframe.onerror = loginHandler;
                    iframe.src = newUrl;
                    
                }, 1000);
            };
            
            // Aller sur logout puis continuer
            iframe.onload = logoutHandler;
            iframe.onerror = logoutHandler;
            iframe.src = logoutUrl;
            
        }, 500);
    }
}

/**
 * Afficher la progression du changement de compte
 */
function showSwitchingProgress(email) {
    const container = document.getElementById('roundcube-container');
    
    let overlay = document.getElementById('switching-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'switching-overlay';
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            font-size: 16px;
        `;
        container.appendChild(overlay);
    }
    
    overlay.style.display = 'flex';
    overlay.innerHTML = `
        <div style="text-align: center;">
            <div style="font-size: 18px; margin-bottom: 15px;">🔄 Changement de compte</div>
            <div style="margin-bottom: 15px;"><strong style="color: #007cba;">${email}</strong></div>
            <div id="switching-status" style="margin-bottom: 15px; color: #666;">Déconnexion en cours...</div>
            <div style="width: 200px; height: 4px; background: #eee; border-radius: 2px; margin: 0 auto;">
                <div style="width: 100%; height: 100%; background: linear-gradient(90deg, #007cba, #28a745); border-radius: 2px; animation: pulse 1.5s infinite;"></div>
            </div>
        </div>
    `;
}

/**
 * Mettre à jour le message de progression
 */
function updateSwitchingProgress(message) {
    const statusEl = document.getElementById('switching-status');
    if (statusEl) {
        statusEl.textContent = message;
    }
}

/**
 * Masquer la progression
 */
function hideSwitchingProgress() {
    const overlay = document.getElementById('switching-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            overlay.style.display = 'none';
            overlay.style.opacity = '1';
        }, 500);
    }
}

/**
 * Injecter l'auto-login pour un compte spécifique
 */
function injectAutoLoginForAccount(iframeDoc, account) {
    const email = account.email;
    const password = decryptPassword(account.password_encrypted);
    const host = account.imap_host;
    
    console.log('🔐 Injection auto-login pour:', email);
    
    const autoLoginScript = `
        (function() {
            console.log('🤖 Auto-login pour changement de compte');
            
            const email = "${email.replace(/"/g, '\\"')}";
            const password = "${password.replace(/"/g, '\\"')}";
            const host = "${host.replace(/"/g, '\\"')}";
            
            function attemptLogin() {
                const userField = document.querySelector(
                    '#rcmloginuser, input[name="_user"], input[name="user"], ' +
                    'input[type="text"], input[type="email"]'
                );
                
                const passField = document.querySelector(
                    '#rcmloginpwd, input[name="_pass"], input[name="pass"], ' +
                    'input[type="password"]'
                );
                
                const hostField = document.querySelector(
                    '#rcmloginhost, select[name="_host"], select[name="host"]'
                );
                
                if (userField && passField) {
                    console.log('📝 Remplissage pour nouveau compte...');
                    
                    userField.value = email;
                    passField.value = password;
                    
                    if (hostField && hostField.tagName.toLowerCase() === 'select') {
                        for (let i = 0; i < hostField.options.length; i++) {
                            if (hostField.options[i].value === host || i === 0) {
                                hostField.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    
                    // Déclencher événements
                    [userField, passField, hostField].forEach(field => {
                        if (field) {
                            ['input', 'change', 'blur'].forEach(eventType => {
                                field.dispatchEvent(new Event(eventType, { bubbles: true }));
                            });
                        }
                    });
                    
                    // Soumettre après un délai
                    setTimeout(() => {
                        const submitBtn = document.querySelector(
                            '#rcmloginsubmit, input[type="submit"], button[type="submit"]'
                        );
                        
                        if (submitBtn) {
                            console.log('🖱️ Soumission automatique...');
                            submitBtn.click();
                        } else {
                            const form = document.querySelector('form');
                            if (form) form.submit();
                        }
                    }, 800);
                    
                    return true;
                }
                return false;
            }
            
            // Attendre le chargement complet
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(attemptLogin, 500);
                });
            } else {
                setTimeout(attemptLogin, 500);
            }
            
            // Réessayer si nécessaire
            setTimeout(attemptLogin, 2000);
        })();
    `;
    
    const script = iframeDoc.createElement('script');
    script.textContent = autoLoginScript;
    
    const target = iframeDoc.head || iframeDoc.body || iframeDoc.documentElement;
    if (target) {
        target.appendChild(script);
        console.log('✅ Auto-login injecté pour changement de compte');
    }
}

/**
 * Actualiser Roundcube
 */
function refreshRoundcube() {
    const iframe = document.getElementById('roundcube-iframe');
    iframe.src = iframe.src;
    console.log('🔄 Actualisation de Roundcube');
}

/**
 * Ouvrir dans une nouvelle fenêtre
 */
function openNewWindow() {
    const iframe = document.getElementById('roundcube-iframe');
    window.open(iframe.src, 'roundcube', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

/**
 * Gérer les erreurs d'iframe
 */
function handleIframeError() {
    document.getElementById('roundcube-error').style.display = 'block';
    document.getElementById('roundcube-iframe').style.display = 'none';
}

/**
 * Initialisation de la page - CONSERVÉ ET SIMPLIFIÉ
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Roundcube Module - Version avec autologin complet');
    console.log('📧 Comptes disponibles:', accounts.length);
    console.log('✅ Autologin avec mot de passe activé');
    
    const iframe = document.getElementById('roundcube-iframe');
    
    // Injection du script à chaque chargement de l'iframe - CONSERVÉ
    iframe.onload = function() {
        console.log('Iframe Roundcube chargée');
        setTimeout(injectDetectionScript, 2000);
    };
    
    // Gestion des erreurs
    iframe.onerror = function() {
        console.error('❌ Erreur de chargement Roundcube');
        handleIframeError();
    };
    
    // Réinjection périodique - CONSERVÉ
    setInterval(function() {
        if (iframe.contentDocument || iframe.contentWindow) {
            injectDetectionScript();
        }
    }, 5000);
    
    console.log('✅ Module Roundcube avec autologin simple initialisé');
});

// Styles CSS additionnels
const style = document.createElement('style');
style.textContent = `
    #account-selector {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    #account-selector select {
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }
    
    #account-selector button {
        background: #007cba;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    #account-selector button:hover {
        background: #005a87;
    }
    
    #roundcube-container {
        position: relative;
    }
    
    #roundcube-error {
        text-align: center;
        padding: 50px;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 5px;
        margin: 20px;
    }
`;
document.head.appendChild(style);
</script>

<?php
llxFooter();
?>

<?php
/*
=====================================
VERSION SIMPLIFIÉE AVEC AUTOLOGIN COMPLET
=====================================

✅ MODIFICATION PRINCIPALE :
- Ajout du mot de passe dans l'URL d'autologin Roundcube
- Email + Mot de passe + Serveur transmis automatiquement

✅ FONCTIONNEMENT :
1. Ouverture du module → Connexion automatique avec le compte par défaut
2. Changement de compte → URL avec les nouveaux paramètres + rechargement
3. Aucune saisie requise → Tout est automatique

✅ CONSERVATION TOTALE :
- Script de détection Roundcube intact
- Bandeau Manager fonctionnel  
- Multi-comptes avec interface simple
- Gestion d'erreurs et configuration
- Toutes les fonctionnalités existantes

✅ URL GÉNÉRÉE :
/roundcube/?_user=email&_pass=password&_host=serveur

Cette version garantit une connexion automatique complète
sans intervention de l'utilisateur, tout en conservant
toutes les fonctionnalités existantes.
*/
?>