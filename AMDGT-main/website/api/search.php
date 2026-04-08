<?php
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'drug';

$db = getDB();

if ($type === 'drug') {
    $stmt = $db->prepare("SELECT idx, drug_id, name FROM drugs WHERE name LIKE ? OR drug_id LIKE ? ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
    $stmt->execute(["%$q%", "%$q%", "$q%"]);
} else {
    // Chỉ tìm diseases trong phạm vi model hỗ trợ (idx < 409)
    // DB có 1072 diseases nhưng model chỉ train trên 409 diseases từ association data
    $stmt = $db->prepare("SELECT idx, disease_id, name FROM diseases WHERE idx < 409 AND (name LIKE ? OR disease_id LIKE ?) ORDER BY (CASE WHEN name LIKE ? THEN 1 ELSE 2 END), name LIMIT 15");
    $stmt->execute(["%$q%", "%$q%", "$q%"]);
}

jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
