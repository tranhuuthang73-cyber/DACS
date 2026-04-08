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
    jsonResponse($result);
} else {
    jsonResponse(['error' => 'Action không hợp lệ'], 400);
}
?>
