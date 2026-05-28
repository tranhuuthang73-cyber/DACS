<?php
/**
 * AMDGT Data Validation Tool
 * Kiểm tra tính toàn vẹn và chính xác của dữ liệu trong tất cả datasets
 */
header('Content-Type: text/html; charset=utf-8');
$datasets = ['C-dataset', 'B-dataset', 'F-dataset'];
$dataRoot = __DIR__ . '/../data/';
$resultRoot = __DIR__ . '/../Result/';

function countCsvRows($file, $skipHeader = true) {
    if (!file_exists($file)) return -1;
    $count = 0;
    $h = fopen($file, 'r');
    if ($skipHeader) fgetcsv($h);
    while (fgetcsv($h) !== false) $count++;
    fclose($h);
    return $count;
}

function readCsvHeader($file) {
    if (!file_exists($file)) return [];
    $h = fopen($file, 'r');
    $header = fgetcsv($h);
    fclose($h);
    return $header ?: [];
}

function fileStatus($exists) {
    return $exists ? '✅' : '❌';
}

// Collect all results
$results = [];
foreach ($datasets as $ds) {
    $dir = $dataRoot . $ds . '/';
    $r = ['name' => $ds, 'errors' => [], 'warnings' => [], 'info' => []];

    // 1. Check required files
    $requiredFiles = [
        'DrugInformation.csv', 'DiseaseFeature.csv', 'AllNode.csv',
        'DrugDiseaseAssociationNumber.csv', 'DrugProteinAssociationNumber.csv',
        'ProteinDiseaseAssociationNumber.csv', 'ProteinInformation.csv',
        'DrugFingerprint.csv', 'DrugGIP.csv', 'DiseaseGIP.csv', 'DiseasePS.csv',
        'Drug_mol2vec.csv', 'Protein_ESM.csv', 'adj.csv', 'Alledge.csv'
    ];
    $fileChecks = [];
    foreach ($requiredFiles as $f) {
        $path = $dir . $f;
        $exists = file_exists($path);
        $rows = $exists ? countCsvRows($path) : -1;
        $size = $exists ? filesize($path) : 0;
        $header = $exists ? readCsvHeader($path) : [];
        $fileChecks[$f] = ['exists' => $exists, 'rows' => $rows, 'size' => $size, 'header' => $header];
        if (!$exists) $r['errors'][] = "File thiếu: $f";
    }
    $r['files'] = $fileChecks;

    // 2. Cross-validate counts
    $numDrugs = $fileChecks['DrugInformation.csv']['rows'];
    $numAllNodes = file_exists($dir . 'AllNode.csv') ? countCsvRows($dir . 'AllNode.csv', false) : 0;
    $numProteins = $fileChecks['ProteinInformation.csv']['rows'];
    $numDiseases = $numAllNodes - $numDrugs;
    $r['counts'] = [
        'drugs' => $numDrugs, 'diseases' => $numDiseases,
        'proteins' => $numProteins, 'allnodes' => $numAllNodes
    ];

    if ($numDiseases <= 0) {
        $r['errors'][] = "Số bệnh tính được <= 0 (AllNode=$numAllNodes - Drugs=$numDrugs)";
    }

    // 3. Validate Drug-Disease associations
    $ddFile = $dir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($ddFile)) {
        $h = fopen($ddFile, 'r'); fgetcsv($h);
        $maxDrug = -1; $maxDisease = -1; $ddCount = 0; $ddErrors = 0;
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) < 2) { $ddErrors++; continue; }
            $d = intval($row[0]); $di = intval($row[1]);
            if ($d > $maxDrug) $maxDrug = $d;
            if ($di > $maxDisease) $maxDisease = $di;
            if ($d < 0 || $d >= $numDrugs) $ddErrors++;
            if ($di < 0 || $di >= $numDiseases) $ddErrors++;
            $ddCount++;
        }
        fclose($h);
        $r['dd'] = ['count' => $ddCount, 'max_drug' => $maxDrug, 'max_disease' => $maxDisease, 'errors' => $ddErrors];
        if ($ddErrors > 0) $r['warnings'][] = "Drug-Disease: $ddErrors cặp có index ngoài phạm vi";
        if ($maxDrug >= $numDrugs) $r['errors'][] = "Drug-Disease: max drug_idx=$maxDrug >= numDrugs=$numDrugs";
        if ($maxDisease >= $numDiseases) $r['errors'][] = "Drug-Disease: max disease_idx=$maxDisease >= numDiseases=$numDiseases";
    }

    // 4. Validate Drug-Protein associations
    $dpFile = $dir . 'DrugProteinAssociationNumber.csv';
    if (file_exists($dpFile)) {
        $h = fopen($dpFile, 'r'); fgetcsv($h);
        $maxDrug = -1; $maxProt = -1; $dpCount = 0; $dpErrors = 0;
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) < 2) { $dpErrors++; continue; }
            $d = intval($row[0]); $p = intval($row[1]);
            if ($d > $maxDrug) $maxDrug = $d;
            if ($p > $maxProt) $maxProt = $p;
            if ($d < 0 || $d >= $numDrugs) $dpErrors++;
            if ($p < 0 || $p >= $numProteins) $dpErrors++;
            $dpCount++;
        }
        fclose($h);
        $r['dp'] = ['count' => $dpCount, 'max_drug' => $maxDrug, 'max_protein' => $maxProt, 'errors' => $dpErrors];
        if ($dpErrors > 0) $r['warnings'][] = "Drug-Protein: $dpErrors cặp có index ngoài phạm vi";
    }

    // 5. Check model checkpoints
    $modelDir = $resultRoot . $ds . '/AMNTDDA/';
    $modelCount = 0;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($modelDir . "fold_{$i}_best_model.pt")) $modelCount++;
    }
    $r['models'] = $modelCount;
    if ($modelCount == 0) $r['warnings'][] = "Không tìm thấy model checkpoint (.pt) nào";
    elseif ($modelCount < 10) $r['warnings'][] = "Chỉ có $modelCount/10 fold models";

    // 6. Check summary.csv
    $summaryFile = $modelDir . 'summary.csv';
    $r['summary'] = null;
    if (file_exists($summaryFile)) {
        $h = fopen($summaryFile, 'r');
        $hdr = fgetcsv($h);
        $folds = [];
        while (($row = fgetcsv($h)) !== false) {
            if ($hdr) $folds[] = array_combine($hdr, $row);
        }
        fclose($h);
        $r['summary'] = $folds;
    }

    $results[] = $r;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>AMDGT - Data Validation</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
    h1 { text-align:center; margin-bottom:2rem; color:#818cf8; font-size:2rem; }
    .ds-card { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; }
    .ds-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 1rem; display:flex; align-items:center; gap:10px; }
    .badge-ok { background: rgba(16,185,129,0.2); color:#34d399; padding:4px 12px; border-radius:20px; font-size:0.8rem; }
    .badge-warn { background: rgba(234,179,8,0.2); color:#facc15; padding:4px 12px; border-radius:20px; font-size:0.8rem; }
    .badge-err { background: rgba(239,68,68,0.2); color:#f87171; padding:4px 12px; border-radius:20px; font-size:0.8rem; }
    table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.9rem; }
    th, td { padding:8px 12px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.05); }
    th { color:#94a3b8; font-weight:600; font-size:0.8rem; text-transform:uppercase; }
    .section { margin:1rem 0; }
    .section h3 { color:#818cf8; margin-bottom:0.5rem; font-size:1rem; }
    .msg-err { color:#f87171; margin:4px 0; }
    .msg-warn { color:#facc15; margin:4px 0; }
    .msg-info { color:#38bdf8; margin:4px 0; }
    .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin:1rem 0; }
    .stat-box { background:rgba(0,0,0,0.2); border-radius:12px; padding:1rem; text-align:center; }
    .stat-num { font-size:1.8rem; font-weight:800; color:#818cf8; }
    .stat-label { font-size:0.8rem; color:#64748b; margin-top:4px; }
</style>
</head>
<body>
<h1>🔬 AMDGT Data Validation Report</h1>
<p style="text-align:center;color:#64748b;margin-bottom:2rem;">Generated: <?= date('Y-m-d H:i:s') ?></p>

<?php foreach ($results as $r): ?>
<div class="ds-card">
    <div class="ds-title">
        📊 <?= $r['name'] ?>
        <?php if (empty($r['errors'])): ?>
            <span class="badge-ok">✅ Data OK</span>
        <?php else: ?>
            <span class="badge-err">❌ <?= count($r['errors']) ?> lỗi</span>
        <?php endif; ?>
        <?php if (!empty($r['warnings'])): ?>
            <span class="badge-warn">⚠ <?= count($r['warnings']) ?> cảnh báo</span>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box"><div class="stat-num"><?= $r['counts']['drugs'] ?></div><div class="stat-label">Drugs</div></div>
        <div class="stat-box"><div class="stat-num"><?= $r['counts']['diseases'] ?></div><div class="stat-label">Diseases</div></div>
        <div class="stat-box"><div class="stat-num"><?= $r['counts']['proteins'] ?></div><div class="stat-label">Proteins</div></div>
        <div class="stat-box"><div class="stat-num"><?= $r['models'] ?>/10</div><div class="stat-label">Model Folds</div></div>
    </div>

    <!-- Errors & Warnings -->
    <?php if (!empty($r['errors']) || !empty($r['warnings'])): ?>
    <div class="section">
        <h3>⚠ Issues</h3>
        <?php foreach ($r['errors'] as $e): ?><div class="msg-err">❌ <?= $e ?></div><?php endforeach; ?>
        <?php foreach ($r['warnings'] as $w): ?><div class="msg-warn">⚠ <?= $w ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- File Check Table -->
    <div class="section">
        <h3>📁 Files Check</h3>
        <table>
            <tr><th>File</th><th>Status</th><th>Rows</th><th>Size</th><th>Columns</th></tr>
            <?php foreach ($r['files'] as $fname => $info): ?>
            <tr>
                <td><?= $fname ?></td>
                <td><?= fileStatus($info['exists']) ?></td>
                <td><?= $info['rows'] >= 0 ? number_format($info['rows']) : '-' ?></td>
                <td><?= $info['exists'] ? number_format($info['size']/1024, 1).'KB' : '-' ?></td>
                <td style="font-size:0.8rem;color:#64748b"><?= implode(', ', $info['header']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Associations -->
    <?php if (isset($r['dd'])): ?>
    <div class="section">
        <h3>🔗 Associations</h3>
        <table>
            <tr><th>Type</th><th>Count</th><th>Max Drug Idx</th><th>Max Target Idx</th><th>Index Errors</th></tr>
            <tr>
                <td>Drug-Disease</td>
                <td><?= number_format($r['dd']['count']) ?></td>
                <td><?= $r['dd']['max_drug'] ?> / <?= $r['counts']['drugs']-1 ?></td>
                <td><?= $r['dd']['max_disease'] ?> / <?= $r['counts']['diseases']-1 ?></td>
                <td><?= $r['dd']['errors'] == 0 ? '✅ 0' : '❌ '.$r['dd']['errors'] ?></td>
            </tr>
            <?php if (isset($r['dp'])): ?>
            <tr>
                <td>Drug-Protein</td>
                <td><?= number_format($r['dp']['count']) ?></td>
                <td><?= $r['dp']['max_drug'] ?> / <?= $r['counts']['drugs']-1 ?></td>
                <td><?= $r['dp']['max_protein'] ?> / <?= $r['counts']['proteins']-1 ?></td>
                <td><?= $r['dp']['errors'] == 0 ? '✅ 0' : '❌ '.$r['dp']['errors'] ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Model Performance -->
    <?php if ($r['summary']): ?>
    <div class="section">
        <h3>📈 Model Performance (summary.csv)</h3>
        <table>
            <tr><th>Fold</th><?php foreach(array_keys($r['summary'][0]) as $k) if($k!='Fold') echo "<th>$k</th>"; ?></tr>
            <?php foreach ($r['summary'] as $row): ?>
            <tr>
                <td><?= $row['Fold'] ?? $row[array_keys($row)[0]] ?></td>
                <?php foreach ($row as $k => $v): if($k == 'Fold' || $k == array_keys($row)[0]) continue; ?>
                    <td><?= is_numeric($v) ? number_format(floatval($v), 4) : $v ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

</body>
</html>
