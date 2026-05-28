<?php
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'drug';
$dataset = $_GET['dataset'] ?? 'C-dataset';

$db = getDB();
if ($db === null) {
    jsonResponse(['error' => 'Database chưa được setup. Vui lòng chạy setup_db.php'], 500);
}

try {
    $whereDataset = "dataset = ?";
    $params = [$dataset];
    if ($dataset === 'ALL') {
        $whereDataset = "1=1";
        $params = [];
    }

    if ($type === 'drug') {
        // Nếu query rỗng hoặc %, lấy 20 items đầu tiên theo tên
        if (empty($q) || $q === '%' || $q === '%25') {
            $stmt = $db->prepare("SELECT idx, drug_id, name, dataset FROM drugs WHERE $whereDataset ORDER BY name ASC LIMIT 20");
            if ($dataset !== 'ALL') {
                $stmt->execute([$dataset]);
            } else {
                $stmt->execute();
            }
        } else {
            $likeQ = "%$q%";
            $startQ = "$q%";

            // Detect single letter for letter-filter: use STARTS-WITH matching
            $isSingleLetter = (strlen($q) === 1 && ctype_alpha($q));

            // SQL ưu tiên:
            // 1. Khớp chính xác tên
            // 2. Bắt đầu bằng chuỗi tìm kiếm (Starts With)
            // 3. Chứa chuỗi tìm kiếm (Contains)
            // For single letter: strictly STARTS-WITH (letter filter)
            $matchClause = $isSingleLetter
                ? "(name LIKE ? OR drug_id LIKE ?)"
                : "(name = ? OR name LIKE ? OR drug_id LIKE ?)";

            $sql = "SELECT idx, drug_id, name, dataset FROM drugs
                    WHERE $whereDataset AND $matchClause
                    ORDER BY
                        (CASE
                            WHEN name = ? THEN 1
                            WHEN name LIKE ? THEN 2
                            WHEN drug_id LIKE ? THEN 3
                            ELSE 4
                        END), name
                    LIMIT " . ($isSingleLetter ? "50" : "20");

            if ($isSingleLetter) {
                $paramsArr = array_merge($params, [$startQ, $startQ, $startQ, $startQ, $startQ]);
            } else {
                $paramsArr = array_merge($params, [$q, $likeQ, $likeQ, $q, $startQ, $startQ]);
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($paramsArr);
        }
    } else if ($type === 'disease') {
        if (empty($q) || $q === '%' || $q === '%25') {
            $stmt = $db->prepare("SELECT idx, disease_id, name, dataset FROM diseases WHERE $whereDataset ORDER BY disease_id ASC LIMIT 20");
            if ($dataset !== 'ALL') {
                $stmt->execute([$dataset]);
            } else {
                $stmt->execute();
            }
        } else {
            $likeQ = "%$q%";
            $startQ = "$q%";
            $isSingleLetter = (strlen($q) === 1 && ctype_alpha($q));
            
            // For single letter: search name OR disease_id starting with or containing the letter
            $matchClause = $isSingleLetter
                ? "(name LIKE ? OR disease_id LIKE ? OR name LIKE ?)"
                : "(name LIKE ? OR disease_id LIKE ?)";

            $sql = "SELECT idx, disease_id, name, dataset FROM diseases
                    WHERE $whereDataset AND $matchClause
                    ORDER BY (CASE WHEN name LIKE ? THEN 1 WHEN disease_id LIKE ? THEN 2 ELSE 3 END), disease_id
                    LIMIT " . ($isSingleLetter ? "50" : "15");

            if ($isSingleLetter) {
                // Search: name starts with letter, disease_id starts with letter, name contains letter
                $paramsArr = array_merge($params, [$startQ, $startQ, $likeQ, $startQ, $startQ]);
            } else {
                $paramsArr = array_merge($params, [$likeQ, $likeQ, "$q%", "$q%"]);
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($paramsArr);
        }
    } else if ($type === 'protein') {
        if (empty($q) || $q === '%' || $q === '%25') {
            $stmt = $db->prepare("SELECT idx, protein_id, name, dataset FROM proteins WHERE $whereDataset ORDER BY protein_id ASC LIMIT 20");
            if ($dataset !== 'ALL') {
                $stmt->execute([$dataset]);
            } else {
                $stmt->execute();
            }
        } else {
            $likeQ = "%$q%";
            $startQ = "$q%";
            $isSingleLetter = (strlen($q) === 1 && ctype_alpha($q));
            
            // For single letter: also search protein_id containing the letter
            $matchClause = $isSingleLetter
                ? "(name LIKE ? OR protein_id LIKE ? OR protein_id LIKE ?)"
                : "(name LIKE ? OR protein_id LIKE ?)";

            $sql = "SELECT idx, protein_id, name, dataset FROM proteins
                    WHERE $whereDataset AND $matchClause
                    ORDER BY (CASE WHEN protein_id LIKE ? THEN 1 WHEN name LIKE ? THEN 2 ELSE 3 END), protein_id
                    LIMIT " . ($isSingleLetter ? "50" : "15");

            if ($isSingleLetter) {
                // protein_id starts with letter (e.g. P for P22303), name starts with, protein_id contains
                $paramsArr = array_merge($params, [$startQ, $startQ, $likeQ, $startQ, $startQ]);
            } else {
                $paramsArr = array_merge($params, [$likeQ, $likeQ, "$q%", "$q%"]);
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($paramsArr);
        }
    } else {
        jsonResponse([]);
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse($results);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
?>
