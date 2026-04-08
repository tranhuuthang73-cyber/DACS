<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$topK = $input['top_k'] ?? 10;

$db = getDB();

if ($type === 'drug_to_disease') {
    $drugIdx = $input['drug_idx'] ?? null;
    if ($drugIdx === null) jsonResponse(['error' => 'Thiếu drug_idx'], 400);
    
    // Get drug name
    $stmt = $db->prepare("SELECT * FROM drugs WHERE idx = ?");
    $stmt->execute([$drugIdx]);
    $drug = $stmt->fetch(PDO::FETCH_ASSOC);
    $queryName = $drug ? $drug['name'] : "Drug #$drugIdx";
    
    // Call AI server
    $aiResult = callAI('/predict/drug', ['drug_idx' => (int)$drugIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    // Enrich with disease names
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $stmt = $db->prepare("SELECT * FROM diseases WHERE idx = ?");
        $stmt->execute([$p['disease_idx']]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            $p['disease_id'] = $disease['disease_id'];
            $p['disease_name'] = $disease['name'];
        }
    }
    
    // Save history
    $stmt = $db->prepare("INSERT INTO predictions (user_id, query_type, query_value, results) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'drug_to_disease', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'query_name' => $queryName]);
    
} elseif ($type === 'disease_to_drug') {
    $diseaseIdx = $input['disease_idx'] ?? null;
    if ($diseaseIdx === null) jsonResponse(['error' => 'Thiếu disease_idx'], 400);
    
    $stmt = $db->prepare("SELECT * FROM diseases WHERE idx = ?");
    $stmt->execute([$diseaseIdx]);
    $disease = $stmt->fetch(PDO::FETCH_ASSOC);
    $queryName = $disease ? $disease['name'] : "Disease #$diseaseIdx";
    
    $aiResult = callAI('/predict/disease', ['disease_idx' => (int)$diseaseIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $stmt = $db->prepare("SELECT * FROM drugs WHERE idx = ?");
        $stmt->execute([$p['drug_idx']]);
        $drug = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($drug) {
            $p['drug_id'] = $drug['drug_id'];
            $p['drug_name'] = $drug['name'];
        }
    }
    
    $stmt = $db->prepare("INSERT INTO predictions (user_id, query_type, query_value, results) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'disease_to_drug', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'query_name' => $queryName]);
    
} else {
    jsonResponse(['error' => 'type không hợp lệ'], 400);
}
?>
