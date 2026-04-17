<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$topK = $input['top_k'] ?? 10;
$dataset = $input['dataset'] ?? 'C-dataset';

$db = getDB();

if ($type === 'drug_to_disease') {
    $drugIdx = $input['drug_idx'] ?? null;
    if ($drugIdx === null) jsonResponse(['error' => 'Thiếu drug_idx'], 400);
    
    // Get drug name
    $stmt = $db->prepare("SELECT * FROM drugs WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $drugIdx]);
    $drug = $stmt->fetch(PDO::FETCH_ASSOC);
    $queryName = $drug ? $drug['name'] : "Drug #$drugIdx";
    
    // Call AI server
    $aiResult = callAI('/predict/drug', ['dataset' => $dataset, 'drug_idx' => (int)$drugIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    // Enrich with disease names
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $stmt = $db->prepare("SELECT * FROM diseases WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $p['disease_idx']]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            $p['disease_id'] = $disease['disease_id'];
            $p['disease_name'] = $disease['name'];
        }
    }
    
    // Also find associated proteins
    $proteins = $aiResult['proteins'] ?? [];
    foreach ($proteins as &$pr) {
        $stmt = $db->prepare("SELECT name FROM proteins WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $pr['protein_idx']]);
        $prName = $stmt->fetchColumn();
        $pr['name'] = $prName ?: "Protein #".$pr['protein_idx'];
    }
    
    // Save history
    $stmt = $db->prepare("INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, 'drug_to_disease', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'proteins' => $proteins, 'query_name' => $queryName]);
    
} elseif ($type === 'disease_to_drug') {
    $diseaseIdx = $input['disease_idx'] ?? null;
    if ($diseaseIdx === null) jsonResponse(['error' => 'Thiếu disease_idx'], 400);
    
    $stmt = $db->prepare("SELECT * FROM diseases WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $diseaseIdx]);
    $disease = $stmt->fetch(PDO::FETCH_ASSOC);
    $queryName = $disease ? $disease['name'] : "Disease #$diseaseIdx";
    
    $aiResult = callAI('/predict/disease', ['dataset' => $dataset, 'disease_idx' => (int)$diseaseIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $stmt = $db->prepare("SELECT * FROM drugs WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $p['drug_idx']]);
        $drug = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($drug) {
            $p['drug_id'] = $drug['drug_id'];
            $p['drug_name'] = $drug['name'];
        }
    }
    
    // Also find associated proteins
    $proteins = $aiResult['proteins'] ?? [];
    foreach ($proteins as &$pr) {
        $stmt = $db->prepare("SELECT name FROM proteins WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $pr['protein_idx']]);
        $prName = $stmt->fetchColumn();
        $pr['name'] = $prName ?: "Protein #".$pr['protein_idx'];
    }
    
    $stmt = $db->prepare("INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, 'disease_to_drug', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'proteins' => $proteins, 'query_name' => $queryName]);
    
} elseif ($type === 'protein_to_any') {
    $proteinIdx = $input['protein_idx'] ?? null;
    if ($proteinIdx === null) jsonResponse(['error' => 'Thiếu protein_idx'], 400);
    
    $stmt = $db->prepare("SELECT * FROM proteins WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $proteinIdx]);
    $protein = $stmt->fetch(PDO::FETCH_ASSOC);
    $queryName = $protein ? $protein['name'] : "Protein #$proteinIdx";
    
    $aiResult = callAI('/predict/protein', ['dataset' => $dataset, 'protein_idx' => (int)$proteinIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $mediated = $aiResult['mediated_predictions'] ?? [];
    foreach ($mediated as &$m) {
        $stmt = $db->prepare("SELECT name FROM drugs WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $m['drug_idx']]);
        $m['drug_name'] = $stmt->fetchColumn() ?: "Drug #".$m['drug_idx'];
        
        $stmt = $db->prepare("SELECT name FROM diseases WHERE dataset = ? AND idx = ?");
        $stmt->execute([$dataset, $m['disease_idx']]);
        $m['disease_name'] = $stmt->fetchColumn() ?: "Disease #".$m['disease_idx'];
    }
    
    $stmt = $db->prepare("INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, 'protein_to_any', $queryName, json_encode($mediated)]);
    
    jsonResponse(['mediated_predictions' => $mediated, 'query_name' => $queryName]);
    
} elseif ($type === 'triplet') {
    $drugIdx = $input['drug_idx'] ?? null;
    $proteinIdx = $input['protein_idx'] ?? null;
    $diseaseIdx = $input['disease_idx'] ?? null;
    
    if ($drugIdx === null || $proteinIdx === null || $diseaseIdx === null) {
        jsonResponse(['error' => 'Thiếu thông tin cho tổ hợp 3'], 400);
    }
    
    // Get names for results
    $stmt = $db->prepare("SELECT name FROM drugs WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $drugIdx]);
    $drugName = $stmt->fetchColumn() ?: "Drug #$drugIdx";
    
    $stmt = $db->prepare("SELECT name FROM proteins WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $proteinIdx]);
    $proteinName = $stmt->fetchColumn() ?: "Protein #$proteinIdx";
    
    $stmt = $db->prepare("SELECT name FROM diseases WHERE dataset = ? AND idx = ?");
    $stmt->execute([$dataset, $diseaseIdx]);
    $diseaseName = $stmt->fetchColumn() ?: "Disease #$diseaseIdx";
    
    $aiResult = callAI('/predict/triplet', [
        'dataset' => $dataset,
        'drug_idx' => (int)$drugIdx,
        'protein_idx' => (int)$proteinIdx,
        'disease_idx' => (int)$diseaseIdx
    ]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $aiResult['drug_name'] = $drugName;
    $aiResult['protein_name'] = $proteinName;
    $aiResult['disease_name'] = $diseaseName;
    
    $stmt = $db->prepare("INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, 'triplet', "$drugName-$proteinName-$diseaseName", json_encode($aiResult)]);
    
    jsonResponse($aiResult);
    
} else {
    jsonResponse(['error' => 'type không hợp lệ'], 400);
}
?>
