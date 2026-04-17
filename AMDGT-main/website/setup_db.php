<?php


echo "<h1>🧬 AMDGT Database Setup</h1><pre>\n";

// Connect MySQL (Laragon default: root, no password)
try {
    $pdo = new PDO("mysql:host=localhost", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "[OK] Connected to MySQL\n";
} catch (PDOException $e) {
    die("[ERROR] Cannot connect MySQL: " . $e->getMessage() . "\n");
}

// Create database
$pdo->exec("CREATE DATABASE IF NOT EXISTS amdgt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE amdgt");
echo "[OK] Database 'amdgt' created\n";

// Drop old tables to apply new schema
$pdo->exec("DROP TABLE IF EXISTS predictions");
$pdo->exec("DROP TABLE IF EXISTS known_associations");
$pdo->exec("DROP TABLE IF EXISTS proteins");
$pdo->exec("DROP TABLE IF EXISTS drugs");
$pdo->exec("DROP TABLE IF EXISTS diseases");

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(64) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dataset VARCHAR(20) NOT NULL,
    drug_id VARCHAR(20),
    name VARCHAR(200),
    smiles TEXT,
    idx INT,
    INDEX(idx),
    UNIQUE KEY(dataset, idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS diseases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dataset VARCHAR(20) NOT NULL,
    disease_id VARCHAR(20),
    name VARCHAR(200),
    idx INT,
    INDEX(idx),
    UNIQUE KEY(dataset, idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS proteins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dataset VARCHAR(20) NOT NULL,
    protein_id VARCHAR(100),
    name TEXT,
    idx INT,
    INDEX(idx),
    UNIQUE KEY(dataset, idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS known_associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dataset VARCHAR(20) NOT NULL,
    drug_idx INT,
    disease_idx INT,
    UNIQUE KEY(dataset, drug_idx, disease_idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    dataset VARCHAR(20) DEFAULT 'C-dataset',
    query_type VARCHAR(50),
    query_value VARCHAR(200),
    results TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB");

echo "[OK] Tables created\n";

// Create default users
$adminPw = hash('sha256', 'admin123');
$userPw = hash('sha256', 'user123');
try {
    $pdo->exec("INSERT IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$adminPw', 'admin')");
    $pdo->exec("INSERT IGNORE INTO users (username, password_hash, role) VALUES ('user', '$userPw', 'user')");
    echo "[OK] Default users created (admin/admin123, user/user123)\n";
} catch (Exception $e) {
    echo "[SKIP] Users already exist\n";
}

// Import loop for all datasets
$datasets = ['B-dataset', 'C-dataset', 'F-dataset'];

foreach ($datasets as $dsName) {
    echo "\n<h3>Importing $dsName</h3>\n";
    $dataDir = __DIR__ . "/../data/$dsName/";
    $drugFile = $dataDir . 'DrugInformation.csv';

    if (file_exists($drugFile)) {
        $handle = fopen($drugFile, 'r');
        $headers = fgetcsv($handle); // skip header
        $idx = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO drugs (dataset, drug_id, name, smiles, idx) VALUES (?, ?, ?, ?, ?)");
        while (($row = fgetcsv($handle)) !== false) {
            // F-dataset has different column arrangement: ,id,name,smiles -> col 1 is id, 2 is name, 3 is smiles
            // B/C-dataset: id,name,smiles -> wait, B-dataset is name,id,smiles!
            // Let's check headers first
            $headerStr = strtolower(implode(",", $headers));
            
            $drug_id = '';
            $name = '';
            $smiles = '';
            
            if (strpos($headerStr, 'name,id,smiles') !== false) {
                // B-dataset
                $name = $row[0] ?? '';
                $drug_id = $row[1] ?? '';
                $smiles = $row[2] ?? '';
            } else if (strpos($headerStr, 'id,name,smiles') !== false) {
                // C and F
                $offset = count($headers) == 4 ? 1 : 0; // F has an empty first col
                if (count($headers) == 4 && strpos($headers[1], 'id') !== false) {
                   $drug_id = $row[1] ?? '';
                   $name = $row[2] ?? '';
                   $smiles = $row[3] ?? '';
                } else {
                   $drug_id = $row[0] ?? '';
                   $name = $row[1] ?? '';
                   $smiles = $row[2] ?? '';
                }
            } else {
                // fallback
                $drug_id = $row[0] ?? '';
                $name = $row[1] ?? '';
                $smiles = $row[2] ?? '';
            }
            
            if ($drug_id) {
                $stmt->execute([$dsName, $drug_id, $name, $smiles, $idx]);
                $idx++;
            }
        }
        fclose($handle);
        echo "[OK] ($dsName) Imported $idx drugs\n";
    } else {
        echo "[WARN] ($dsName) Drug file not found: $drugFile\n";
    }

    // Import diseases
    $nodeFile = file_exists($dataDir . 'AllNode.csv') ? ($dataDir . 'AllNode.csv') : ($dataDir . 'Allnode.csv');
    if (file_exists($nodeFile)) {
        $lines = file($nodeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Count exact diseases from DiseaseFeature.csv to prevent pulling proteins
        $diseaseFeatureFile = $dataDir . 'DiseaseFeature.csv';
        $diseaseCount = 0;
        if (file_exists($diseaseFeatureFile)) {
            $diseaseCount = count(file($diseaseFeatureFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        $diseaseIdx = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO diseases (dataset, disease_id, name, idx) VALUES (?, ?, ?, ?)");
        
        for ($i = $idx; $i < $idx + $diseaseCount && $i < count($lines); $i++) {
            $line = $lines[$i];
            $nodeCols = str_getcsv($line);
            $nodeName = trim($nodeCols[1] ?? $nodeCols[0] ?? '');
            
            $d_id = $nodeName;
            $d_name = $nodeName;
            
            if (preg_match('/^D\d+$/i', $d_name)) {
                $d_name = "Bệnh " . $d_name;
            }
            
            $stmt->execute([$dsName, $d_id, $d_name, $diseaseIdx]);
            $diseaseIdx++;
        }
        echo "[OK] ($dsName) Imported $diseaseIdx diseases\n";
    }

    // Import proteins
    $proteinFile = $dataDir . 'ProteinInformation.csv';
    if (file_exists($proteinFile)) {
        $handle = fopen($proteinFile, 'r');
        fgetcsv($handle); // skip header
        $pIdx = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO proteins (dataset, protein_id, name, idx) VALUES (?, ?, ?, ?)");
        while (($row = fgetcsv($handle)) !== false) {
            $p_id = $row[0] ?? $row[1] ?? '';
            if ($p_id) {
                // Often protein name is the ID or we can generate a label
                $p_name = "Protein " . $p_id;
                $stmt->execute([$dsName, $p_id, $p_name, $pIdx]);
                $pIdx++;
            }
        }
        fclose($handle);
        echo "[OK] ($dsName) Imported $pIdx proteins\n";
    }

    // Import associations
    $assocFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
    if (file_exists($assocFile)) {
        $handle = fopen($assocFile, 'r');
        fgetcsv($handle); // skip header
        $count = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO known_associations (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)");
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2) {
                $stmt->execute([$dsName, (int)$row[0], (int)$row[1]]);
                $count++;
            }
        }
        fclose($handle);
        echo "[OK] ($dsName) Imported $count associations\n";
    }
}

// Summary
$drugCount = $pdo->query("SELECT COUNT(*) FROM drugs")->fetchColumn();
$diseaseCount = $pdo->query("SELECT COUNT(*) FROM diseases")->fetchColumn();
$assocCount = $pdo->query("SELECT COUNT(*) FROM known_associations")->fetchColumn();

echo "\n<h2>Total System Stats:</h2>\n";
echo "<ul>\n";
echo "<li>💊 Configured Drugs: <b>$drugCount</b></li>\n";
echo "<li>🦠 Configured Diseases: <b>$diseaseCount</b></li>\n";
echo "<li>🔗 Known Associations: <b>$assocCount</b></li>\n";
echo "</ul>\n";


?>