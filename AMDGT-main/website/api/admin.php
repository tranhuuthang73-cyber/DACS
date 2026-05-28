<?php
require_once __DIR__ . '/../includes/config.php';

if (!isAdmin()) {
    jsonResponse(['error' => 'Không có quyền admin'], 403);
}

$db = getDB();
if ($db === null) {
    jsonResponse(['error' => 'Database chưa được khởi tạo. Hãy chạy setup_db.php trước!'], 503);
}

// Wrap all queries in try-catch
try {


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

        case 'proteins':
            $proteins = $db->query("SELECT * FROM proteins ORDER BY idx LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db->query("SELECT COUNT(*) FROM proteins")->fetchColumn();
            jsonResponse(['proteins' => $proteins, 'total' => (int)$total, 'page' => $page]);
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
            
        case 'users':
            $users = $db->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $total = count($users);
            jsonResponse(['users' => $users, 'total' => $total]);
            break;
            
        case 'stats':
            $stats = [
                'drugs' => (int)$db->query("SELECT COUNT(*) FROM drugs")->fetchColumn(),
                'diseases' => (int)$db->query("SELECT COUNT(*) FROM diseases")->fetchColumn(),
                'proteins' => (int)$db->query("SELECT COUNT(*) FROM proteins")->fetchColumn(),
                'associations' => (int)$db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn(),
                'predictions' => (int)$db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
            ];
            jsonResponse($stats);
            break;
            
        case 'dataset_stats':
            $datasets = ['B-dataset', 'C-dataset', 'F-dataset'];
            $datasetStats = [];
            foreach ($datasets as $ds) {
                $datasetStats[] = [
                    'dataset' => $ds,
                    'drugs' => (int)$db->query("SELECT COUNT(*) FROM drugs WHERE dataset = '$ds'")->fetchColumn(),
                    'diseases' => (int)$db->query("SELECT COUNT(*) FROM diseases WHERE dataset = '$ds'")->fetchColumn(),
                    'proteins' => (int)$db->query("SELECT COUNT(*) FROM proteins WHERE dataset = '$ds'")->fetchColumn(),
                    'dd' => (int)$db->query("SELECT COUNT(*) FROM known_associations WHERE dataset = '$ds'")->fetchColumn(),
                    'dp' => (int)$db->query("SELECT COUNT(*) FROM drug_protein_associations WHERE dataset = '$ds'")->fetchColumn(),
                    'pd' => (int)$db->query("SELECT COUNT(*) FROM protein_disease_associations WHERE dataset = '$ds'")->fetchColumn()
                ];
            }
            jsonResponse(['stats' => $datasetStats]);
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
            $stmt->execute([$input['name'] ?? '', $input['id'] ?? 0]);
            jsonResponse(['success' => true]);
            break;
            
        case 'update_disease':
            $stmt = $db->prepare("UPDATE diseases SET name = ? WHERE id = ?");
            $stmt->execute([$input['name'] ?? '', $input['id'] ?? 0]);
            jsonResponse(['success' => true]);
            break;

        case 'update_protein':
            $stmt = $db->prepare("UPDATE proteins SET name = ? WHERE id = ?");
            $stmt->execute([$input['name'] ?? '', $input['id'] ?? 0]);
            jsonResponse(['success' => true]);
            break;
            
        case 'add_drug':
            $dataset = $input['dataset'] ?? 'C-dataset'; // Lấy dataset từ client
            
            // DDL statements cause implicit commits in MySQL. Create table before starting transaction.
            if (!empty($input['protein_id']) || !empty($input['protein_name'])) {
                $db->exec("CREATE TABLE IF NOT EXISTS drug_protein_associations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dataset VARCHAR(20) NOT NULL,
                    drug_idx INT,
                    protein_idx INT,
                    UNIQUE KEY(dataset, drug_idx, protein_idx)
                ) ENGINE=InnoDB");
            }
            
            try {
                $db->beginTransaction();
                
                // 1. Add Drug
                $drug_id = $input['drug_id'] ?? uniqid('D');
                $drug_name = $input['name'] ?? 'Unknown Drug';
                
                // Check if drug exists
                $stmt = $db->prepare("SELECT idx FROM drugs WHERE drug_id = ?");
                $stmt->execute([$drug_id]);
                $drug_idx = $stmt->fetchColumn();
                
                if ($drug_idx === false) {
                    $maxIdx = $db->query("SELECT MAX(idx) FROM drugs")->fetchColumn();
                    $drug_idx = $maxIdx !== false ? (int)$maxIdx + 1 : 0;
                    $stmt = $db->prepare("INSERT INTO drugs (dataset, drug_id, name, idx) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$dataset, $drug_id, $drug_name, $drug_idx]);
                }
                
                // 2. Add Protein & Link (If provided)
                if (!empty($input['protein_id']) || !empty($input['protein_name'])) {
                    $p_id = !empty($input['protein_id']) ? $input['protein_id'] : uniqid('P');
                    $p_name = !empty($input['protein_name']) ? $input['protein_name'] : $p_id;
                    
                    // Check if protein exists
                    $stmt = $db->prepare("SELECT idx FROM proteins WHERE protein_id = ?");
                    $stmt->execute([$p_id]);
                    $protein_idx = $stmt->fetchColumn();
                    
                    if ($protein_idx === false) {
                        $maxPIdx = $db->query("SELECT MAX(idx) FROM proteins")->fetchColumn();
                        $protein_idx = $maxPIdx !== false ? (int)$maxPIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO proteins (dataset, protein_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $p_id, $p_name, $protein_idx]);
                    }
                    
                    // Link drug and protein
                    $stmt = $db->prepare("INSERT IGNORE INTO drug_protein_associations (dataset, drug_idx, protein_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $drug_idx, $protein_idx]);
                }
                
                // 3. Add Disease & Link (If provided)
                if (!empty($input['disease_id']) || !empty($input['disease_name'])) {
                    $d_id = !empty($input['disease_id']) ? $input['disease_id'] : uniqid('DIS');
                    $d_name = !empty($input['disease_name']) ? $input['disease_name'] : $d_id;
                    
                    // Check if disease exists
                    $stmt = $db->prepare("SELECT idx FROM diseases WHERE disease_id = ?");
                    $stmt->execute([$d_id]);
                    $disease_idx = $stmt->fetchColumn();
                    
                    if ($disease_idx === false) {
                        $maxDIdx = $db->query("SELECT MAX(idx) FROM diseases")->fetchColumn();
                        $disease_idx = $maxDIdx !== false ? (int)$maxDIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO diseases (dataset, disease_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $d_id, $d_name, $disease_idx]);
                    }
                    
                    // Link drug and disease
                    $stmt = $db->prepare("INSERT IGNORE INTO known_associations (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $drug_idx, $disease_idx]);
                }
                
                $db->commit();
                jsonResponse(['success' => true]);
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;
            
        case 'add_disease':
            $dataset = $input['dataset'] ?? 'C-dataset'; // Lấy dataset từ client
            
            // DDL statements cause implicit commits in MySQL. Create table before starting transaction.
            if (!empty($input['protein_id']) || !empty($input['protein_name'])) {
                $db->exec("CREATE TABLE IF NOT EXISTS protein_disease_associations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dataset VARCHAR(20) NOT NULL,
                    protein_idx INT,
                    disease_idx INT,
                    UNIQUE KEY(dataset, protein_idx, disease_idx)
                ) ENGINE=InnoDB");
            }
            
            try {
                $db->beginTransaction();
                
                // 1. Add Disease
                $disease_id = $input['disease_id'] ?? uniqid('DIS');
                $disease_name = $input['name'] ?? 'Unknown Disease';
                
                // Check if disease exists
                $stmt = $db->prepare("SELECT idx FROM diseases WHERE disease_id = ?");
                $stmt->execute([$disease_id]);
                $disease_idx = $stmt->fetchColumn();
                
                if ($disease_idx === false) {
                    $maxIdx = $db->query("SELECT MAX(idx) FROM diseases")->fetchColumn();
                    $disease_idx = $maxIdx !== false ? (int)$maxIdx + 1 : 0;
                    $stmt = $db->prepare("INSERT INTO diseases (dataset, disease_id, name, idx) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$dataset, $disease_id, $disease_name, $disease_idx]);
                }
                
                // 2. Add Protein & Link (If provided)
                if (!empty($input['protein_id']) || !empty($input['protein_name'])) {
                    $p_id = !empty($input['protein_id']) ? $input['protein_id'] : uniqid('P');
                    $p_name = !empty($input['protein_name']) ? $input['protein_name'] : $p_id;
                    
                    // Check if protein exists
                    $stmt = $db->prepare("SELECT idx FROM proteins WHERE protein_id = ?");
                    $stmt->execute([$p_id]);
                    $protein_idx = $stmt->fetchColumn();
                    
                    if ($protein_idx === false) {
                        $maxPIdx = $db->query("SELECT MAX(idx) FROM proteins")->fetchColumn();
                        $protein_idx = $maxPIdx !== false ? (int)$maxPIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO proteins (dataset, protein_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $p_id, $p_name, $protein_idx]);
                    }
                    
                    // Link protein and disease
                    $stmt = $db->prepare("INSERT IGNORE INTO protein_disease_associations (dataset, protein_idx, disease_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $protein_idx, $disease_idx]);
                }
                
                // 3. Add Drug & Link (If provided)
                if (!empty($input['drug_id']) || !empty($input['drug_name'])) {
                    $d_id = !empty($input['drug_id']) ? $input['drug_id'] : uniqid('D');
                    $d_name = !empty($input['drug_name']) ? $input['drug_name'] : $d_id;
                    
                    // Check if drug exists
                    $stmt = $db->prepare("SELECT idx FROM drugs WHERE drug_id = ?");
                    $stmt->execute([$d_id]);
                    $drug_idx = $stmt->fetchColumn();
                    
                    if ($drug_idx === false) {
                        $maxDIdx = $db->query("SELECT MAX(idx) FROM drugs")->fetchColumn();
                        $drug_idx = $maxDIdx !== false ? (int)$maxDIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO drugs (dataset, drug_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $d_id, $d_name, $drug_idx]);
                    }
                    
                    // Link drug and disease
                    $stmt = $db->prepare("INSERT IGNORE INTO known_associations (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $drug_idx, $disease_idx]);
                }
                
                $db->commit();
                jsonResponse(['success' => true]);
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;

        case 'add_protein':
            $dataset = $input['dataset'] ?? 'C-dataset'; // Lấy dataset từ client
            
            // DDL statements cause implicit commits in MySQL. Create tables before starting transaction.
            if (!empty($input['drug_id']) || !empty($input['drug_name'])) {
                $db->exec("CREATE TABLE IF NOT EXISTS drug_protein_associations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dataset VARCHAR(20) NOT NULL,
                    drug_idx INT,
                    protein_idx INT,
                    UNIQUE KEY(dataset, drug_idx, protein_idx)
                ) ENGINE=InnoDB");
            }
            if (!empty($input['disease_id']) || !empty($input['disease_name'])) {
                $db->exec("CREATE TABLE IF NOT EXISTS protein_disease_associations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dataset VARCHAR(20) NOT NULL,
                    protein_idx INT,
                    disease_idx INT,
                    UNIQUE KEY(dataset, protein_idx, disease_idx)
                ) ENGINE=InnoDB");
            }
            
            try {
                $db->beginTransaction();
                
                // 1. Add Protein
                $protein_id = $input['protein_id'] ?? uniqid('P');
                $protein_name = $input['name'] ?? 'Unknown Protein';
                
                // Check if protein exists
                $stmt = $db->prepare("SELECT idx FROM proteins WHERE protein_id = ?");
                $stmt->execute([$protein_id]);
                $protein_idx = $stmt->fetchColumn();
                
                if ($protein_idx === false) {
                    $maxIdx = $db->query("SELECT MAX(idx) FROM proteins")->fetchColumn();
                    $protein_idx = $maxIdx !== false ? (int)$maxIdx + 1 : 0;
                    $stmt = $db->prepare("INSERT INTO proteins (dataset, protein_id, name, idx) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$dataset, $protein_id, $protein_name, $protein_idx]);
                }
                
                // 2. Add Drug & Link (If provided)
                if (!empty($input['drug_id']) || !empty($input['drug_name'])) {
                    $d_id = !empty($input['drug_id']) ? $input['drug_id'] : uniqid('D');
                    $d_name = !empty($input['drug_name']) ? $input['drug_name'] : $d_id;
                    
                    // Check if drug exists
                    $stmt = $db->prepare("SELECT idx FROM drugs WHERE drug_id = ?");
                    $stmt->execute([$d_id]);
                    $drug_idx = $stmt->fetchColumn();
                    
                    if ($drug_idx === false) {
                        $maxDIdx = $db->query("SELECT MAX(idx) FROM drugs")->fetchColumn();
                        $drug_idx = $maxDIdx !== false ? (int)$maxDIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO drugs (dataset, drug_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $d_id, $d_name, $drug_idx]);
                    }
                    
                    // Link drug and protein
                    $stmt = $db->prepare("INSERT IGNORE INTO drug_protein_associations (dataset, drug_idx, protein_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $drug_idx, $protein_idx]);
                }
                
                // 3. Add Disease & Link (If provided)
                if (!empty($input['disease_id']) || !empty($input['disease_name'])) {
                    $d_id = !empty($input['disease_id']) ? $input['disease_id'] : uniqid('DIS');
                    $d_name = !empty($input['disease_name']) ? $input['disease_name'] : $d_id;
                    
                    // Check if disease exists
                    $stmt = $db->prepare("SELECT idx FROM diseases WHERE disease_id = ?");
                    $stmt->execute([$d_id]);
                    $disease_idx = $stmt->fetchColumn();
                    
                    if ($disease_idx === false) {
                        $maxDIdx = $db->query("SELECT MAX(idx) FROM diseases")->fetchColumn();
                        $disease_idx = $maxDIdx !== false ? (int)$maxDIdx + 1 : 0;
                        $stmt = $db->prepare("INSERT INTO diseases (dataset, disease_id, name, idx) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$dataset, $d_id, $d_name, $disease_idx]);
                    }
                    
                    // Link protein and disease
                    $stmt = $db->prepare("INSERT IGNORE INTO protein_disease_associations (dataset, protein_idx, disease_idx) VALUES (?, ?, ?)");
                    $stmt->execute([$dataset, $protein_idx, $disease_idx]);
                }
                
                $db->commit();
                jsonResponse(['success' => true]);
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;
            
        case 'delete_drug':
            $id = $input['id'] ?? 0;
            $stmt = $db->prepare("SELECT idx, dataset FROM drugs WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $db->prepare("DELETE FROM known_associations WHERE dataset = ? AND drug_idx = ?")->execute([$item['dataset'], $item['idx']]);
                
                try {
                    $db->prepare("DELETE FROM drug_protein_associations WHERE dataset = ? AND drug_idx = ?")->execute([$item['dataset'], $item['idx']]);
                } catch (PDOException $e) {} // Bỏ qua nếu bảng chưa tồn tại
                
                $db->prepare("DELETE FROM drugs WHERE id = ?")->execute([$id]);
            }
            jsonResponse(['success' => true]);
            break;
            
        case 'delete_disease':
            $id = $input['id'] ?? 0;
            $stmt = $db->prepare("SELECT idx, dataset FROM diseases WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $db->prepare("DELETE FROM known_associations WHERE dataset = ? AND disease_idx = ?")->execute([$item['dataset'], $item['idx']]);
                
                try {
                    $db->prepare("DELETE FROM protein_disease_associations WHERE dataset = ? AND disease_idx = ?")->execute([$item['dataset'], $item['idx']]);
                } catch (PDOException $e) {}
                
                $db->prepare("DELETE FROM diseases WHERE id = ?")->execute([$id]);
            }
            jsonResponse(['success' => true]);
            break;

        case 'delete_protein':
            $id = $input['id'] ?? 0;
            $stmt = $db->prepare("SELECT idx, dataset FROM proteins WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                try {
                    $db->prepare("DELETE FROM drug_protein_associations WHERE dataset = ? AND protein_idx = ?")->execute([$item['dataset'], $item['idx']]);
                    $db->prepare("DELETE FROM protein_disease_associations WHERE dataset = ? AND protein_idx = ?")->execute([$item['dataset'], $item['idx']]);
                } catch (PDOException $e) {}
                
                $db->prepare("DELETE FROM proteins WHERE id = ?")->execute([$id]);
            }
            jsonResponse(['success' => true]);
            break;
            
        case 'delete_log':
            $stmt = $db->prepare("DELETE FROM predictions WHERE id = ?");
            $stmt->execute([$input['id'] ?? 0]);
            jsonResponse(['success' => true]);
            break;
            
        case 'delete_user':
            $id = $input['id'] ?? 0;
            if ($id == $_SESSION['user_id']) {
                jsonResponse(['error' => 'Không thể xóa chính tài khoản của bạn'], 400);
            }
            // Delete predictions first due to foreign key
            $stmt1 = $db->prepare("DELETE FROM predictions WHERE user_id = ?");
            $stmt1->execute([$id]);
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            jsonResponse(['success' => true]);
            break;
            
        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}
} catch (PDOException $e) {
    jsonResponse(['error' => 'Lỗi database: ' . $e->getMessage()], 500);
}
?>
