<?php
/**
 * API de recherche d'entités pour le classement des mails
 * 
 * Emplacement: custom/roundcubemodule/components/classification/api/search-entities.php
 */

// Recherche de main.inc.php
$res = 0;
$paths = ['../../../../main.inc.php', '../../../../../main.inc.php', '../../../../../../main.inc.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration Dolibarr non trouvée']);
    exit;
}

// Headers pour JSON et CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Vérification de l'authentification
if (empty($user->id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Vérification des droits
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Droits insuffisants']);
    exit;
}

/**
 * Fonction de recherche d'entités
 */
function searchEntities($type, $query, $limit = 10) {
    global $db, $user;
    
    $results = [];
    $query = trim($query);
    
    if (strlen($query) < 2) {
        return $results;
    }
    
    $query_escaped = $db->escape($query);
    
    try {
        switch ($type) {
            case 'thirdparty':
                $sql = "SELECT s.rowid as id, s.nom as label, s.code_client, s.email 
                        FROM " . MAIN_DB_PREFIX . "societe s 
                        WHERE s.entity IN (" . getEntity('societe') . ") 
                        AND (s.nom LIKE '%" . $query_escaped . "%' 
                             OR s.code_client LIKE '%" . $query_escaped . "%'
                             OR s.email LIKE '%" . $query_escaped . "%')
                        ORDER BY s.nom ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'contact':
                $sql = "SELECT c.rowid as id, 
                               CONCAT(c.firstname, ' ', c.lastname) as label,
                               c.email,
                               s.nom as societe_nom
                        FROM " . MAIN_DB_PREFIX . "socpeople c
                        LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON c.fk_soc = s.rowid
                        WHERE c.entity IN (" . getEntity('contact') . ")
                        AND (c.firstname LIKE '%" . $query_escaped . "%' 
                             OR c.lastname LIKE '%" . $query_escaped . "%'
                             OR c.email LIKE '%" . $query_escaped . "%'
                             OR s.nom LIKE '%" . $query_escaped . "%')
                        ORDER BY c.lastname, c.firstname ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'project':
                $sql = "SELECT p.rowid as id, p.title as label, p.ref, s.nom as societe_nom
                        FROM " . MAIN_DB_PREFIX . "projet p
                        LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON p.fk_soc = s.rowid
                        WHERE p.entity IN (" . getEntity('project') . ")
                        AND (p.title LIKE '%" . $query_escaped . "%' 
                             OR p.ref LIKE '%" . $query_escaped . "%'
                             OR s.nom LIKE '%" . $query_escaped . "%')
                        ORDER BY p.title ASC 
                        LIMIT " . (int)$limit;
                break;
                
            default:
                throw new Exception('Type d\'entité non supporté: ' . $type);
        }
        
        $resql = $db->query($sql);
        
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $result = [
                    'id' => $obj->id,
                    'label' => $obj->label,
                    'type' => $type
                ];
                
                // Ajouter des informations contextuelles
                switch ($type) {
                    case 'thirdparty':
                        if (!empty($obj->code_client)) {
                            $result['label'] .= ' (' . $obj->code_client . ')';
                        }
                        if (!empty($obj->email)) {
                            $result['email'] = $obj->email;
                        }
                        break;
                        
                    case 'contact':
                        if (!empty($obj->email)) {
                            $result['email'] = $obj->email;
                        }
                        if (!empty($obj->societe_nom)) {
                            $result['label'] .= ' - ' . $obj->societe_nom;
                        }
                        break;
                        
                    case 'project':
                        if (!empty($obj->ref)) {
                            $result['label'] = $obj->ref . ' - ' . $result['label'];
                        }
                        if (!empty($obj->societe_nom)) {
                            $result['societe'] = $obj->societe_nom;
                        }
                        break;
                }
                
                $results[] = $result;
            }
            $db->free($resql);
        } else {
            throw new Exception('Erreur SQL: ' . $db->lasterror());
        }
        
    } catch (Exception $e) {
        error_log('Erreur recherche entités: ' . $e->getMessage());
        throw $e;
    }
    
    return $results;
}

/**
 * Point d'entrée principal
 */
try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'search_entities':
            $type = $_GET['type'] ?? '';
            $query = $_GET['query'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 résultats
            
            if (empty($type) || empty($query)) {
                throw new Exception('Paramètres manquants: type et query requis');
            }
            
            $results = searchEntities($type, $query, $limit);
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'count' => count($results),
                'query' => $query,
                'type' => $type
            ]);
            break;
            
        case 'test':
            // Test de l'API
            echo json_encode([
                'success' => true,
                'message' => 'API de recherche fonctionnelle',
                'user_id' => $user->id,
                'user_name' => $user->getFullName($langs),
                'timestamp' => date('Y-m-d H:i:s'),
                'supported_types' => ['thirdparty', 'contact', 'project']
            ]);
            break;
            
        default:
            throw new Exception('Action non supportée: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * DOCUMENTATION DE L'API
 * 
 * ENDPOINTS:
 * 
 * 1. Test de l'API:
 *    GET ?action=test
 *    Retourne: statut de l'API et informations système
 * 
 * 2. Recherche d'entités:
 *    GET ?action=search_entities&type=TYPE&query=QUERY&limit=LIMIT
 *    
 *    Types supportés:
 *    - thirdparty: Recherche dans les sociétés/tiers
 *    - contact: Recherche dans les contacts
 *    - project: Recherche dans les projets
 *    
 *    Paramètres:
 *    - type (requis): Type d'entité à rechercher
 *    - query (requis): Terme de recherche (min 2 caractères)
 *    - limit (optionnel): Nombre max de résultats (défaut: 10, max: 50)
 * 
 * RÉPONSES:
 * 
 * Succès:
 * {
 *   "success": true,
 *   "results": [
 *     {
 *       "id": 123,
 *       "label": "Nom affiché",
 *       "type": "thirdparty",
 *       "email": "email@example.com" // si disponible
 *     }
 *   ],
 *   "count": 1,
 *   "query": "terme recherché",
 *   "type": "thirdparty"
 * }
 * 
 * Erreur:
 * {
 *   "success": false,
 *   "error": "Message d'erreur",
 *   "timestamp": "2024-01-01 12:00:00"
 * }
 * 
 * EXEMPLES D'UTILISATION:
 * 
 * // Rechercher des sociétés contenant "acme"
 * fetch('search-entities.php?action=search_entities&type=thirdparty&query=acme')
 * 
 * // Rechercher des contacts contenant "john"
 * fetch('search-entities.php?action=search_entities&type=contact&query=john&limit=5')
 * 
 * // Tester l'API
 * fetch('search-entities.php?action=test')
 */
?>