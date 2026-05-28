<?php


echo "<h1>🧬 AMDGT Database Setup</h1><pre>\n";

// Chọn database engine: Mặc định tự dò MySQL, nếu thêm tham số ?db=sqlite sẽ dùng SQLite
$isSqlite = isset($_GET['db']) && $_GET['db'] === 'sqlite';
$sqlitePath = __DIR__ . '/../data/database.sqlite';

if ($isSqlite) {
    try {
        if (!is_dir(dirname($sqlitePath))) {
            mkdir(dirname($sqlitePath), 0777, true);
        }
        $pdo = new PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "[OK] Connected to SQLite database ($sqlitePath)\n";
    } catch (PDOException $e) {
        die("[ERROR] Cannot connect SQLite: " . $e->getMessage() . "\n");
    }
} else {
    // Connect MySQL (Laragon default: root, no password) - Tự động dò cổng 3306, 3307, 3308
    $ports = ['3306', '3307', '3308'];
    $pdo = null;
    $errorMsg = "";
    foreach ($ports as $port) {
        try {
            $pdo = new PDO("mysql:host=localhost;port=$port", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "[OK] Connected to MySQL on port $port\n";
            break;
        } catch (PDOException $e) {
            $errorMsg .= "Port $port: " . $e->getMessage() . "; ";
        }
    }

    if (!$pdo) {
        die("[ERROR] Cannot connect MySQL (tested ports 3306, 3307, 3308). Error details: " . $errorMsg . "\n");
    }

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS amdgt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE amdgt");
    echo "[OK] Database 'amdgt' created\n";
}

// ⚠️  Chỉ drop tables nếu có tham số ?reset=1 trong URL (tránh mất dữ liệu vô tình)
$forceReset = isset($_GET['reset']) && $_GET['reset'] === '1';

if ($forceReset) {
    echo "<div style='background:#fee2e2;border:1px solid #ef4444;padding:1rem;border-radius:8px;margin:1rem 0;'>
        ⚠️ <strong>CHẾ ĐỘ RESET:</strong> Đang xóa toàn bộ dữ liệu cũ...
    </div>\n";
    $pdo->exec("DROP TABLE IF EXISTS predictions");
    $pdo->exec("DROP TABLE IF EXISTS known_associations");
    $pdo->exec("DROP TABLE IF EXISTS proteins");
    $pdo->exec("DROP TABLE IF EXISTS drugs");
    $pdo->exec("DROP TABLE IF EXISTS diseases");
    $pdo->exec("DROP TABLE IF EXISTS activity_log");
    echo "[OK] Đã xóa bảng cũ\n";
} else {
    echo "<div style='background:#dcfce7;border:1px solid #22c55e;padding:1rem;border-radius:8px;margin:1rem 0;'>
        ✅ <strong>Chế độ an toàn:</strong> Chỉ thêm dữ liệu mới, không xóa dữ liệu cũ.<br>
        Để reset hoàn toàn: <a href='?reset=1' style='color:#dc2626;font-weight:bold;'>setup_db.php?reset=1</a>
    </div>\n";
}

if ($isSqlite) {
    // Tạo bảng cho SQLite
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(64) NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS drugs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        drug_id VARCHAR(20),
        name VARCHAR(200),
        smiles TEXT,
        idx INT,
        UNIQUE(dataset, idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS diseases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        disease_id VARCHAR(20),
        name VARCHAR(200),
        idx INT,
        UNIQUE(dataset, idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS proteins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        protein_id VARCHAR(100),
        name TEXT,
        idx INT,
        UNIQUE(dataset, idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS known_associations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        drug_idx INT,
        disease_idx INT,
        UNIQUE(dataset, drug_idx, disease_idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS predictions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INT,
        dataset VARCHAR(20) DEFAULT 'C-dataset',
        query_type VARCHAR(50),
        query_value VARCHAR(200),
        results TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        drug_idx INT NOT NULL,
        disease_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (dataset, drug_idx, disease_idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_dp_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        drug_idx INT NOT NULL,
        protein_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (dataset, drug_idx, protein_idx)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_pd_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dataset VARCHAR(20) NOT NULL,
        protein_idx INT NOT NULL,
        disease_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (dataset, protein_idx, disease_idx)
    )");
} else {
    // Tạo bảng cho MySQL
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dataset VARCHAR(20) NOT NULL,
        drug_idx INT NOT NULL,
        disease_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_discovery (dataset, drug_idx, disease_idx)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_dp_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dataset VARCHAR(20) NOT NULL,
        drug_idx INT NOT NULL,
        protein_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_dp (dataset, drug_idx, protein_idx)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discovered_pd_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dataset VARCHAR(20) NOT NULL,
        protein_idx INT NOT NULL,
        disease_idx INT NOT NULL,
        discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_pd (dataset, protein_idx, disease_idx)
    ) ENGINE=InnoDB");
}

echo "[OK] Tables created\n";

// Prefix cho câu lệnh INSERT (MySQL là INSERT IGNORE, SQLite là INSERT OR IGNORE)
$insertPrefix = $isSqlite ? "INSERT OR IGNORE" : "INSERT IGNORE";

// Create default users
$adminPw = hash('sha256', 'admin123');
$userPw = hash('sha256', 'user123');
try {
    $pdo->exec("$insertPrefix INTO users (username, password_hash, role) VALUES ('admin', '$adminPw', 'admin')");
    $pdo->exec("$insertPrefix INTO users (username, password_hash, role) VALUES ('user', '$userPw', 'user')");
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
        $stmt = $pdo->prepare("$insertPrefix INTO drugs (dataset, drug_id, name, smiles, idx) VALUES (?, ?, ?, ?, ?)");
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
        $stmt = $pdo->prepare("$insertPrefix INTO diseases (dataset, disease_id, name, idx) VALUES (?, ?, ?, ?)");
        
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
        $stmt = $pdo->prepare("$insertPrefix INTO proteins (dataset, protein_id, name, idx) VALUES (?, ?, ?, ?)");
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
        $stmt = $pdo->prepare("$insertPrefix INTO known_associations (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)");
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2) {
                $stmt->execute([$dsName, (int)$row[0], (int)$row[1]]);
                $count++;
            }
        }
        fclose($handle);
        echo "[OK] ($dsName) Imported $count associations\n";
    }

    // Tự động sinh dữ liệu "AI Discoveries" chất lượng cao để hiển thị so sánh ngay lập tức
    echo "<h4>Generating AI Discoveries for $dsName...</h4>\n";
    
    // Load known associations into memory
    $ddByDrug = [];
    $ddByDisease = [];
    $stmt = $pdo->prepare("SELECT drug_idx, disease_idx FROM known_associations WHERE dataset = ?");
    $stmt->execute([$dsName]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)$row['drug_idx'];
        $di = (int)$row['disease_idx'];
        $ddByDrug[$d][] = $di;
        $ddByDisease[$di][] = $d;
    }
    
    // Get total drug and disease counts
    $numDrugs = (int)$pdo->query("SELECT COUNT(*) FROM drugs WHERE dataset = '$dsName'")->fetchColumn();
    $numDiseases = (int)$pdo->query("SELECT COUNT(*) FROM diseases WHERE dataset = '$dsName'")->fetchColumn();
    
    // Setup insert statements
    $insertDD = $pdo->prepare("$insertPrefix INTO discovered_links (dataset, drug_idx, disease_idx) VALUES (?, ?, ?)");
    
    $discoveryCount = 0;
    // Loop over drugs and diseases to find high-scoring Jaccard connections
    for ($d = 0; $d < $numDrugs; $d++) {
        $queryNeighbors = $ddByDrug[$d] ?? [];
        if (empty($queryNeighbors)) continue;
        
        for ($di = 0; $di < $numDiseases; $di++) {
            if (in_array($di, $queryNeighbors)) continue; // skip already known
            
            $diseaseNeighbors = $ddByDisease[$di] ?? [];
            if (empty($diseaseNeighbors)) continue;
            
            // Jaccard calculation
            $intersection = count(array_intersect($queryNeighbors, $diseaseNeighbors));
            if ($intersection > 0) {
                $union = count(array_unique(array_merge($queryNeighbors, $diseaseNeighbors)));
                $jaccard = $intersection / $union;
                
                // If Jaccard similarity is reasonably high, count as AI discovery!
                if ($jaccard > 0.05) {
                    $insertDD->execute([$dsName, $d, $di]);
                    $discoveryCount++;
                    // Limit discoveries per dataset to keep setup fast and database size balanced
                    if ($discoveryCount >= 1500) {
                        break 2;
                    }
                }
            }
        }
    }
    echo "[OK] Generated $discoveryCount AI discoveries for $dsName\n";
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