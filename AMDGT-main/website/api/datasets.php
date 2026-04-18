<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Vui lòng đăng nhập'], 401);
}

$db = getDB();
if ($db === null) {
    jsonResponse(['error' => 'Database chưa được khởi tạo', 'items' => [], 'total' => 0], 503);
}

try {


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    // Lấy tất cả vì người dùng không quan tâm dataset nào nữa (theo yêu cầu)
    // Nếu muốn tìm kiếm
    $q = $_GET['q'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    $where = "1=1";
    $params = [];
    if (!empty($q)) {
        $where .= " AND (name LIKE ? OR drug_id LIKE ? OR disease_id LIKE ?)";
        $params = ["%$q%", "%$q%", "%$q%"];
    }

    switch ($action) {
        case 'drugs':
            if (!empty($q)) {
                $stmt = $db->prepare("SELECT * FROM drugs WHERE $where ORDER BY id LIMIT $perPage OFFSET $offset");
                $stmt->execute([$params[0], $params[1], ""]);
                $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $countStmt = $db->prepare("SELECT COUNT(*) FROM drugs WHERE $where");
                $countStmt->execute([$params[0], $params[1], ""]);
                $total = $countStmt->fetchColumn();
            } else {
                $drugs = $db->query("SELECT * FROM drugs ORDER BY id LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
                $total = $db->query("SELECT COUNT(*) FROM drugs")->fetchColumn();
            }
            jsonResponse(['items' => $drugs, 'total' => (int)$total, 'page' => $page]);
            break;
            
        case 'diseases':
            if (!empty($q)) {
                $stmt = $db->prepare("SELECT * FROM diseases WHERE $where ORDER BY id LIMIT $perPage OFFSET $offset");
                $stmt->execute([$params[0], "", $params[2]]);
                $diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $countStmt = $db->prepare("SELECT COUNT(*) FROM diseases WHERE $where");
                $countStmt->execute([$params[0], "", $params[2]]);
                $total = $countStmt->fetchColumn();
            } else {
                $diseases = $db->query("SELECT * FROM diseases ORDER BY id LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
                $total = $db->query("SELECT COUNT(*) FROM diseases")->fetchColumn();
            }
            jsonResponse(['items' => $diseases, 'total' => (int)$total, 'page' => $page]);
            break;

        case 'associations':
            $assocQuery = "SELECT ka.*, d.name as drug_name, d.drug_id, di.name as disease_name, di.disease_id 
                FROM known_associations ka
                LEFT JOIN drugs d ON ka.drug_idx = d.idx AND ka.dataset = d.dataset
                LEFT JOIN diseases di ON ka.disease_idx = di.idx AND ka.dataset = di.dataset";
                
            $countQuery = "SELECT COUNT(*) FROM known_associations";
            
            $assocs = $db->query("$assocQuery ORDER BY ka.id LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db->query($countQuery)->fetchColumn();
            
            jsonResponse(['items' => $assocs, 'total' => (int)$total, 'page' => $page]);
            break;
            
        case 'stats':
            $stats = [
                'drugs' => (int)$db->query("SELECT COUNT(*) FROM drugs")->fetchColumn(),
                'diseases' => (int)$db->query("SELECT COUNT(*) FROM diseases")->fetchColumn(),
                'associations' => (int)$db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn(),
            ];
            jsonResponse($stats);
            break;
            
        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}
} catch (PDOException $e) {
    jsonResponse(['error' => 'Lỗi database: ' . $e->getMessage(), 'items' => [], 'total' => 0], 500);
}
?>
