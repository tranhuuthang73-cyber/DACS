<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$topK = $input['top_k'] ?? 10;
$dataset = $input['dataset'] ?? 'C-dataset';

$db = getDB(); // may be null if DB not set up yet

/** Helper: fetch a single column safely, return $default on failure */
function dbFetchOne($db, $sql, $params, $default = null) {
    if ($db === null) return $default;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn() ?: $default;
    } catch (PDOException $e) { return $default; }
}

/** Helper: fetch one row safely */
function dbFetchRow($db, $sql, $params) {
    if ($db === null) return null;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) { return null; }
}

/** Helper: execute a statement safely (e.g. INSERT) */
function dbExec($db, $sql, $params) {
    if ($db === null) return;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
    } catch (PDOException $e) { /* ignore */ }
}


if ($type === 'drug_to_disease') {
    $drugIdx = $input['drug_idx'] ?? null;
    if ($drugIdx === null) jsonResponse(['error' => 'Thiếu drug_idx'], 400);
    
    // Get drug name
    $drug = dbFetchRow($db, "SELECT * FROM drugs WHERE dataset = ? AND idx = ?", [$dataset, $drugIdx]);
    $queryName = $drug ? $drug['name'] : "Drug #$drugIdx";
    
    // Call AI server
    $aiResult = callAI('/predict/drug', ['dataset' => $dataset, 'drug_idx' => (int)$drugIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    // Enrich with disease names
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $disease = dbFetchRow($db, "SELECT * FROM diseases WHERE dataset = ? AND idx = ?", [$dataset, $p['disease_idx']]);
        if ($disease) {
            $p['disease_id'] = $disease['disease_id'];
            $p['disease_name'] = $disease['name'];
        } else {
            $p['disease_name'] = "Disease #" . $p['disease_idx'];
        }
    }
    
    // Also find associated proteins
    $proteins = $aiResult['proteins'] ?? [];
    foreach ($proteins as &$pr) {
        $pr['name'] = dbFetchOne($db, "SELECT name FROM proteins WHERE dataset = ? AND idx = ?", [$dataset, $pr['protein_idx']], "Protein #".$pr['protein_idx']);
    }
    
    // Save history
    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'drug_to_disease', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'proteins' => $proteins, 'query_name' => $queryName]);
    
} elseif ($type === 'disease_to_drug') {
    $diseaseIdx = $input['disease_idx'] ?? null;
    if ($diseaseIdx === null) jsonResponse(['error' => 'Thiếu disease_idx'], 400);
    
    $disease = dbFetchRow($db, "SELECT * FROM diseases WHERE dataset = ? AND idx = ?", [$dataset, $diseaseIdx]);
    $queryName = $disease ? $disease['name'] : "Disease #$diseaseIdx";
    
    $aiResult = callAI('/predict/disease', ['dataset' => $dataset, 'disease_idx' => (int)$diseaseIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $predictions = $aiResult['predictions'] ?? [];
    foreach ($predictions as &$p) {
        $drug = dbFetchRow($db, "SELECT * FROM drugs WHERE dataset = ? AND idx = ?", [$dataset, $p['drug_idx']]);
        if ($drug) {
            $p['drug_id'] = $drug['drug_id'];
            $p['drug_name'] = $drug['name'];
        } else {
            $p['drug_name'] = "Drug #" . $p['drug_idx'];
        }
    }
    
    // Also find associated proteins
    $proteins = $aiResult['proteins'] ?? [];
    foreach ($proteins as &$pr) {
        $pr['name'] = dbFetchOne($db, "SELECT name FROM proteins WHERE dataset = ? AND idx = ?", [$dataset, $pr['protein_idx']], "Protein #".$pr['protein_idx']);
    }
    
    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'disease_to_drug', $queryName, json_encode($predictions)]);
    
    jsonResponse(['predictions' => $predictions, 'proteins' => $proteins, 'query_name' => $queryName]);
    
} elseif ($type === 'protein_to_any') {
    $proteinIdx = $input['protein_idx'] ?? null;
    if ($proteinIdx === null) jsonResponse(['error' => 'Thiếu protein_idx'], 400);
    
    $protein = dbFetchRow($db, "SELECT * FROM proteins WHERE dataset = ? AND idx = ?", [$dataset, $proteinIdx]);
    $queryName = $protein ? $protein['name'] : "Protein #$proteinIdx";
    
    $aiResult = callAI('/predict/protein', ['dataset' => $dataset, 'protein_idx' => (int)$proteinIdx, 'top_k' => (int)$topK]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $mediated = $aiResult['mediated_predictions'] ?? [];
    foreach ($mediated as &$m) {
        $m['drug_name']    = dbFetchOne($db, "SELECT name FROM drugs WHERE dataset = ? AND idx = ?",    [$dataset, $m['drug_idx']],    "Drug #".$m['drug_idx']);
        $m['disease_name'] = dbFetchOne($db, "SELECT name FROM diseases WHERE dataset = ? AND idx = ?", [$dataset, $m['disease_idx']], "Disease #".$m['disease_idx']);
    }
    
    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'protein_to_any', $queryName, json_encode($mediated)]);
    
    jsonResponse(['mediated_predictions' => $mediated, 'query_name' => $queryName]);
    
} elseif ($type === 'triplet') {
    $drugIdx    = $input['drug_idx'] ?? null;
    $proteinIdx = $input['protein_idx'] ?? null;
    $diseaseIdx = $input['disease_idx'] ?? null;
    
    if ($drugIdx === null || $proteinIdx === null || $diseaseIdx === null) {
        jsonResponse(['error' => 'Thiếu thông tin cho tổ hợp 3'], 400);
    }
    
    // Get names for results
    $drugName    = dbFetchOne($db, "SELECT name FROM drugs WHERE dataset = ? AND idx = ?",    [$dataset, $drugIdx],    "Drug #$drugIdx");
    $proteinName = dbFetchOne($db, "SELECT name FROM proteins WHERE dataset = ? AND idx = ?", [$dataset, $proteinIdx], "Protein #$proteinIdx");
    $diseaseName = dbFetchOne($db, "SELECT name FROM diseases WHERE dataset = ? AND idx = ?", [$dataset, $diseaseIdx], "Disease #$diseaseIdx");
    
    $aiResult = callAI('/predict/triplet', [
        'dataset'     => $dataset,
        'drug_idx'    => (int)$drugIdx,
        'protein_idx' => (int)$proteinIdx,
        'disease_idx' => (int)$diseaseIdx
    ]);
    
    if (isset($aiResult['error'])) {
        jsonResponse(['error' => $aiResult['error']], 500);
    }
    
    $aiResult['drug_name']    = $drugName;
    $aiResult['protein_name'] = $proteinName;
    $aiResult['disease_name'] = $diseaseName;
    
    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'triplet', "$drugName-$proteinName-$diseaseName", json_encode($aiResult)]);
    
    jsonResponse($aiResult);
    
} else {
    jsonResponse(['error' => 'type không hợp lệ'], 400);
}
?>
