<?php
require_once __DIR__ . '/../includes/config.php';

if (!isAdmin()) {
    jsonResponse(['error' => 'Không có quyền admin'], 403);
}

$db = getDB();

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    switch ($action) {
        case 'drugs':
            $drugs = $db->query("SELECT * FROM drugs ORDER BY idx LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db->query("SELECT COUNT(*) FROM drugs")->fetchColumn();
            jsonResponse(['drugs' => $drugs, 'total' => (int)$total, 'page' => $page]);
            break;
            
        case 'diseases':
            $diseases = $db->query("SELECT * FROM diseases ORDER BY idx LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db->query("SELECT COUNT(*) FROM diseases")->fetchColumn();
            jsonResponse(['diseases' => $diseases, 'total' => (int)$total, 'page' => $page]);
            break;
            
        case 'associations':
            $assocs = $db->query("SELECT ka.*, d.name as drug_name, d.drug_id, di.name as disease_name, di.disease_id 
                FROM known_associations ka
                LEFT JOIN drugs d ON ka.drug_idx = d.idx
                LEFT JOIN diseases di ON ka.disease_idx = di.idx
                ORDER BY ka.id LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn();
            jsonResponse(['associations' => $assocs, 'total' => (int)$total, 'page' => $page]);
            break;
            
        case 'logs':
            $logs = $db->query("SELECT p.*, u.username FROM predictions p 
                JOIN users u ON p.user_id = u.id 
                ORDER BY p.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['logs' => $logs]);
            break;
            
        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_drug':
            $stmt = $db->prepare("UPDATE drugs SET name = ? WHERE id = ?");
            $stmt->execute([$input['name'], $input['id']]);
            jsonResponse(['success' => true]);
            break;
            
        case 'update_disease':
            $stmt = $db->prepare("UPDATE diseases SET name = ? WHERE id = ?");
            $stmt->execute([$input['name'], $input['id']]);
            jsonResponse(['success' => true]);
            break;
            
        case 'add_drug':
            $maxIdx = $db->query("SELECT MAX(idx) FROM drugs")->fetchColumn();
            $newIdx = $maxIdx !== false ? (int)$maxIdx + 1 : 0;
            $stmt = $db->prepare("INSERT INTO drugs (drug_id, name, idx) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$input['drug_id'], $input['name'], $newIdx]);
                jsonResponse(['success' => true]);
            } catch (Exception $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;
            
        case 'add_disease':
            $maxIdx = $db->query("SELECT MAX(idx) FROM diseases")->fetchColumn();
            $newIdx = $maxIdx !== false ? (int)$maxIdx + 1 : 0;
            $stmt = $db->prepare("INSERT INTO diseases (disease_id, name, idx) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$input['disease_id'], $input['name'], $newIdx]);
                jsonResponse(['success' => true]);
            } catch (Exception $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;
            
        case 'delete_drug':
            $stmt = $db->prepare("DELETE FROM drugs WHERE id = ?");
            $stmt->execute([$input['id']]);
            jsonResponse(['success' => true]);
            break;
            
        case 'delete_disease':
            $stmt = $db->prepare("DELETE FROM diseases WHERE id = ?");
            $stmt->execute([$input['id']]);
            jsonResponse(['success' => true]);
            break;
            
        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}
?>
