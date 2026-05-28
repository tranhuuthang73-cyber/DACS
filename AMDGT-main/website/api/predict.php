<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Chưa đăng nhập'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$topK = intval($input['top_k'] ?? 10);
$dataset = $input['dataset'] ?? 'C-dataset';

$db = getDB();

// Helper: fetch column safely
function dbFetchOne($db, $sql, $params, $default = null) {
    if ($db === null) return $default;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn() ?: $default;
    } catch (PDOException $e) { return $default; }
}

// Helper: fetch one row safely
function dbFetchRow($db, $sql, $params) {
    if ($db === null) return null;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) { return null; }
}

// Helper: execute INSERT safely
function dbExec($db, $sql, $params) {
    if ($db === null) return;
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
    } catch (PDOException $e) { error_log('DB_EXEC_ERROR: ' . $e->getMessage()); }
}

// ============================================================
// INITIALIZE DATABASE TABLE FOR 3-STATE TRACKING
// ============================================================
function initDiscoveredLinksTable($db) {
    if ($db === null) return;
    try {
        $isSqlite = ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        
        if ($isSqlite) {
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dataset VARCHAR(20) NOT NULL,
                drug_idx INT NOT NULL,
                disease_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (dataset, drug_idx, disease_idx)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_dp_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dataset VARCHAR(20) NOT NULL,
                drug_idx INT NOT NULL,
                protein_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (dataset, drug_idx, protein_idx)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_pd_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dataset VARCHAR(20) NOT NULL,
                protein_idx INT NOT NULL,
                disease_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (dataset, protein_idx, disease_idx)
            )");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dataset VARCHAR(20) NOT NULL,
                drug_idx INT NOT NULL,
                disease_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_discovery (dataset, drug_idx, disease_idx)
            ) ENGINE=InnoDB");
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_dp_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dataset VARCHAR(20) NOT NULL,
                drug_idx INT NOT NULL,
                protein_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_dp (dataset, drug_idx, protein_idx)
            ) ENGINE=InnoDB");
            $db->exec("CREATE TABLE IF NOT EXISTS discovered_pd_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dataset VARCHAR(20) NOT NULL,
                protein_idx INT NOT NULL,
                disease_idx INT NOT NULL,
                discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pd (dataset, protein_idx, disease_idx)
            ) ENGINE=InnoDB");
        }
    } catch (PDOException $e) { error_log('DB_CREATE_TABLE_ERROR: ' . $e->getMessage()); }
}
initDiscoveredLinksTable($db);

// ============================================================
// OFFLINE PREDICTIONS - Uses CSV files directly (NO AI server needed)
// Jaccard + degree scoring for network-based similarity
// ============================================================
// Helper to get normalized names from database
function getNamesFromDB($datasetName) {
    $db = getDB();
    if (!$db) return null;
    $names = ['drugs' => [], 'diseases' => [], 'drug_ids' => [], 'disease_ids' => []];
    try {
        $stmt = $db->prepare("SELECT idx, name, drug_id FROM drugs WHERE dataset = ?");
        $stmt->execute([$datasetName]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $names['drugs'][intval($d['idx'])] = $d['name'];
            $names['drug_ids'][intval($d['idx'])] = $d['drug_id'];
        }
        $stmt = $db->prepare("SELECT idx, name, disease_id FROM diseases WHERE dataset = ?");
        $stmt->execute([$datasetName]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $names['diseases'][intval($d['idx'])] = $d['name'];
            $names['disease_ids'][intval($d['idx'])] = $d['disease_id'];
        }
        return $names;
    } catch (Exception $e) { return null; }
}

function getCSVBasedPredictions($dataDir, $queryType, $queryIdx, $topK) {
    $predictions = [];
    $datasetName = basename(rtrim($dataDir, '/\\'));
    $dbNames = getNamesFromDB($datasetName);

    // Load drug names
    $drugNames = [];
    $drugFile = $dataDir . 'DrugInformation.csv';
    if (file_exists($drugFile)) {
        $h = fopen($drugFile, 'r');
        fgetcsv($h);
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $drugNames[$idx] = $dbNames['drugs'][$idx] ?? ($row[1] ?? "Drug_$idx");
            $idx++;
        }
        fclose($h);
    }
    $numDrugs = count($drugNames);
    // Load protein names to know the protein count
    $proteinNames = [];
    $protFile = $dataDir . 'ProteinInformation.csv';
    if (file_exists($protFile)) {
        $h = fopen($protFile, 'r'); fgetcsv($h);
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $proteinNames[$idx] = $row[0] ?? "Protein_$idx";
            $idx++;
        }
        fclose($h);
    }
    $numProteins = count($proteinNames);

    // Load all nodes (drugs + diseases + proteins)
    $allNodes = [];
    $nodeFile = $dataDir . 'AllNode.csv';
    if (file_exists($nodeFile)) {
        $h = fopen($nodeFile, 'r');
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $name = trim($row[0] ?? '');
            $allNodes[] = $name;
            $idx++;
        }
        fclose($h);
    }
    $numDiseases = count($allNodes) - $numDrugs - $numProteins;

    // Map names from DB or correct index mapping
    foreach ($allNodes as $idx => $name) {
        if ($idx < $numDrugs) {
            $allNodes[$idx] = $dbNames['drugs'][$idx] ?? $name;
        } elseif ($idx < $numDrugs + $numDiseases) {
            $di = $idx - $numDrugs;
            $allNodes[$idx] = $dbNames['diseases'][$di] ?? $name;
        } else {
            $pi = $idx - $numDrugs - $numDiseases;
            $allNodes[$idx] = $proteinNames[$pi] ?? $name;
        }
    }

    // Load drug-disease associations into memory (keyed by drug and disease)
    $ddByDrug = []; // ddByDrug[drug_idx] = [disease_idx1, disease_idx2]
    $ddByDisease = []; // ddByDisease[disease_idx] = [drug_idx1, drug_idx2]
    $ddFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($ddFile)) {
        $h = fopen($ddFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $d = intval($row[0]);
                $di = intval($row[1]);
                if (!isset($ddByDrug[$d])) $ddByDrug[$d] = [];
                $ddByDrug[$d][] = $di;
                if (!isset($ddByDisease[$di])) $ddByDisease[$di] = [];
                $ddByDisease[$di][] = $d;
            }
        }
        fclose($h);
    }

    // Load drug-protein associations
    $dpByDrug = [];
    $dpFile = $dataDir . 'DrugProteinAssociationNumber.csv';
    if (file_exists($dpFile)) {
        $h = fopen($dpFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $d = intval($row[0]);
                $p = intval($row[1]);
                if (!isset($dpByDrug[$d])) $dpByDrug[$d] = [];
                if (!in_array($p, $dpByDrug[$d])) $dpByDrug[$d][] = $p;
            }
        }
        fclose($h);
    }

    // Load protein-disease associations
    $pdByDisease = [];
    $pdFile = $dataDir . 'ProteinDiseaseAssociationNumber.csv';
    if (file_exists($pdFile)) {
        $h = fopen($pdFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $p = intval($row[0]);
                $di = intval($row[1]);
                if (!isset($pdByDisease[$di])) $pdByDisease[$di] = [];
                if (!in_array($p, $pdByDisease[$di])) $pdByDisease[$di][] = $p;
            }
        }
        fclose($h);
    }

    $db = getDB();

    // Include manually added known_associations from Database
    if ($db) {
        $stmt = $db->prepare("SELECT drug_idx, disease_idx FROM known_associations WHERE dataset = ?");
        $stmt->execute([$datasetName]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $d = intval($row['drug_idx']);
            $di = intval($row['disease_idx']);
            if (!isset($ddByDrug[$d])) $ddByDrug[$d] = [];
            if (!in_array($di, $ddByDrug[$d])) $ddByDrug[$d][] = $di;
            if (!isset($ddByDisease[$di])) $ddByDisease[$di] = [];
            if (!in_array($d, $ddByDisease[$di])) $ddByDisease[$di][] = $d;
        }
    }

    // Find previously discovered links
    $discoveredSet = [];
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("SELECT drug_idx, disease_idx FROM discovered_links WHERE dataset = ?");
        $stmt->execute([$datasetName]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($queryType === 'drug' && $row['drug_idx'] == $queryIdx) {
                $discoveredSet[$row['disease_idx']] = true;
            } else if ($queryType === 'disease' && $row['disease_idx'] == $queryIdx) {
                $discoveredSet[$row['drug_idx']] = true;
            }
        }
    }

    if ($queryType === 'drug') {
        $queryNeighbors = $ddByDrug[intval($queryIdx)] ?? [];

        // Score each disease
        for ($di = 0; $di < $numDiseases; $di++) {
            $isKnown = in_array($di, $queryNeighbors);

            $diseaseNeighbors = $ddByDisease[$di] ?? [];
            $intersection = count(array_intersect($queryNeighbors, $diseaseNeighbors));
            $union = count(array_unique(array_merge($queryNeighbors, $diseaseNeighbors)));
            $jaccard = $union > 0 ? $intersection / $union : 0;

            $queryDegree = count($queryNeighbors);
            $diseaseDegree = count($diseaseNeighbors);
            $degreeScore = ($queryDegree > 0 && $diseaseDegree > 0)
                ? ($queryDegree * $diseaseDegree) / ($queryDegree + $diseaseDegree)
                : 0;

            $score = $isKnown
                ? 70 + ($jaccard * 30)
                : ($jaccard * 60) + ($degreeScore / max($queryDegree, 1) * 40);

            $status = 'novel';
            if ($isKnown) {
                $status = 'literature';
            } else if (isset($discoveredSet[$di])) {
                $status = 'previously_discovered';
            }

            // Save novel links
            if ($status === 'novel' && $db) {
                dbExec($db, "INSERT IGNORE INTO discovered_links (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, $queryIdx, $di]);
                // Khám phá Drug-Protein: proteins của disease này mà drug chưa có liên kết
                $drugProteins = $dpByDrug[intval($queryIdx)] ?? [];
                $diseaseProteins = $pdByDisease[$di] ?? [];
                foreach ($diseaseProteins as $p) {
                    if (!in_array($p, $drugProteins)) {
                        dbExec($db, "INSERT IGNORE INTO discovered_dp_links (dataset, drug_idx, protein_idx) VALUES (?, ?, ?)", [$datasetName, $queryIdx, $p]);
                    }
                }
                // Khám phá Protein-Disease: proteins của drug mà disease chưa có liên kết
                foreach ($drugProteins as $p) {
                    if (!in_array($p, $diseaseProteins)) {
                        dbExec($db, "INSERT IGNORE INTO discovered_pd_links (dataset, protein_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, $p, $di]);
                    }
                }
            }

            $diseaseName = $allNodes[$numDrugs + $di] ?? "Disease_$di";
            $diseaseId = $dbNames['disease_ids'][$di] ?? null;

            // Nếu DB có dữ liệu nhưng disease này không có trong DB (đã bị xóa) thì bỏ qua
            if (!empty($dbNames['diseases']) && !isset($dbNames['diseases'][$di])) {
                continue;
            }
            if (!$diseaseId) $diseaseId = $diseaseName;

            $predictions[] = [
                'disease_idx' => $di,
                'disease_id' => $diseaseId,
                'disease_name' => $diseaseName,
                'score' => min(100, max(0, floatval($score))),
                'is_known' => $isKnown,
                'status' => $status,
                'rank' => 0
            ];
        }
    } else {
        // disease_to_drug
        $queryNeighbors = $ddByDisease[intval($queryIdx)] ?? [];

        for ($dri = 0; $dri < $numDrugs; $dri++) {
            $isKnown = in_array($dri, $queryNeighbors);

            $drugNeighbors = $ddByDrug[$dri] ?? [];
            $intersection = count(array_intersect($queryNeighbors, $drugNeighbors));
            $union = count(array_unique(array_merge($queryNeighbors, $drugNeighbors)));
            $jaccard = $union > 0 ? $intersection / $union : 0;

            $queryDegree = count($queryNeighbors);
            $drugDegree = count($drugNeighbors);
            $degreeScore = ($queryDegree > 0 && $drugDegree > 0)
                ? ($queryDegree * $drugDegree) / ($queryDegree + $drugDegree)
                : 0;

            $score = $isKnown
                ? 70 + ($jaccard * 30)
                : ($jaccard * 60) + ($degreeScore / max($queryDegree, 1) * 40);

            $status = 'novel';
            if ($isKnown) {
                $status = 'literature';
            } else if (isset($discoveredSet[$dri])) {
                $status = 'previously_discovered';
            }

            // Save novel links
            if ($status === 'novel' && $db) {
                dbExec($db, "INSERT IGNORE INTO discovered_links (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, $dri, $queryIdx]);
                // Khám phá Drug-Protein
                $drugProteins = $dpByDrug[$dri] ?? [];
                $diseaseProteins = $pdByDisease[intval($queryIdx)] ?? [];
                foreach ($diseaseProteins as $p) {
                    if (!in_array($p, $drugProteins)) {
                        dbExec($db, "INSERT IGNORE INTO discovered_dp_links (dataset, drug_idx, protein_idx) VALUES (?, ?, ?)", [$datasetName, $dri, $p]);
                    }
                }
                // Khám phá Protein-Disease
                foreach ($drugProteins as $p) {
                    if (!in_array($p, $diseaseProteins)) {
                        dbExec($db, "INSERT IGNORE INTO discovered_pd_links (dataset, protein_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, $p, $queryIdx]);
                    }
                }
            }

            $drugName = $drugNames[$dri] ?? "Drug_$dri";
            $drugId = $dbNames['drug_ids'][$dri] ?? null;

            // Nếu DB có dữ liệu nhưng drug này không có trong DB (đã bị xóa) thì bỏ qua
            if (!empty($dbNames['drugs']) && !isset($dbNames['drugs'][$dri])) {
                continue;
            }
            if (!$drugId) $drugId = $drugName;

            $predictions[] = [
                'drug_idx' => $dri,
                'drug_id' => $drugId,
                'drug_name' => $drugName,
                'score' => min(100, max(0, floatval($score))),
                'is_known' => $isKnown,
                'status' => $status,
                'rank' => 0
            ];
        }
    }

    // Filter out 0 scores
    $filtered = array_filter($predictions, fn($p) => $p['score'] > 0);
    $filtered = array_values($filtered);

    // Sort by score descending
    usort($filtered, fn($a, $b) => $b['score'] <=> $a['score']);

    // Take top K and assign ranks
    $result = [];
    for ($i = 0; $i < min($topK, count($filtered)); $i++) {
        $filtered[$i]['rank'] = $i + 1;
        $result[] = $filtered[$i];
    }

    return $result;
}

// ============================================================
// OFFLINE PROTEIN PREDICTIONS - Drug-Disease via protein bridge
// ============================================================
function getProteinMediatedPredictions($dataDir, $proteinIdx, $topK) {
    $datasetName = basename(rtrim($dataDir, '/\\'));
    $dbNames = getNamesFromDB($datasetName);

    // Load drug names
    $drugNames = [];
    $drugFile = $dataDir . 'DrugInformation.csv';
    if (file_exists($drugFile)) {
        $h = fopen($drugFile, 'r');
        fgetcsv($h);
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $drugNames[$idx] = $dbNames['drugs'][$idx] ?? ($row[1] ?? "Drug_$idx");
            $idx++;
        }
        fclose($h);
    }
    $numDrugs = count($drugNames);
    // Load protein names to know the protein count
    $proteinNames = [];
    $protFile = $dataDir . 'ProteinInformation.csv';
    if (file_exists($protFile)) {
        $h = fopen($protFile, 'r'); fgetcsv($h);
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $proteinNames[$idx] = $row[0] ?? "Protein_$idx";
            $idx++;
        }
        fclose($h);
    }
    $numProteins = count($proteinNames);

    // Load all nodes
    $allNodes = [];
    $nodeFile = $dataDir . 'AllNode.csv';
    if (file_exists($nodeFile)) {
        $h = fopen($nodeFile, 'r');
        $idx = 0;
        while (($row = fgetcsv($h)) !== false) {
            $name = trim($row[0] ?? '');
            $allNodes[] = $name;
            $idx++;
        }
        fclose($h);
    }
    $numDiseases = count($allNodes) - $numDrugs - $numProteins;

    // Map names from DB or correct index mapping
    foreach ($allNodes as $idx => $name) {
        if ($idx < $numDrugs) {
            $allNodes[$idx] = $dbNames['drugs'][$idx] ?? $name;
        } elseif ($idx < $numDrugs + $numDiseases) {
            $di = $idx - $numDrugs;
            $allNodes[$idx] = $dbNames['diseases'][$di] ?? $name;
        } else {
            $pi = $idx - $numDrugs - $numDiseases;
            $allNodes[$idx] = $proteinNames[$pi] ?? $name;
        }
    }

    // Load drug-protein associations
    $dpEdges = []; // dpEdges[drug_idx] = [protein_idx1, ...]
    $dpFile = $dataDir . 'DrugProteinAssociationNumber.csv';
    if (file_exists($dpFile)) {
        $h = fopen($dpFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $dpEdges[intval($row[0])][] = intval($row[1]);
            }
        }
        fclose($h);
    }

    // Load protein-disease associations
    $pdEdges = []; // pdEdges[disease_idx] = [protein_idx1, ...]
    $pdFile = $dataDir . 'ProteinDiseaseAssociationNumber.csv';
    if (file_exists($pdFile)) {
        $h = fopen($pdFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $pdEdges[intval($row[1])][] = intval($row[0]);
            }
        }
        fclose($h);
    }

    // Find diseases linked to this protein
    // pdEdges is keyed by protein_idx => [disease_idx1, disease_idx2, ...]
    $linkedDiseases = $pdEdges[intval($proteinIdx)] ?? [];

    // Find drugs linked to this protein
    // dpEdges is keyed by drug_idx => [protein_idx1, protein_idx2, ...]
    $linkedDrugs = [];
    foreach ($dpEdges as $dri => $prots) {
        if (in_array(intval($proteinIdx), $prots)) {
            $linkedDrugs[] = $dri;
        }
    }

    // Load drug-disease associations for direct link check
    $ddByDrug = [];
    $ddByDisease = [];
    $ddFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($ddFile)) {
        $h = fopen($ddFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $d = intval($row[0]);
                $di2 = intval($row[1]);
                if (!isset($ddByDrug[$d])) $ddByDrug[$d] = [];
                $ddByDrug[$d][] = $di2;
                if (!isset($ddByDisease[$di2])) $ddByDisease[$di2] = [];
                $ddByDisease[$di2][] = $d;
            }
        }
        fclose($h);
    }

    // Build reverse: disease -> proteins
    $diseaseToProteins = [];
    foreach ($pdEdges as $pIdx => $diseases) {
        foreach ($diseases as $dIdx) {
            if (!isset($diseaseToProteins[$dIdx])) $diseaseToProteins[$dIdx] = [];
            $diseaseToProteins[$dIdx][] = $pIdx;
        }
    }

    $db = getDB();

    // Include DB manual associations
    if ($db) {
        // Drug-Protein
        $stmt1 = $db->prepare("SELECT drug_idx, protein_idx FROM drug_protein_associations WHERE dataset = ?");
        if ($stmt1) {
            try {
                $stmt1->execute([$datasetName]);
                while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                    $d = intval($row['drug_idx']);
                    $p = intval($row['protein_idx']);
                    if (!isset($dpEdges[$p])) $dpEdges[$p] = [];
                    if (!in_array($d, $dpEdges[$p])) $dpEdges[$p][] = $d;
                }
            } catch (PDOException $e) {} // Table might not exist yet
        }

        // Protein-Disease
        $stmt2 = $db->prepare("SELECT protein_idx, disease_idx FROM protein_disease_associations WHERE dataset = ?");
        if ($stmt2) {
            try {
                $stmt2->execute([$datasetName]);
                while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $p = intval($row['protein_idx']);
                    $di = intval($row['disease_idx']);
                    if (!isset($pdEdges[$p])) $pdEdges[$p] = [];
                    if (!in_array($di, $pdEdges[$p])) $pdEdges[$p][] = $di;
                    if (!isset($diseaseToProteins[$di])) $diseaseToProteins[$di] = [];
                    if (!in_array($p, $diseaseToProteins[$di])) $diseaseToProteins[$di][] = $p;
                }
            } catch (PDOException $e) {}
        }

        // Drug-Disease (known)
        $stmt3 = $db->prepare("SELECT drug_idx, disease_idx FROM known_associations WHERE dataset = ?");
        $stmt3->execute([$datasetName]);
        while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
            $d = intval($row['drug_idx']);
            $di = intval($row['disease_idx']);
            if (!isset($ddByDrug[$d])) $ddByDrug[$d] = [];
            if (!in_array($di, $ddByDrug[$d])) $ddByDrug[$d][] = $di;
            if (!isset($ddByDisease[$di])) $ddByDisease[$di] = [];
            if (!in_array($d, $ddByDisease[$di])) $ddByDisease[$di][] = $d;
        }
    }

    // Find previously discovered links
    $discoveredSet = []; // Format: "$drugIdx-$diseaseIdx" => true
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("SELECT drug_idx, disease_idx FROM discovered_links WHERE dataset = ?");
        $stmt->execute([$datasetName]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $discoveredSet[$row['drug_idx'] . '-' . $row['disease_idx']] = true;
        }
    }

    // Build mediated predictions: drug -> protein -> disease
    $mediated = [];
    foreach ($linkedDrugs as $dri) {
        $drugProteins = $dpEdges[$dri] ?? [];
        $drugDiseases = $ddByDrug[$dri] ?? [];

        foreach ($linkedDiseases as $di) {
            $pathway = "Drug($dri) --Protein($proteinIdx)-- Disease($di)";
            
            // Check if direct drug-disease association exists
            $isKnown = in_array($di, $drugDiseases);
            
            // Factor 1: Direct link bonus (0 or 25)
            $directBonus = $isKnown ? 25 : 0;
            
            // Factor 2: How many proteins connect this disease? (more = stronger evidence)
            $diseaseProts = $diseaseToProteins[$di] ?? [];
            $sharedProteins = array_intersect($drugProteins, $diseaseProts);
            $sharedCount = count($sharedProteins);
            // Score: 1 shared protein = 15, 2 = 22, 3+ = 28
            $sharedScore = $sharedCount > 0 ? min(28, 10 + $sharedCount * 7) : 5;
            
            // Factor 3: Disease connectivity (diseases with more drug connections = more validated)
            $diseaseDrugs = $ddByDisease[$di] ?? [];
            $diseaseDegree = count($diseaseDrugs);
            $degreeScore = min(20, $diseaseDegree * 2.5);
            
            // Factor 4: Drug connectivity breadth
            $drugDegree = count($drugDiseases);
            $drugScore = min(15, $drugDegree * 1.5);
            
            // Factor 5: Disease-protein specificity (fewer proteins = more specific = higher score)
            $diseaseProtCount = count($diseaseProts);
            $specificityScore = $diseaseProtCount > 0 ? min(12, 12 / $diseaseProtCount * 3) : 3;
            
            $totalScore = $directBonus + $sharedScore + $degreeScore + $drugScore + $specificityScore;
            $totalScore = min(100, max(5, $totalScore));

            $status = 'novel';
            if ($isKnown) {
                $status = 'literature';
            } else if (isset($discoveredSet[$dri . '-' . $di])) {
                $status = 'previously_discovered';
            }

            if ($status === 'novel' && $db) {
                dbExec($db, "INSERT IGNORE INTO discovered_links (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, $dri, $di]);
            }

            // Lưu AI khám phá Drug-Protein và Protein-Disease
            if ($db && !$isKnown) {
                // Drug → Protein (nếu drug liên kết với protein này nhưng chưa có trong dataset gốc)
                $dpExists = in_array(intval($proteinIdx), $drugProteins);
                if (!$dpExists) {
                    dbExec($db, "INSERT IGNORE INTO discovered_dp_links (dataset, drug_idx, protein_idx) VALUES (?, ?, ?)", [$datasetName, $dri, intval($proteinIdx)]);
                }
                // Protein → Disease (nếu protein liên kết với disease này nhưng chưa có trong dataset gốc)
                $pdExists = in_array($di, $pdEdges[intval($proteinIdx)] ?? []);
                if (!$pdExists) {
                    dbExec($db, "INSERT IGNORE INTO discovered_pd_links (dataset, protein_idx, disease_idx) VALUES (?, ?, ?)", [$datasetName, intval($proteinIdx), $di]);
                }
            }

            $drugName = $drugNames[$dri] ?? "Drug_$dri";
            $drugId = $dbNames['drug_ids'][$dri] ?? null;
            if (!empty($dbNames['drugs']) && !isset($dbNames['drugs'][$dri])) continue;
            if (!$drugId) $drugId = $drugName;

            $diseaseName = $allNodes[$numDrugs + $di] ?? "Disease_$di";
            $diseaseId = $dbNames['disease_ids'][$di] ?? null;
            if (!empty($dbNames['diseases']) && !isset($dbNames['diseases'][$di])) continue;
            if (!$diseaseId) $diseaseId = $diseaseName;

            $mediated[] = [
                'drug_idx' => $dri,
                'drug_id' => $drugId,
                'drug_name' => $drugName,
                'disease_idx' => $di,
                'disease_id' => $diseaseId,
                'disease_name' => $diseaseName,
                'protein_idx' => intval($proteinIdx),
                'pathway' => $pathway,
                'score' => round($totalScore, 1),
                'is_known' => $isKnown,
                'status' => $status
            ];
        }
    }

    usort($mediated, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($mediated, 0, $topK);
}

// ============================================================
// ROUTING
// ============================================================

if ($type === 'drug_to_disease') {
    $drugIdx = $input['drug_idx'] ?? null;
    if ($drugIdx === null) jsonResponse(['error' => 'Thiếu drug_idx'], 400);

    $drug = dbFetchRow($db, "SELECT * FROM drugs WHERE dataset = ? AND idx = ?", [$dataset, $drugIdx]);
    $queryName = $drug ? $drug['name'] : "Drug #$drugIdx";

    $dataDir = __DIR__ . "/../../data/$dataset/";
    $predictions = getCSVBasedPredictions($dataDir, 'drug', $drugIdx, $topK);

    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'drug_to_disease', $queryName, json_encode($predictions)]);

    jsonResponse([
        'predictions' => $predictions,
        'query_name' => $queryName,
        'source' => 'offline',
        'dataset' => $dataset
    ]);

} elseif ($type === 'disease_to_drug') {
    $diseaseIdx = $input['disease_idx'] ?? null;
    if ($diseaseIdx === null) jsonResponse(['error' => 'Thiếu disease_idx'], 400);

    $disease = dbFetchRow($db, "SELECT * FROM diseases WHERE dataset = ? AND idx = ?", [$dataset, $diseaseIdx]);
    $queryName = $disease ? $disease['name'] : "Disease #$diseaseIdx";

    $dataDir = __DIR__ . "/../../data/$dataset/";
    $predictions = getCSVBasedPredictions($dataDir, 'disease', $diseaseIdx, $topK);

    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'disease_to_drug', $queryName, json_encode($predictions)]);

    jsonResponse([
        'predictions' => $predictions,
        'query_name' => $queryName,
        'source' => 'offline',
        'dataset' => $dataset
    ]);

} elseif ($type === 'protein_to_any') {
    $proteinIdx = $input['protein_idx'] ?? null;
    if ($proteinIdx === null) jsonResponse(['error' => 'Thiếu protein_idx'], 400);

    // Read protein name from CSV for reliability (DB may be out of sync)
    $queryName = "Protein #$proteinIdx";
    $protInfoFile = __DIR__ . "/../../data/$dataset/ProteinInformation.csv";
    if (file_exists($protInfoFile)) {
        $ph = fopen($protInfoFile, 'r');
        fgetcsv($ph); // skip header
        $pidx = 0;
        while (($prow = fgetcsv($ph)) !== false) {
            if ($pidx == intval($proteinIdx)) {
                $queryName = "Protein " . trim($prow[0] ?? "P$proteinIdx");
                break;
            }
            $pidx++;
        }
        fclose($ph);
    }

    if ($dataset === 'ALL') {
        $dataset = 'C-dataset';
    }

    $dataDir = __DIR__ . "/../../data/$dataset/";
    if (!is_dir($dataDir)) {
        // Fallback to C-dataset if the requested dataset folder doesn't exist
        $dataset = 'C-dataset';
        $dataDir = __DIR__ . "/../../data/C-dataset/";
        if (!is_dir($dataDir)) {
            jsonResponse(['error' => "Khong tim thay thu muc data/$dataset"], 500);
        }
    }

    $mediated = getProteinMediatedPredictions($dataDir, $proteinIdx, $topK);

    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'protein_to_any', $queryName, json_encode($mediated)]);

    jsonResponse([
        'mediated_predictions' => $mediated,
        'query_name' => $queryName,
        'source' => 'offline',
        'dataset' => $dataset
    ]);

} elseif ($type === 'triplet') {
    $drugIdx    = $input['drug_idx'] ?? null;
    $proteinIdx = $input['protein_idx'] ?? null;
    $diseaseIdx = $input['disease_idx'] ?? null;

    if ($drugIdx === null || $proteinIdx === null || $diseaseIdx === null) {
        jsonResponse(['error' => 'Thiếu thông tin cho tổ hợp 3'], 400);
    }

    $drugName    = dbFetchOne($db, "SELECT name FROM drugs WHERE    dataset = ? AND idx = ?", [$dataset, $drugIdx],    "Drug #$drugIdx");
    $proteinName = dbFetchOne($db, "SELECT name FROM proteins WHERE dataset = ? AND idx = ?", [$dataset, $proteinIdx], "Protein #$proteinIdx");
    $diseaseName = dbFetchOne($db, "SELECT name FROM diseases WHERE  dataset = ? AND idx = ?", [$dataset, $diseaseIdx], "Disease #$diseaseIdx");

    $dataDir = __DIR__ . "/../../data/$dataset/";

    // Simple triplet scoring using CSV files
    $score = 0;
    $mechanism = '';

    // Load edges
    $drugProteinLinked = false;
    $proteinDiseaseLinked = false;
    $drugDiseaseLinked = false;

    // Check drug-protein
    $dpFile = $dataDir . 'DrugProteinAssociationNumber.csv';
    if (file_exists($dpFile)) {
        $h = fopen($dpFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2 && intval($row[0]) === intval($drugIdx) && intval($row[1]) === intval($proteinIdx)) {
                $drugProteinLinked = true;
                break;
            }
        }
        fclose($h);
    }

    // Check protein-disease
    $pdFile = $dataDir . 'ProteinDiseaseAssociationNumber.csv';
    if (file_exists($pdFile)) {
        $h = fopen($pdFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2 && intval($row[0]) === intval($proteinIdx) && intval($row[1]) === intval($diseaseIdx)) {
                $proteinDiseaseLinked = true;
                break;
            }
        }
        fclose($h);
    }

    // Check drug-disease
    $ddFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($ddFile)) {
        $h = fopen($ddFile, 'r');
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2 && intval($row[0]) === intval($drugIdx) && intval($row[1]) === intval($diseaseIdx)) {
                $drugDiseaseLinked = true;
                break;
            }
        }
        fclose($h);
    }

    // Score triplet
    if ($drugProteinLinked && $proteinDiseaseLinked) {
        $score = 80;
        $mechanism = 'Protein-mediated mechanism (D-P-D pathway)';
    } elseif ($drugDiseaseLinked) {
        $score = 90;
        $mechanism = 'Direct drug-disease association';
    } else {
        // Compute similarity-based score
        $predictions = getCSVBasedPredictions($dataDir, 'drug', $drugIdx, 50);
        foreach ($predictions as $p) {
            if ($p['disease_idx'] == $diseaseIdx) {
                $score = $p['score'];
                break;
            }
        }
        $mechanism = 'Network-based similarity prediction';
    }

    $status = 'novel';
    if ($drugDiseaseLinked) {
        $status = 'literature';
    } else {
        $discovered = dbFetchOne($db, "SELECT 1 FROM discovered_links WHERE dataset = ? AND drug_idx = ? AND disease_idx = ?", [$dataset, $drugIdx, $diseaseIdx]);
        if ($discovered) {
            $status = 'previously_discovered';
        } else if ($status === 'novel') {
            dbExec($db, "INSERT IGNORE INTO discovered_links (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)", [$dataset, $drugIdx, $diseaseIdx]);
        }
    }

    $result = [
        'drug_idx' => intval($drugIdx),
        'protein_idx' => intval($proteinIdx),
        'disease_idx' => intval($diseaseIdx),
        'drug_name' => $drugName,
        'protein_name' => $proteinName,
        'disease_name' => $diseaseName,
        'score' => round($score, 2),
        'mechanism' => $mechanism,
        'links' => [
            'drug_protein' => $drugProteinLinked,
            'protein_disease' => $proteinDiseaseLinked,
            'drug_disease' => $drugDiseaseLinked
        ],
        'status' => $status,
        'source' => 'offline'
    ];

    dbExec($db, "INSERT INTO predictions (user_id, dataset, query_type, query_value, results) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $dataset, 'triplet', "$drugName-$proteinName-$diseaseName", json_encode($result)]);

    jsonResponse($result);

} else {
    jsonResponse(['error' => 'type không hợp lệ. Các type hợp lệ: drug_to_disease, disease_to_drug, protein_to_any, triplet'], 400);
}
?>
