<?php
/**
 * Version simplifiée sans inclusion de Dolibarr
 * À placer dans : /custom/roundcubemodule/scripts/get_active_modules_simple.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Modules par défaut sans vérification Dolibarr
$modules = [
    [
        'code' => 'thirdparty',
        'value' => 'thirdparty',
        'label' => '🏢 Tiers'
    ],
    [
        'code' => 'contact',
        'value' => 'contact',
        'label' => '👤 Contact'
    ],
    [
        'code' => 'project',
        'value' => 'project',
        'label' => '📋 Projet'
    ],
    [
        'code' => 'propal',
        'value' => 'propal',
        'label' => '📄 Proposition commerciale'
    ],
    [
        'code' => 'commande',
        'value' => 'commande',
        'label' => '🛒 Commande client'
    ],
    [
        'code' => 'invoice',
        'value' => 'invoice',
        'label' => '💰 Facture client'
    ],
    [
        'code' => 'contract',
        'value' => 'contract',
        'label' => '📋 Contrat'
    ],
    [
        'code' => 'ticket',
        'value' => 'ticket',
        'label' => '🎫 Ticket'
    ]
];

echo json_encode($modules, JSON_UNESCAPED_UNICODE);
exit;
?>