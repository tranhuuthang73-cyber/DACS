<?php
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'drug';
$dataset = $_GET['dataset'] ?? 'C-dataset';

$db = getDB();
if ($db === null) {
    jsonResponse([]); // DB not set up yet
}

try {
    $whereDataset = "dataset = ?";
    $params = [$dataset];
    if ($dataset === 'ALL') {
        $whereDataset = "1=1";
        $params = [];
    }

    if ($type === 'drug') {
        $stmt = $db->prepare("SELECT idx, drug_id, name, dataset FROM drugs WHERE $whereDataset AND (name LIKE ? OR drug_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
        $stmt->execute(array_merge($params, ["%$q%", "%$q%", "$q%"]));
    } else if ($type === 'disease') {
        $stmt = $db->prepare("SELECT idx, disease_id, name, dataset FROM diseases WHERE $whereDataset AND (name LIKE ? OR disease_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
        $stmt->execute(array_merge($params, ["%$q%", "%$q%", "$q%"]));
    } else if ($type === 'protein') {
        $stmt = $db->prepare("SELECT idx, protein_id, name, dataset FROM proteins WHERE $whereDataset AND (name LIKE ? OR protein_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
        $stmt->execute(array_merge($params, ["%$q%", "%$q%", "$q%"]));
    } else {
        jsonResponse([]);
    }
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    jsonResponse([]); // Table may not exist yet
}
?>
