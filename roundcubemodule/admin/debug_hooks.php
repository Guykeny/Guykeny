<?php
/**
 * Outil de debug pour vérifier les hooks du module Roundcube
 * À placer dans /custom/roundcubemodule/admin/debug_hooks.php
 */

// Load Dolibarr environment
$res = 0;
$paths = [
    dirname(dirname(dirname(__DIR__))).'/main.inc.php',
    '../../../main.inc.php',
    '../../main.inc.php',
    '../main.inc.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $res = @include $path;
        break;
    }
}

if (!$res) {
    die("Erreur : Impossible de charger main.inc.php.");
}

// Vérifier les droits d'administration
if (!$user->hasRight('roundcubemodule', 'config', 'write')) {
    accessforbidden('Vous n\'avez pas les droits pour déboguer les hooks');
}

llxHeader('', 'Debug Hooks Roundcube', '');

print '<h1>🔍 Debug Hooks Module Roundcube</h1>';

// 1. Vérifier si le module est activé
print '<h2>📋 État du module</h2>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Paramètre</td><td>Valeur</td><td>Statut</td></tr>';

$module_active = !empty($conf->global->MAIN_MODULE_ROUNDCUBEMODULE);
print '<tr><td>Module activé</td><td>'.($module_active ? 'Oui' : 'Non').'</td><td>';
print $module_active ? '<span class="badge badge-status4">✅ OK</span>' : '<span class="badge badge-status8">❌ KO</span>';
print '</td></tr>';

// 2. Vérifier les droits de l'utilisateur
print '<tr><td>Droits webmail.read</td><td>'.($user->hasRight('roundcubemodule', 'webmail', 'read') ? 'Oui' : 'Non').'</td><td>';
print $user->hasRight('roundcubemodule', 'webmail', 'read') ? '<span class="badge badge-status4">✅ OK</span>' : '<span class="badge badge-status8">❌ KO</span>';
print '</td></tr>';

print '<tr><td>Droits admin.write</td><td>'.($user->hasRight('roundcubemodule', 'admin', 'write') ? 'Oui' : 'Non').'</td><td>';
print $user->hasRight('roundcubemodule', 'admin', 'write') ? '<span class="badge badge-status4">✅ OK</span>' : '<span class="badge badge-status6">ℹ️ Optionnel</span>';
print '</td></tr>';

print '</table>';

// 3. Vérifier les fichiers hooks
print '<h2>📁 Fichiers hooks</h2>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Fichier</td><td>Existence</td><td>Taille</td></tr>';

$hook_file = dol_buildpath('/custom/roundcubemodule/core/hooks/roundcube_hooks.class.php', 0);
$hook_exists = file_exists($hook_file);
print '<tr><td>roundcube_hooks.class.php</td><td>';
print $hook_exists ? '✅ Existe' : '❌ Manquant';
print '</td><td>';
print $hook_exists ? number_format(filesize($hook_file)) . ' octets' : 'N/A';
print '</td></tr>';

print '</table>';

// 4. Vérifier les hooks déclarés
print '<h2>🔗 Hooks déclarés</h2>';
if ($hook_exists) {
    include_once $hook_file;
    
    if (class_exists('ActionsRoundcube')) {
        print '<div class="ok">✅ Classe ActionsRoundcube trouvée</div>';
        
        $reflection = new ReflectionClass('ActionsRoundcube');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><td>Méthode hook</td><td>Description</td></tr>';
        
        foreach ($methods as $method) {
            if ($method->name != '__construct') {
                print '<tr><td>'.$method->name.'</td><td>';
                switch ($method->name) {
                    case 'completeTabsHead':
                        print 'Ajoute des onglets aux fiches (principal)';
                        break;
                    case 'addMoreTabs':
                        print 'Ajoute des onglets (alternatif)';
                        break;
                    case 'addMoreActionsButtons':
                        print 'Ajoute des boutons d\'action';
                        break;
                    case 'printTopRightMenu':
                        print 'Ajoute des éléments au menu supérieur';
                        break;
                    default:
                        print 'Autre hook';
                }
                print '</td></tr>';
            }
        }
        print '</table>';
    } else {
        print '<div class="error">❌ Classe ActionsRoundcube non trouvée</div>';
    }
} else {
    print '<div class="error">❌ Fichier hooks manquant</div>';
}

// 5. Test manuel d'ajout d'onglet
print '<h2>🧪 Test manuel d\'onglet</h2>';

// Créer un objet utilisateur de test
$test_user = new User($db);
$test_user->fetch($user->id);

if (class_exists('ActionsRoundcube')) {
    $hook_instance = new ActionsRoundcube($db);
    
    // Simuler les onglets utilisateur
    $head = user_prepare_head($test_user);
    print '<p><strong>Onglets avant hook :</strong> ' . count($head) . '</p>';
    
    // Appeler le hook
    $hook_instance->completeTabsHead($head, $test_user, '', null);
    print '<p><strong>Onglets après hook :</strong> ' . count($head) . '</p>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td>Onglet</td><td>URL</td><td>Titre</td></tr>';
    
    foreach ($head as $index => $tab) {
        print '<tr><td>'.($index + 1).'</td><td>'.htmlspecialchars($tab[0]).'</td><td>'.htmlspecialchars($tab[1]).'</td></tr>';
    }
    print '</table>';
}

// 6. Vérifier la configuration des hooks dans le module
print '<h2>⚙️ Configuration module</h2>';

$module_file = dol_buildpath('/custom/roundcubemodule/core/modules/modRoundcubeModule.class.php', 0);
if (file_exists($module_file)) {
    $module_content = file_get_contents($module_file);
    
    // Chercher la déclaration des hooks
    if (preg_match('/\$this->module_parts\s*=\s*array\((.*?)\);/s', $module_content, $matches)) {
        print '<div class="ok">✅ Configuration module_parts trouvée</div>';
        print '<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">';
        print '$this->module_parts = array(' . trim($matches[1]) . ');';
        print '</pre>';
        
        // Vérifier si 'usercard' est présent
        if (strpos($matches[1], 'usercard') !== false) {
            print '<div class="ok">✅ Hook usercard déclaré</div>';
        } else {
            print '<div class="error">❌ Hook usercard manquant dans la déclaration</div>';
        }
    } else {
        print '<div class="error">❌ Configuration module_parts non trouvée</div>';
    }
} else {
    print '<div class="error">❌ Fichier module principal manquant</div>';
}

// 7. Instructions de résolution
print '<h2>🔧 Solutions</h2>';
print '<div class="info">';
print '<strong>Si l\'onglet n\'apparaît pas :</strong><br>';
print '1. <strong>Désactiver puis réactiver</strong> le module dans Configuration > Modules<br>';
print '2. <strong>Vider le cache</strong> Dolibarr si activé<br>';
print '3. <strong>Vérifier les droits</strong> utilisateur roundcubemodule.webmail.read<br>';
print '4. <strong>Forcer le rechargement</strong> avec Ctrl+F5<br>';
print '5. <strong>Vérifier les logs</strong> dans /documents/dolibarr.log<br>';
print '</div>';

// 8. Test en temps réel avec JavaScript
print '<h2>🔬 Test JavaScript en temps réel</h2>';
print '<div id="js-test-result">En cours de test...</div>';

print '<script>
document.addEventListener("DOMContentLoaded", function() {
    var result = document.getElementById("js-test-result");
    var tests = [];
    
    // Test 1: Vérifier si nous sommes dans une fiche utilisateur
    tests.push("URL actuelle: " + window.location.href);
    
    // Test 2: Chercher les onglets existants
    var tabs = document.querySelectorAll(".tabBar a, .tabs a, .fiche .tabBar a");
    tests.push("Onglets trouvés: " + tabs.length);
    
    // Test 3: Lister les onglets
    tabs.forEach(function(tab, index) {
        tests.push("Onglet " + (index + 1) + ": " + tab.textContent.trim());
    });
    
    // Test 4: Chercher spécifiquement l\'onglet Roundcube
    var roundcubeTab = Array.from(tabs).find(tab => tab.textContent.includes("Roundcube") || tab.textContent.includes("webmail"));
    tests.push("Onglet Roundcube trouvé: " + (roundcubeTab ? "✅ Oui" : "❌ Non"));
    
    result.innerHTML = "<pre>" + tests.join("\\n") + "</pre>";
});
</script>';

// Actions
print '<div class="tabsAction">';
print '<a class="butAction" href="roundcube_config.php">Configuration</a>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'">Actualiser</a>';
print '<a class="butAction" href="'.DOL_URL_ROOT.'/user/card.php?id='.$user->id.'">Ma fiche utilisateur</a>';
print '</div>';

llxFooter();
?>