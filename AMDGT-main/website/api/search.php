<?php
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'drug';
$dataset = $_GET['dataset'] ?? 'C-dataset';

$db = getDB();

if ($type === 'drug') {
    $stmt = $db->prepare("SELECT idx, drug_id, name FROM drugs WHERE dataset = ? AND (name LIKE ? OR drug_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
    $stmt->execute([$dataset, "%$q%", "%$q%", "$q%"]);
} else if ($type === 'disease') {
    $stmt = $db->prepare("SELECT idx, disease_id, name FROM diseases WHERE dataset = ? AND (name LIKE ? OR disease_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
    $stmt->execute([$dataset, "%$q%", "%$q%", "$q%"]);
} else if ($type === 'protein') {
    $stmt = $db->prepare("SELECT idx, protein_id, name FROM proteins WHERE dataset = ? AND (name LIKE ? OR protein_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
    $stmt->execute([$dataset, "%$q%", "%$q%", "$q%"]);
}

jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
