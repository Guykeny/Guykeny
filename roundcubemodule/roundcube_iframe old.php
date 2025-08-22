<?php
/**
 * Iframe pour intégrer Roundcube dans le module
 * Ce fichier affiche Roundcube et injecte le JavaScript de détection
 * Version améliorée avec bandeau d'information des modules liés
 * 
 * @package    RoundcubeModule
 * @version    3.0.0
 */

// Charger Dolibarr pour accès à la base de données
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include("../../main.inc.php");
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include("../../../main.inc.php");
}
if (!$res) {
    die("Erreur: Impossible de charger l'environnement Dolibarr");
}

global $db, $conf, $langs;

// Configuration de l'URL Roundcube
// IMPORTANT : Adaptez ces URLs selon votre installation Roundcube

// Option 1 : Roundcube installé dans le même serveur
$roundcube_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube/';

// Option 2 : Roundcube dans un sous-dossier
// $roundcube_url = '/webmail/';

// Option 3 : Roundcube sur un autre port
// $roundcube_url = 'http://localhost:8080/roundcube/';

// Option 4 : URL complète externe
// $roundcube_url = 'https://webmail.votredomaine.com/';

// Option 5 : Roundcube dans WAMP/XAMPP
// $roundcube_url = '/roundcubemail/';

// Vérifier si une URL est passée en paramètre (pour tests)
if (isset($_GET['url'])) {
    $roundcube_url = $_GET['url'];
}

// Si l'URL ne commence pas par http, ajouter le protocole et le host
if (!preg_match('/^https?:\/\//', $roundcube_url)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $roundcube_url = $protocol . $host . $roundcube_url;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Roundcube Webmail</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        #roundcube-frame {
            width: 100%;
            height: calc(100% - 50px); /* Réduire pour faire place au bandeau */
            border: none;
        }
        
        /* Bandeau d'information des modules */
        #module-info-banner {
            display: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            height: 50px;
            box-sizing: border-box;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        #module-info-banner.show {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        #module-info-banner .mail-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 0;
        }
        
        #module-info-banner .mail-subject {
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
        
        #module-info-banner .mail-date {
            font-size: 12px;
            opacity: 0.9;
            white-space: nowrap;
        }
        
        #module-info-banner .modules-list {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        #module-info-banner .module-tag {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        #module-info-banner .module-tag:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        #module-info-banner .module-icon {
            width: 16px;
            height: 16px;
            display: inline-block;
        }
        
        #module-info-banner .no-module {
            font-size: 13px;
            font-style: italic;
            opacity: 0.8;
        }
        
        #module-info-banner .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        #module-info-banner .btn-action {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        #module-info-banner .btn-action:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Loader pendant le chargement */
        #loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            font-family: Arial, sans-serif;
            color: #666;
            z-index: 1000;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Bandeau d'erreur si Roundcube n'est pas accessible */
        #error-banner {
            display: none;
            background: #f44336;
            color: white;
            padding: 10px;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        
        #error-banner.show {
            display: block;
        }
        
        /* Panel de configuration */
        #config-panel {
            position: absolute;
            top: 60px;
            right: 10px;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            display: none;
            z-index: 1000;
            max-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        #config-panel.show {
            display: block;
        }
        
        #config-panel h4 {
            margin: 0 0 10px 0;
        }
        
        #config-panel input {
            width: 100%;
            padding: 5px;
            margin: 5px 0;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        #config-panel button {
            background: #667eea;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            transition: background 0.3s ease;
        }
        
        #config-panel button:hover {
            background: #5a67d8;
        }
        
        /* Bouton de configuration */
        #config-toggle {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            z-index: 999;
            font-family: Arial, sans-serif;
            font-size: 12px;
            transition: background 0.3s ease;
        }
        
        #config-toggle:hover {
            background: #5a67d8;
        }
        
        /* Animation de pulse pour nouveaux modules */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
            }
        }
        
        .module-tag.new {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <!-- Bandeau d'information des modules -->
    <div id="module-info-banner">
        <div class="mail-info">
            <span class="mail-subject" id="mail-subject-display">Aucun mail sélectionné</span>
            <span class="mail-date" id="mail-date-display"></span>
        </div>
        <div class="modules-list" id="modules-list">
            <span class="no-module">Aucun module lié</span>
        </div>
        <div class="action-buttons">
            <button class="btn-action" onclick="linkToModule()">📎 Lier à un module</button>
            <button class="btn-action" onclick="refreshModules()">🔄 Actualiser</button>
        </div>
    </div>
    
    <!-- Bandeau d'erreur -->
    <div id="error-banner">
        ⚠️ Roundcube n'est pas accessible à l'URL configurée. 
        <button onclick="showConfig()">Configurer</button>
    </div>
    
    <!-- Loader -->
    <div id="loader">
        <div class="spinner"></div>
        <div>Chargement de Roundcube...</div>
        <div style="margin-top: 10px; font-size: 11px; color: #999;">
            URL: <?php echo htmlspecialchars($roundcube_url); ?>
        </div>
    </div>
    
    <!-- Bouton de configuration -->
    <button id="config-toggle" onclick="toggleConfig()">⚙️ Config</button>
    
    <!-- Panel de configuration -->
    <div id="config-panel">
        <h4>Configuration Roundcube</h4>
        <label>URL Roundcube :</label>
        <input type="text" id="roundcube-url-input" value="<?php echo htmlspecialchars($roundcube_url); ?>">
        <br><br>
        <button onclick="testUrl()">Tester</button>
        <button onclick="saveUrl()">Appliquer</button>
        <button onclick="hideConfig()">Fermer</button>
        <br><br>
        <div style="font-size: 11px; opacity: 0.8;">
            Exemples :<br>
            • /roundcube/<br>
            • http://localhost:8080/roundcube/<br>
            • https://webmail.domain.com/
        </div>
    </div>
    
    <!-- Iframe Roundcube -->
    <iframe id="roundcube-frame" src="<?php echo htmlspecialchars($roundcube_url); ?>"></iframe>
    
    <script>
    // Configuration
    let currentUrl = '<?php echo $roundcube_url; ?>';
    let currentMailId = null;
    let currentMailUID = null;
    
    // Gestion du loader et des erreurs
    const iframe = document.getElementById('roundcube-frame');
    const loader = document.getElementById('loader');
    const errorBanner = document.getElementById('error-banner');
    const configPanel = document.getElementById('config-panel');
    const moduleInfoBanner = document.getElementById('module-info-banner');
    
    // Masquer le loader quand l'iframe est chargée
    iframe.onload = function() {
        loader.style.display = 'none';
        console.log('✅ Roundcube iframe chargée');
        
        // Essayer d'injecter le script de détection
        injectDetectionScript();
    };
    
    // Gérer les erreurs de chargement
    iframe.onerror = function() {
        loader.style.display = 'none';
        errorBanner.classList.add('show');
        console.error('❌ Erreur de chargement de Roundcube');
    };
    
    // Timeout si le chargement est trop long
    setTimeout(function() {
        if (loader.style.display !== 'none') {
            loader.innerHTML = '<div style="color: #f44336;">⚠️ Le chargement prend trop de temps...</div>' +
                              '<button onclick="location.reload()">Recharger</button> ' +
                              '<button onclick="showConfig()">Configurer URL</button>';
        }
    }, 10000); // 10 secondes
    
    // Fonction pour injecter le script de détection dans l'iframe
    function injectDetectionScript() {
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            
            // Vérifier l'accès à l'iframe
            if (!iframeDoc) {
                console.warn('⚠️ Impossible d\'accéder au contenu de l\'iframe (cross-origin)');
                return;
            }
            
            // Créer et injecter le script de détection
            const script = iframeDoc.createElement('script');
            script.textContent = `
                console.log('🔍 Script de détection Roundcube injecté');
                
                // Détection des clics sur les mails
                document.addEventListener('click', function(e) {
                    // Chercher si on a cliqué sur un élément de mail
                    let target = e.target;
                    while (target && target !== document.body) {
                        if (target.classList && (
                            target.classList.contains('message') ||
                            target.classList.contains('mail') ||
                            target.id && target.id.includes('rcmrow')
                        )) {
                            // Mail trouvé, extraire les infos
                            const mailData = {
                                element: target.outerHTML.substring(0, 200),
                                classes: target.className,
                                id: target.id,
                                timestamp: new Date().toISOString(),
                                // Essayer d'extraire l'UID du mail
                                uid: target.id ? target.id.replace(/[^0-9]/g, '') : null,
                                // Essayer d'extraire le sujet
                                subject: target.querySelector('.subject') ? target.querySelector('.subject').textContent : 'Mail sélectionné'
                            };
                            
                            // Envoyer au parent
                            window.parent.postMessage({
                                type: 'roundcube_mail_selected',
                                data: mailData
                            }, '*');
                            
                            console.log('📧 Mail sélectionné détecté:', mailData);
                            break;
                        }
                        target = target.parentElement;
                    }
                });
                
                // Notification de succès
                window.parent.postMessage({
                    type: 'roundcube_detection_ready',
                    message: 'Détection activée'
                }, '*');
            `;
            
            iframeDoc.head.appendChild(script);
            console.log('✅ Script de détection injecté avec succès');
            
        } catch (error) {
            console.warn('⚠️ Impossible d\'injecter le script de détection:', error.message);
            console.log('ℹ️ Mode fallback : détection limitée activée');
        }
    }
    
    // Fonction pour charger les modules liés à un mail
    function loadMailModules(uid) {
        if (!uid) return;
        
        currentMailUID = uid;
        
        // Appel AJAX pour récupérer les modules liés
        fetch('<?php echo DOL_URL_ROOT; ?>/custom/roundcubemodule/ajax/get_mail_modules.php?uid=' + uid)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'OK' && data.modules) {
                    displayModules(data.modules, data.mail_info);
                }
            })
            .catch(error => {
                console.error('Erreur chargement modules:', error);
            });
    }
    
    // Fonction pour afficher les modules dans le bandeau
    function displayModules(modules, mailInfo) {
        const modulesList = document.getElementById('modules-list');
        const mailSubject = document.getElementById('mail-subject-display');
        const mailDate = document.getElementById('mail-date-display');
        
        // Afficher les infos du mail
        if (mailInfo) {
            mailSubject.textContent = mailInfo.subject || 'Sans sujet';
            mailSubject.title = mailInfo.subject || 'Sans sujet';
            
            if (mailInfo.date) {
                const date = new Date(mailInfo.date);
                mailDate.textContent = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
            }
            
            currentMailId = mailInfo.id;
        }
        
        // Afficher les modules
        if (modules && modules.length > 0) {
            modulesList.innerHTML = '';
            
            modules.forEach(module => {
                const moduleTag = document.createElement('a');
                moduleTag.className = 'module-tag';
                moduleTag.href = getModuleUrl(module.type, module.id);
                moduleTag.target = '_blank';
                
                // Ajouter une icône selon le type
                const icon = getModuleIcon(module.type);
                moduleTag.innerHTML = `${icon} ${module.type} #${module.id}`;
                
                if (module.name) {
                    moduleTag.title = module.name;
                    moduleTag.innerHTML = `${icon} ${module.name}`;
                }
                
                modulesList.appendChild(moduleTag);
            });
        } else {
            modulesList.innerHTML = '<span class="no-module">Aucun module lié</span>';
        }
        
        // Afficher le bandeau
        moduleInfoBanner.classList.add('show');
        
        // Ajuster la hauteur de l'iframe
        iframe.style.height = 'calc(100% - 50px)';
    }
    
    // Fonction pour obtenir l'URL d'un module
    function getModuleUrl(type, id) {
        const baseUrl = '<?php echo DOL_URL_ROOT; ?>';
        
        switch(type) {
            case 'thirdparty':
            case 'societe':
                return baseUrl + '/societe/card.php?id=' + id;
            case 'contact':
                return baseUrl + '/contact/card.php?id=' + id;
            case 'project':
                return baseUrl + '/projet/card.php?id=' + id;
            case 'propal':
                return baseUrl + '/comm/propal/card.php?id=' + id;
            case 'commande':
                return baseUrl + '/commande/card.php?id=' + id;
            case 'invoice':
            case 'facture':
                return baseUrl + '/compta/facture/card.php?id=' + id;
            case 'contract':
                return baseUrl + '/contrat/card.php?id=' + id;
            case 'ticket':
                return baseUrl + '/ticket/card.php?id=' + id;
            case 'fichinter':
                return baseUrl + '/fichinter/card.php?id=' + id;
            case 'expedition':
                return baseUrl + '/expedition/card.php?id=' + id;
            default:
                return '#';
        }
    }
    
    // Fonction pour obtenir l'icône d'un module
    function getModuleIcon(type) {
        switch(type) {
            case 'thirdparty':
            case 'societe':
                return '🏢';
            case 'contact':
                return '👤';
            case 'project':
                return '📁';
            case 'propal':
                return '📄';
            case 'commande':
                return '📦';
            case 'invoice':
            case 'facture':
                return '💰';
            case 'contract':
                return '📜';
            case 'ticket':
                return '🎫';
            case 'fichinter':
                return '🔧';
            case 'expedition':
                return '🚚';
            default:
                return '📌';
        }
    }
    
    // Fonction pour lier le mail à un module
    function linkToModule() {
        if (!currentMailId) {
            alert('Veuillez d\'abord sélectionner un mail');
            return;
        }
        
        // Ouvrir une popup pour sélectionner le module
        const url = '<?php echo DOL_URL_ROOT; ?>/custom/roundcubemodule/link_mail.php?mail_id=' + currentMailId;
        window.open(url, 'LinkMail', 'width=800,height=600,scrollbars=yes');
    }
    
    // Fonction pour rafraîchir les modules
    function refreshModules() {
        if (currentMailUID) {
            loadMailModules(currentMailUID);
        }
    }
    
    // Écouter les messages de l'iframe
    window.addEventListener('message', function(event) {
        // Vérifier l'origine si nécessaire
        // if (event.origin !== 'http://localhost') return;
        
        if (event.data.type === 'roundcube_mail_selected') {
            console.log('📧 Mail sélectionné reçu:', event.data.data);
            
            // Charger les modules pour ce mail
            if (event.data.data.uid) {
                loadMailModules(event.data.data.uid);
            }
            
            // Envoyer au parent (module Dolibarr) si nécessaire
            if (window.parent !== window) {
                window.parent.postMessage(event.data, '*');
            }
        } else if (event.data.type === 'roundcube_detection_ready') {
            console.log('✅ Détection prête:', event.data.message);
        } else if (event.data.type === 'modules_updated') {
            // Rafraîchir si les modules ont été mis à jour
            refreshModules();
        }
    });
    
    // Fonctions de configuration
    function toggleConfig() {
        configPanel.classList.toggle('show');
    }
    
    function showConfig() {
        configPanel.classList.add('show');
    }
    
    function hideConfig() {
        configPanel.classList.remove('show');
    }
    
    function testUrl() {
        const url = document.getElementById('roundcube-url-input').value;
        window.open(url, '_blank');
    }
    
    function saveUrl() {
        const url = document.getElementById('roundcube-url-input').value;
        if (url) {
            // Recharger avec la nouvelle URL
            window.location.href = '?url=' + encodeURIComponent(url);
        }
    }
    
    // Log de démarrage
    console.log('🚀 Roundcube iframe module chargé avec bandeau modules');
    console.log('🔍 URL Roundcube:', currentUrl);
    </script>
</body>
</html>