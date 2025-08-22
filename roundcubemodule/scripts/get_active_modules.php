<?php
/**
 * Script pour récupérer les modules actifs de Dolibarr
 * À placer dans : /custom/roundcubemodule/scripts/get_active_modules.php
 */

// Désactiver l'affichage des erreurs et tout output de Dolibarr
error_reporting(0);
ini_set('display_errors', 0);

// Capturer tout output indésirable
ob_start();

// Configuration Dolibarr
$dolibarr_main_document_root = dirname(dirname(dirname(__DIR__)));
require_once $dolibarr_main_document_root . '/main.inc.php';

// Nettoyer l'output buffer
ob_clean();

// Headers après le nettoyage
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    // Vérifier que Dolibarr est bien chargé
    if (!defined('DOL_VERSION')) {
        throw new Exception('Dolibarr non initialisé');
    }

    // Liste des modules supportés avec leurs informations
    $supported_modules = [
        'societe' => [
            'code' => 'thirdparty',
            'value' => 'thirdparty', 
            'label' => '🏢 Tiers',
            'dolibarr_module' => 'societe'
        ],
        'contact' => [
            'code' => 'contact',
            'value' => 'contact',
            'label' => '👤 Contact', 
            'dolibarr_module' => 'contact'
        ],
        'projet' => [
            'code' => 'project',
            'value' => 'project',
            'label' => '📋 Projet',
            'dolibarr_module' => 'projet'
        ],
        'propal' => [
            'code' => 'propal',
            'value' => 'propal',
            'label' => '📄 Proposition commerciale',
            'dolibarr_module' => 'propal'
        ],
        'commande' => [
            'code' => 'commande', 
            'value' => 'commande',
            'label' => '🛒 Commande client',
            'dolibarr_module' => 'commande'
        ],
        'facture' => [
            'code' => 'invoice',
            'value' => 'invoice', 
            'label' => '💰 Facture client',
            'dolibarr_module' => 'facture'
        ],
        'expedition' => [
            'code' => 'expedition',
            'value' => 'expedition',
            'label' => '📦 Expédition',
            'dolibarr_module' => 'expedition'
        ],
        'contrat' => [
            'code' => 'contract',
            'value' => 'contract',
            'label' => '📋 Contrat',
            'dolibarr_module' => 'contrat'
        ],
        'ficheinter' => [
            'code' => 'fichinter',
            'value' => 'fichinter', 
            'label' => '🔧 Intervention',
            'dolibarr_module' => 'ficheinter'
        ],
        'ticket' => [
            'code' => 'ticket',
            'value' => 'ticket',
            'label' => '🎫 Ticket',
            'dolibarr_module' => 'ticket'
        ]
    ];

    $active_modules = [];

    // Vérifier quels modules sont actifs dans Dolibarr
    foreach ($supported_modules as $dolibarr_name => $module_info) {
        // Méthode 1: Via la configuration
        $module_var = 'MAIN_MODULE_' . strtoupper($dolibarr_name);
        if (getDolGlobalString($module_var)) {
            $active_modules[] = $module_info;
            continue;
        }
        
        // Méthode 2: Via la constante de configuration
        if (isModEnabled($dolibarr_name)) {
            $active_modules[] = $module_info;
            continue;
        }
        
        // Méthode 3: Vérification manuelle pour certains modules
        if ($dolibarr_name === 'societe' && (!empty($conf->societe->enabled))) {
            $active_modules[] = $module_info;
        } elseif ($dolibarr_name === 'contact' && (!empty($conf->contact->enabled))) {
            $active_modules[] = $module_info;
        } elseif ($dolibarr_name === 'projet' && (!empty($conf->projet->enabled))) {
            $active_modules[] = $module_info;
        }
    }

    // Si aucun module détecté, retourner des modules de base
    if (empty($active_modules)) {
        $active_modules = [
            $supported_modules['societe'],
            $supported_modules['contact'],
            $supported_modules['projet']
        ];
    }

    // S'assurer qu'on a un JSON valide
    $json_output = json_encode($active_modules, JSON_UNESCAPED_UNICODE);
    
    // Nettoyer complètement l'output avant d'envoyer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo $json_output;
    exit;

} catch (Exception $e) {
    // En cas d'erreur, retourner des modules par défaut
    $default_modules = [
        [
            'code' => 'thirdparty',
            'value' => 'thirdparty',
            'label' => '🏢 Tiers (défaut)'
        ],
        [
            'code' => 'contact', 
            'value' => 'contact',
            'label' => '👤 Contact (défaut)'
        ],
        [
            'code' => 'project',
            'value' => 'project', 
            'label' => '📋 Projet (défaut)'
        ]
    ];
    
    // Nettoyer l'output et retourner le JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($default_modules, JSON_UNESCAPED_UNICODE);
    exit;
}
?>