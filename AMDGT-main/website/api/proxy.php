<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'landscape') {
    // callAI handles HTTP POST to ai_server
    // but our landscape is a GET endpoint on ai_server.
    // wait, callAI hardcodes CURLOPT_POST to true.
    // Let's manually fetch or modify callAI?
    // call_ai defaults to POST. So I will just use file_get_contents for the GET landscape endpoint.
    $url = AI_SERVER_URL . '/landscape/disease';
    $response = @file_get_contents($url);
    if ($response === false) {
        jsonResponse(['error' => 'Không thể kết nối AI Server'], 500);
    }
    echo $response;
    exit;
} elseif ($action === 'similar') {
    if (!isset($input['drug_idx'])) jsonResponse(['error' => 'Thiếu drug_idx'], 400);
    $result = callAI('/similar_drugs', $input);
    
    // Lấy tên thuốc từ Database
    if (isset($result['similar_drugs'])) {
        $db = getDB();
        foreach ($result['similar_drugs'] as &$drug) {
            $stmt = $db->prepare("SELECT name FROM drugs WHERE idx = ?");
            $stmt->execute([$drug['drug_idx']]);
            $dbDrug = $stmt->fetch(PDO::FETCH_ASSOC);
            $drug['drug_name'] = $dbDrug ? $dbDrug['name'] : "Thuốc " . $drug['drug_idx'];
        }
    }
    
    jsonResponse($result);
} elseif ($action === 'explain') {
    if (!isset($input['drug_idx']) || !isset($input['disease_idx'])) {
        jsonResponse(['error' => 'Thiếu tham số'], 400);
    }
    $result = callAI('/explain', $input);
    
    // Enrich similar drugs with names
    if (isset($result['similar_drugs'])) {
        $db = getDB();
        foreach ($result['similar_drugs'] as &$drug) {
            $stmt = $db->prepare("SELECT name, drug_id FROM drugs WHERE idx = ?");
            $stmt->execute([$drug['drug_idx']]);
            $dbDrug = $stmt->fetch(PDO::FETCH_ASSOC);
            $drug['drug_name'] = $dbDrug ? $dbDrug['name'] : "Thuốc " . $drug['drug_idx'];
            $drug['drug_id'] = $dbDrug ? $dbDrug['drug_id'] : '';
        }
    }
    
    // Enrich similar diseases with names
    if (isset($result['similar_diseases'])) {
        $db = getDB();
        foreach ($result['similar_diseases'] as &$disease) {
            $stmt = $db->prepare("SELECT name, disease_id FROM diseases WHERE idx = ?");
            $stmt->execute([$disease['disease_idx']]);
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
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS expert_validations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, drug_idx INT, disease_idx INT,
        validation_type VARCHAR(20), note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    
    $stmt = $db->prepare("INSERT INTO expert_validations (user_id, drug_idx, disease_idx, validation_type, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $drugIdx, $diseaseIdx, $validationType, $note]);
    
    jsonResponse(['success' => true, 'message' => $validationType === 'confirm' ? 'Đã xác nhận lâm sàng' : 'Đã báo cáo sai lệch']);
} else {
    jsonResponse(['error' => 'Action không hợp lệ'], 400);
}
?>
