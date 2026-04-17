<?php
require_once __DIR__ . '/../includes/config.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$dataset = $input['dataset'] ?? $_GET['dataset'] ?? 'C-dataset';

// Public endpoints (no login required)
if ($action === 'graph_stats') {
    $url = AI_SERVER_URL . '/graph_stats?dataset=' . urlencode($dataset);
    $response = @file_get_contents($url);
    if ($response === false) jsonResponse(['error' => 'Không thể kết nối AI Server'], 500);
    echo $response;
    exit;
}

if ($action === 'ablation_stats') {
    $url = AI_SERVER_URL . '/ablation_stats';
    $response = @file_get_contents($url);
    if ($response === false) jsonResponse(['error' => 'Không thể kết nối AI Server'], 500);
    echo $response;
    exit;
}

if ($action === 'predict_protein') {
    if (!isLoggedIn()) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    $res = callAI('/predict/protein', $input);
    if (isset($res['error'])) jsonResponse($res, 500);
    
    // Enrich with names
    $db = getDB();
    if (isset($res['drugs'])) {
        foreach ($res['drugs'] as &$d) {
            $s = $db->prepare("SELECT name, drug_id FROM drugs WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $d['drug_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $d['drug_name'] = $item ? $item['name'] : "Drug #".$d['drug_idx'];
        }
    }
    if (isset($res['diseases'])) {
        foreach ($res['diseases'] as &$di) {
            $s = $db->prepare("SELECT name, disease_id FROM diseases WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $di['disease_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $di['disease_name'] = $item ? $item['name'] : "Disease #".$di['disease_idx'];
        }
    }
    if (isset($res['mediated_predictions'])) {
        foreach ($res['mediated_predictions'] as &$m) {
            $s = $db->prepare("SELECT name FROM diseases WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $m['disease_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $m['disease_name'] = $item ? $item['name'] : "Disease #".$m['disease_idx'];
        }
    }
    jsonResponse($res);
}

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

if ($action === 'landscape') {
    $url = AI_SERVER_URL . '/landscape/disease?dataset=' . urlencode($dataset);
    $response = @file_get_contents($url);
    if ($response === false) {
        jsonResponse(['error' => 'Không thể kết nối AI Server'], 500);
    }
    echo $response;
    exit;
} elseif ($action === 'similar') {
    if (!isset($input['drug_idx'])) jsonResponse(['error' => 'Thiếu drug_idx'], 400);
    $result = callAI('/similar_drugs', array_merge($input, ['dataset' => $dataset]));
    
    // Lấy tên thuốc từ Database
    if (isset($result['similar_drugs'])) {
        $db = getDB();
        foreach ($result['similar_drugs'] as &$drug) {
            $stmt = $db->prepare("SELECT name FROM drugs WHERE dataset = ? AND idx = ?");
            $stmt->execute([$dataset, $drug['drug_idx']]);
            $dbDrug = $stmt->fetch(PDO::FETCH_ASSOC);
            $drug['drug_name'] = $dbDrug ? $dbDrug['name'] : "Thuốc " . $drug['drug_idx'];
        }
    }
    
    jsonResponse($result);
} elseif ($action === 'explain') {
    if (!isset($input['drug_idx']) || !isset($input['disease_idx'])) {
        jsonResponse(['error' => 'Thiếu tham số'], 400);
    }
    $result = callAI('/explain', array_merge($input, ['dataset' => $dataset]));
    
    // Enrich similar drugs with names
    if (isset($result['similar_drugs'])) {
        $db = getDB();
        foreach ($result['similar_drugs'] as &$drug) {
            $stmt = $db->prepare("SELECT name, drug_id FROM drugs WHERE dataset = ? AND idx = ?");
            $stmt->execute([$dataset, $drug['drug_idx']]);
            $dbDrug = $stmt->fetch(PDO::FETCH_ASSOC);
            $drug['drug_name'] = $dbDrug ? $dbDrug['name'] : "Thuốc " . $drug['drug_idx'];
            $drug['drug_id'] = $dbDrug ? $dbDrug['drug_id'] : '';
        }
    }
    
    // Enrich similar diseases with names
    if (isset($result['similar_diseases'])) {
        $db = getDB();
        foreach ($result['similar_diseases'] as &$disease) {
            $stmt = $db->prepare("SELECT name, disease_id FROM diseases WHERE dataset = ? AND idx = ?");
            $stmt->execute([$dataset, $disease['disease_idx']]);
            $dbDisease = $stmt->fetch(PDO::FETCH_ASSOC);
            $disease['disease_name'] = $dbDisease ? $dbDisease['name'] : "Bệnh " . $disease['disease_idx'];
            $disease['disease_id'] = $dbDisease ? $dbDisease['disease_id'] : '';
        }
    }
    
    jsonResponse($result);
} elseif ($action === 'health') {
    // Health check for dashboard
    $ch = curl_init(AI_SERVER_URL . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        jsonResponse(json_decode($response, true) ?: ['status' => 'ok']);
    } else {
        jsonResponse(['error' => 'AI Server offline'], 503);
    }
} elseif ($action === 'validate') {
    // Expert validation - save feedback
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    }
    $drugIdx = $input['drug_idx'] ?? null;
    $diseaseIdx = $input['disease_idx'] ?? null;
    $validationType = $input['validation'] ?? '';
    $note = trim($input['note'] ?? '');
    
    if ($drugIdx === null || $diseaseIdx === null || !in_array($validationType, ['confirm', 'report'])) {
        jsonResponse(['error' => 'Tham số không hợp lệ'], 400);
    }
    
    $db = getDB();
    // Create table if not exists (add dataset column)
    $db->exec("CREATE TABLE IF NOT EXISTS expert_validations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, dataset VARCHAR(20) DEFAULT 'C-dataset',
        drug_idx INT, disease_idx INT,
        validation_type VARCHAR(20), note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    
    // Fallback migration for existing setups
    try {
        $db->exec("ALTER TABLE expert_validations ADD COLUMN dataset VARCHAR(20) DEFAULT 'C-dataset'");
    } catch (Exception $e) {}
    
    $stmt = $db->prepare("INSERT INTO expert_validations (user_id, dataset, drug_idx, disease_idx, validation_type, note) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, $drugIdx, $diseaseIdx, $validationType, $note]);
    
    jsonResponse(['success' => true, 'message' => $validationType === 'confirm' ? 'Đã xác nhận lâm sàng' : 'Đã báo cáo sai lệch']);
} elseif ($action === 'pubchem') {
    $name = $_GET['name'] ?? '';
    if (!$name) jsonResponse(['error' => 'Thiếu tên'], 400);
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($name) . "/JSON";
    $response = @file_get_contents($url);
    if ($response === false) jsonResponse(['error' => 'Không tìm thấy trên PubChem'], 404);
    echo $response;
    exit;
} else {
    jsonResponse(['error' => 'Action không hợp lệ'], 400);
}
?>
