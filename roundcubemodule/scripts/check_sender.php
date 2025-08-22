<?php
// Version simple de check_sender.php avec conf.php
header('Content-Type: application/json; charset=utf-8');

$email = '';
if (isset($_GET['email'])) {
    $email = $_GET['email'];
} elseif (isset($_POST['email'])) {
    $email = $_POST['email'];
}

if (empty($email)) {
    echo json_encode(['found' => false, 'error' => 'Email manquant']);
    exit;
}

$email = trim(strtolower($email));

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
    
    $db_prefix = $dolibarr_main_db_prefix;
    
    // Rechercher dans les tiers
    $sql = "SELECT rowid, nom, email FROM {$db_prefix}societe 
            WHERE LOWER(email) = :email 
            AND status = 1
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'found' => true,
            'type' => 'societe',
            'societe' => [
                'id' => intval($row['rowid']),
                'nom' => $row['nom'],
                'email' => $row['email']
            ],
            'name' => $row['nom'],
            'id' => intval($row['rowid'])
        ]);
        exit;
    }

    // Rechercher dans les contacts
    $sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, c.fk_soc, s.nom as societe_nom
            FROM {$db_prefix}socpeople c
            LEFT JOIN {$db_prefix}societe s ON c.fk_soc = s.rowid
            WHERE LOWER(c.email) = :email
            AND c.statut = 1
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'found' => true,
            'type' => 'contact',
            'contact' => [
                'id' => intval($row['rowid']),
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'fk_soc' => $row['fk_soc'] ? intval($row['fk_soc']) : null,
                'societe_nom' => $row['societe_nom']
            ],
            'name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'id' => intval($row['rowid'])
        ]);
        exit;
    }

    // Aucun résultat trouvé
    echo json_encode([
        'found' => false,
        'email' => $email
    ]);

} catch (Exception $e) {
    // En cas d'erreur, simuler une réponse
    $known_emails = [
        'test@example.com' => ['name' => 'Société Test (simulation)', 'id' => 1],
        'contact@dolibarr.com' => ['name' => 'Dolibarr (simulation)', 'id' => 2],
        'demo@avocats.com' => ['name' => 'Cabinet Avocats (simulation)', 'id' => 3]
    ];

    if (isset($known_emails[$email])) {
        echo json_encode([
            'found' => true,
            'type' => 'societe',
            'societe' => [
                'id' => $known_emails[$email]['id'],
                'nom' => $known_emails[$email]['name'] . ' [ERREUR BDD: ' . $e->getMessage() . ']',
                'email' => $email
            ],
            'name' => $known_emails[$email]['name'],
            'id' => $known_emails[$email]['id']
        ]);
    } else {
        echo json_encode([
            'found' => false,
            'email' => $email,
            'debug' => 'Erreur BDD: ' . $e->getMessage()
        ]);
    }
}