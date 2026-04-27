<?php
require_once __DIR__ . '/../includes/config.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$dataset = $input['dataset'] ?? $_GET['dataset'] ?? 'C-dataset';

// Public endpoints (no login required)
if ($action === 'graph_stats') {
    $dataset = $_GET['dataset'] ?? 'C-dataset';
    $dataDir = __DIR__ . "/../../data/$dataset/";
    $maxDrugs = intval($_GET['max_drugs'] ?? $_GET['max'] ?? 25);
    $maxDiseases = intval($_GET['max_diseases'] ?? $_GET['max'] ?? 25);
    $maxProteins = intval($_GET['max_proteins'] ?? $_GET['max'] ?? 25);

    // ── 1. Load Drug names (index → name) ──
    $drugNames = [];
    $drugFile = $dataDir . 'DrugInformation.csv';
    if (file_exists($drugFile)) {
        $h = fopen($drugFile, 'r');
        fgetcsv($h); // skip header
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $drugNames[$idx] = $row[1] ?? "Drug_$idx"; // name col
            $idx++;
        }
        fclose($h);
    }

    // ── 2. Load Protein names (index → name) ──
    $proteinNames = [];
    $protFile = $dataDir . 'ProteinInformation.csv';
    if (file_exists($protFile)) {
        $h = fopen($protFile, 'r');
        fgetcsv($h);
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $proteinNames[$idx] = $row[1] ?? $row[0] ?? "Protein_$idx";
            $idx++;
        }
        fclose($h);
    }

    // ── 3. Load Disease names from AllNode.csv (diseases start after drugs) ──
    $allNodes = [];
    $allNodeFile = $dataDir . 'AllNode.csv';
    if (file_exists($allNodeFile)) {
        $h = fopen($allNodeFile, 'r');
        while (($row = fgetcsv($h)) !== false) {
            $allNodes[] = trim($row[0]);
        }
        fclose($h);
    }
    $numDrugs = count($drugNames);
    $diseaseNames = []; // disease global_idx → name
    for ($i = $numDrugs; $i < count($allNodes); $i++) {
        $diseaseNames[$i - $numDrugs] = $allNodes[$i];
    }

    // ── 4. Load Drug-Disease edges (sample) ──
    $ddEdges = []; // [[drug_idx, disease_idx], ...]
    $ddFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($ddFile)) {
        $h = fopen($ddFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2)
                $ddEdges[] = [(int) $row[0], (int) $row[1]];
        }
        fclose($h);
    }

    // ── 5. Load Drug-Protein edges ──
    $dpEdges = [];
    $dpFile = $dataDir . 'DrugProteinAssociationNumber.csv';
    if (file_exists($dpFile)) {
        $h = fopen($dpFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2)
                $dpEdges[] = [(int) $row[0], (int) $row[1]];
        }
        fclose($h);
    }

    // ── 6. Load Protein-Disease edges ──
    $pdEdges = [];
    $pdFile = $dataDir . 'ProteinDiseaseAssociationNumber.csv';
    if (file_exists($pdFile)) {
        $h = fopen($pdFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2)
                $pdEdges[] = [(int) $row[1], (int) $row[0]]; // protein, disease
        }
        fclose($h);
    }

    // ── 7. Select top nodes that appear in edges ──
    // Get most-connected drugs
    $drugDegree = [];
    foreach ($ddEdges as [$d, $_]) {
        $drugDegree[$d] = ($drugDegree[$d] ?? 0) + 1;
    }
    foreach ($dpEdges as [$d, $_]) {
        $drugDegree[$d] = ($drugDegree[$d] ?? 0) + 1;
    }
    arsort($drugDegree);
    $topDrugs = array_slice(array_keys($drugDegree), 0, $maxDrugs, true);

    // Get most-connected diseases
    $disDegree = [];
    foreach ($ddEdges as [$_, $di]) {
        $disDegree[$di] = ($disDegree[$di] ?? 0) + 1;
    }
    foreach ($pdEdges as [$_, $di]) {
        $disDegree[$di] = ($disDegree[$di] ?? 0) + 1;
    }
    arsort($disDegree);
    $topDiseases = array_slice(array_keys($disDegree), 0, $maxDiseases, true);

    // Get most-connected proteins (that link selected drugs/diseases)
    $topDrugSet = array_flip($topDrugs);
    $topDisSet = array_flip($topDiseases);
    $protDegree = [];
    foreach ($dpEdges as [$d, $p]) {
        if (isset($topDrugSet[$d]))
            $protDegree[$p] = ($protDegree[$p] ?? 0) + 1;
    }
    foreach ($pdEdges as [$p, $di]) {
        if (isset($topDisSet[$di]))
            $protDegree[$p] = ($protDegree[$p] ?? 0) + 1;
    }
    arsort($protDegree);
    $topProteins = array_slice(array_keys($protDegree), 0, $maxProteins, true);
    $topProtSet = array_flip($topProteins);

    // ── 8. Build node list ──
    $nodes = [];
    foreach ($topDrugs as $idx) {
        $nodes[] = ['id' => "drug_$idx", 'type' => 'drug', 'name' => $drugNames[$idx] ?? ("Drug_$idx"), 'idx' => $idx];
    }
    foreach ($topDiseases as $idx) {
        $nodes[] = ['id' => "dis_$idx", 'type' => 'disease', 'name' => $diseaseNames[$idx] ?? ("Dis_$idx"), 'idx' => $idx];
    }
    foreach ($topProteins as $idx) {
        $nodes[] = ['id' => "prot_$idx", 'type' => 'protein', 'name' => $proteinNames[$idx] ?? ("Prot_$idx"), 'idx' => $idx];
    }

    // ── 9. Build edge list (only between selected nodes) ──
    $edges = [];
    // Drug-Disease
    foreach ($ddEdges as [$d, $di]) {
        if (isset($topDrugSet[$d]) && isset($topDisSet[$di])) {
            $edges[] = ['source' => "drug_$d", 'target' => "dis_$di", 'type' => 'dd'];
        }
    }
    // Drug-Protein
    foreach ($dpEdges as [$d, $p]) {
        if (isset($topDrugSet[$d]) && isset($topProtSet[$p])) {
            $edges[] = ['source' => "drug_$d", 'target' => "prot_$p", 'type' => 'dp'];
        }
    }
    // Protein-Disease
    foreach ($pdEdges as [$p, $di]) {
        if (isset($topProtSet[$p]) && isset($topDisSet[$di])) {
            $edges[] = ['source' => "prot_$p", 'target' => "dis_$di", 'type' => 'pd'];
        }
    }

    jsonResponse([
        'nodes' => $nodes,
        'edges' => $edges,
        'stats' => ['drugs' => count($topDrugs), 'diseases' => count($topDiseases), 'proteins' => count($topProteins), 'edges' => count($edges)]
    ]);
}


if ($action === 'training_curve') {
    $fold = $_GET['fold'] ?? '0';
    $dataset = $_GET['dataset'] ?? 'C-dataset';
    $dir = __DIR__ . "/../../Result/$dataset/AMNTDDA/";

    $epochs = [];
    $aucs = [];
    $auprs = [];

    if ($fold === 'all') {
        // Mean across all folds from 10_fold_results CSV
        $files = glob($dir . "10_fold_results_*.csv");
        if ($files) {
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
            if (($h = fopen($files[0], 'r')) !== false) {
                $hdr = fgetcsv($h);
                while (($row = fgetcsv($h)) !== false) {
                    if (isset($row[0]) && strpos($row[0], 'Fold') !== false) {
                        $r = array_combine($hdr, $row);
                        $epochs[] = intval($r['Best_Epoch'] ?? 0);
                        $aucs[] = floatval($r['AUC'] ?? 0);
                        $auprs[] = floatval($r['AUPR'] ?? 0);
                    }
                }
                fclose($h);
            }
        }
    } else {
        $file = $dir . "fold_{$fold}.csv";
        if (file_exists($file) && ($h = fopen($file, 'r')) !== false) {
            $hdr = fgetcsv($h);
            while (($row = fgetcsv($h)) !== false) {
                $r = array_combine($hdr, $row);
                $epochs[] = intval($r['Epoch'] ?? $r['epoch'] ?? 0);
                $aucs[] = floatval($r['AUC'] ?? $r['auc'] ?? 0);
                $auprs[] = floatval($r['AUPR'] ?? $r['aupr'] ?? 0);
            }
            fclose($h);
        }
    }
    jsonResponse(['epochs' => $epochs, 'auc' => $aucs, 'aupr' => $auprs, 'fold' => $fold, 'dataset' => $dataset]);
}

if ($action === 'model_performance') {
    $dir = __DIR__ . "/../../Result/$dataset/AMNTDDA";
    $files = glob($dir . "/10_fold_results_*.csv");
    if (!$files) {
        jsonResponse(['error' => 'Chưa có dữ liệu huấn luyện 10-fold cho dataset này.'], 404);
    }

    // Sắp xếp lấy file mới nhất
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $latestFile = $files[0];

    $stats = [];
    if (($handle = fopen($latestFile, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($data[0] === 'Mean') {
                foreach ($header as $i => $col) {
                    if ($col && isset($data[$i])) {
                        $stats[$col] = $data[$i];
                    }
                }
                break;
            }
        }
        fclose($handle);
    }

    if (empty($stats)) {
        jsonResponse(['error' => 'Không tìm thấy dòng Mean trong file kết quả.'], 500);
    }

    jsonResponse([
        'dataset' => $dataset,
        'filename' => basename($latestFile),
        'timestamp' => date("Y-m-d H:i:s", filemtime($latestFile)),
        'stats' => $stats
    ]);
}

if ($action === 'predict_protein') {
    if (!isLoggedIn())
        jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    $res = callAI('/predict/protein', $input);
    if (isset($res['error']))
        jsonResponse($res, 500);

    // Enrich with names
    $db = getDB();
    if (isset($res['drugs'])) {
        foreach ($res['drugs'] as &$d) {
            $s = $db->prepare("SELECT name, drug_id FROM drugs WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $d['drug_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $d['drug_name'] = $item ? $item['name'] : "Drug #" . $d['drug_idx'];
        }
    }
    if (isset($res['diseases'])) {
        foreach ($res['diseases'] as &$di) {
            $s = $db->prepare("SELECT name, disease_id FROM diseases WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $di['disease_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $di['disease_name'] = $item ? $item['name'] : "Disease #" . $di['disease_idx'];
        }
    }
    if (isset($res['mediated_predictions'])) {
        foreach ($res['mediated_predictions'] as &$m) {
            $s = $db->prepare("SELECT name FROM diseases WHERE dataset = ? AND idx = ?");
            $s->execute([$dataset, $m['disease_idx']]);
            $item = $s->fetch(PDO::FETCH_ASSOC);
            $m['disease_name'] = $item ? $item['name'] : "Disease #" . $m['disease_idx'];
        }
    }
    jsonResponse($res);
}

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

if ($action === 'latent_path') {
    if (!isLoggedIn())
        jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    $result = callAI('/latent_path', array_merge($input, ['dataset' => $dataset]));
    if (isset($result['error']) && !isset($result['bridge_proteins'])) {
        jsonResponse($result, 500);
    }

    // Enrich protein names từ database
    $db = getDB();
    if (isset($result['bridge_proteins']) && $db) {
        foreach ($result['bridge_proteins'] as &$pr) {
            $stmt = $db->prepare("SELECT name FROM proteins WHERE dataset = ? AND idx = ?");
            $stmt->execute([$dataset, $pr['protein_idx']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $pr['protein_name'] = $row ? $row['name'] : "Protein #" . $pr['protein_idx'];
        }
    }
    jsonResponse($result);
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
    if (!isset($input['drug_idx']))
        jsonResponse(['error' => 'Thiếu drug_idx'], 400);
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
    if ($ch === false) {
        jsonResponse(['error' => 'AI Server offline'], 503);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

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
    } catch (Exception $e) {
    }

    $stmt = $db->prepare("INSERT INTO expert_validations (user_id, dataset, drug_idx, disease_idx, validation_type, note) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $dataset, $drugIdx, $diseaseIdx, $validationType, $note]);

    jsonResponse(['success' => true, 'message' => $validationType === 'confirm' ? 'Đã xác nhận lâm sàng' : 'Đã báo cáo sai lệch']);
} elseif ($action === 'pubchem') {
    $name = $_GET['name'] ?? '';
    if (!$name)
        jsonResponse(['error' => 'Thiếu tên'], 400);
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($name) . "/JSON";
    $response = @file_get_contents($url);
    if ($response === false)
        jsonResponse(['error' => 'Không tìm thấy trên PubChem'], 404);
    echo $response;
    exit;
} else {
    jsonResponse(['error' => 'Action không hợp lệ'], 400);
}
?>