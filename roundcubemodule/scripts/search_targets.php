<?php
// Version connectée à Dolibarr pour search_targets.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Charger la configuration Dolibarr depuis conf.php
    $dolibarr_main_document_root = dirname(dirname(dirname(__DIR__)));
    $conf_file = $dolibarr_main_document_root . '/htdocs/conf/conf.php';
    
    if (!file_exists($conf_file)) {
        // Essayer d'autres emplacements possibles
        $conf_file = $dolibarr_main_document_root . '/conf/conf.php';
        if (!file_exists($conf_file)) {
            throw new Exception('Fichier conf.php non trouvé');
        }
    }

    // Inclure le fichier de configuration
    include_once $conf_file;

    // Vérifier que les variables sont définies
    if (!isset($dolibarr_main_db_host) || !isset($dolibarr_main_db_name)) {
        throw new Exception('Configuration Dolibarr non trouvée dans conf.php');
    }

    // Connexion à la base de données avec les paramètres de Dolibarr
    $dsn = "mysql:host={$dolibarr_main_db_host}";
    if (!empty($dolibarr_main_db_port)) {
        $dsn .= ";port={$dolibarr_main_db_port}";
    }
    $dsn .= ";dbname={$dolibarr_main_db_name};charset=utf8";

    $pdo = new PDO($dsn, $dolibarr_main_db_user, $dolibarr_main_db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $query_param = '%' . $query . '%';
    $db_prefix = $dolibarr_main_db_prefix;

    switch ($type) {
        case 'thirdparty':
            $sql = "SELECT rowid, nom as label FROM {$db_prefix}societe 
                    WHERE nom LIKE :query 
                    AND status = 1
                    ORDER BY nom LIMIT 20";
            break;

        case 'contact':
            $sql = "SELECT rowid, CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, '')) as label 
                    FROM {$db_prefix}socpeople 
                    WHERE (COALESCE(firstname, '') LIKE :query 
                           OR COALESCE(lastname, '') LIKE :query)
                    AND statut = 1
                    ORDER BY lastname, firstname LIMIT 20";
            break;

        case 'project':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), ' - ', COALESCE(title, '')) as label 
                    FROM {$db_prefix}projet 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(title, '') LIKE :query)
                    AND fk_statut = 1
                    ORDER BY ref LIMIT 20";
            break;

        case 'propal':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), 
                           CASE WHEN ref_client IS NOT NULL AND ref_client != '' 
                                THEN CONCAT(' - ', ref_client) 
                                ELSE '' END) as label 
                    FROM {$db_prefix}propal 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(ref_client, '') LIKE :query)
                    ORDER BY ref DESC LIMIT 20";
            break;

        case 'commande':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), 
                           CASE WHEN ref_client IS NOT NULL AND ref_client != '' 
                                THEN CONCAT(' - ', ref_client) 
                                ELSE '' END) as label 
                    FROM {$db_prefix}commande 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(ref_client, '') LIKE :query)
                    ORDER BY ref DESC LIMIT 20";
            break;

        case 'invoice':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), 
                           CASE WHEN ref_client IS NOT NULL AND ref_client != '' 
                                THEN CONCAT(' - ', ref_client) 
                                ELSE '' END) as label 
                    FROM {$db_prefix}facture 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(ref_client, '') LIKE :query)
                    ORDER BY ref DESC LIMIT 20";
            break;

        case 'ticket':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), ' - ', COALESCE(subject, '')) as label 
                    FROM {$db_prefix}ticket 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(subject, '') LIKE :query 
                           OR COALESCE(track_id, '') LIKE :query)
                    ORDER BY ref DESC LIMIT 20";
            break;

        case 'contract':
            $sql = "SELECT rowid, CONCAT(COALESCE(ref, ''), 
                           CASE WHEN ref_customer IS NOT NULL AND ref_customer != '' 
                                THEN CONCAT(' - ', ref_customer) 
                                ELSE '' END) as label 
                    FROM {$db_prefix}contrat 
                    WHERE (COALESCE(ref, '') LIKE :query 
                           OR COALESCE(ref_customer, '') LIKE :query)
                    ORDER BY ref DESC LIMIT 20";
            break;

        default:
            echo json_encode(['error' => 'Type de recherche non supporté: ' . $type]);
            exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':query', $query_param);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Nettoyer le label
        $label = trim($row['label']);
        $label = str_replace('  ', ' ', $label); // Supprimer les espaces doubles
        $label = rtrim($label, ' -'); // Supprimer les tirets et espaces en fin
        
        if (!empty($label) && $label !== ' ') {
            $results[] = [
                'id' => intval($row['rowid']),
                'label' => $label
            ];
        }
    }

    echo json_encode($results);

} catch (Exception $e) {
    // En cas d'erreur, retourner les données simulées avec indication d'erreur
    $mock_data = [
        'thirdparty' => [
            ['id' => 1, 'label' => 'ACME Corporation (simulation - erreur BDD)'],
            ['id' => 2, 'label' => 'Global Solutions SARL (simulation - erreur BDD)'],
            ['id' => 3, 'label' => 'TechStart SAS (simulation - erreur BDD)']
        ],
        'contact' => [
            ['id' => 1, 'label' => 'Jean Dupont (simulation - erreur BDD)'],
            ['id' => 2, 'label' => 'Marie Martin (simulation - erreur BDD)']
        ],
        'project' => [
            ['id' => 1, 'label' => 'PROJ-2024-001 - Refonte site web (simulation - erreur BDD)']
        ]
    ];
    
    $results = [];
    if (isset($mock_data[$type])) {
        $query_lower = strtolower($query);
        foreach ($mock_data[$type] as $item) {
            if (strpos(strtolower($item['label']), $query_lower) !== false) {
                $results[] = $item;
            }
        }
    }
    
    // Ajouter l'erreur en premier résultat pour debug
    array_unshift($results, [
        'id' => 0, 
        'label' => 'ERREUR: ' . $e->getMessage() . ' (mode simulation activé)'
    ]);
    
    echo json_encode($results);
}
?>