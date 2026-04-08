<?php
/**
 * Database Setup Script
 * Chạy file này 1 lần để tạo database + import data
 * 
 * Cách chạy: mở trình duyệt http://localhost:88/AMDGT-main/website/setup_db.php
 * Hoặc: php setup_db.php
 */

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

// Create tables
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(64) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_id VARCHAR(20) UNIQUE,
    name VARCHAR(200),
    smiles TEXT,
    idx INT,
    INDEX(idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS diseases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disease_id VARCHAR(20) UNIQUE,
    name VARCHAR(200),
    idx INT,
    INDEX(idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS known_associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_idx INT,
    disease_idx INT,
    UNIQUE KEY(drug_idx, disease_idx)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
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

// Import drugs from CSV
$dataDir = __DIR__ . '/../data/C-dataset/';
$drugFile = $dataDir . 'DrugInformation.csv';

if (file_exists($drugFile)) {
    $handle = fopen($drugFile, 'r');
    $headers = fgetcsv($handle); // skip header
    $idx = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO drugs (drug_id, name, smiles, idx) VALUES (?, ?, ?, ?)");
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 2) {
            $stmt->execute([$row[0], $row[1], $row[2] ?? '', $idx]);
            $idx++;
        }
    }
    fclose($handle);
    echo "[OK] Imported $idx drugs\n";
} else {
    echo "[WARN] Drug file not found: $drugFile\n";
}

// Import diseases from AllNode.csv (nodes starting with 'D')
$nodeFile = $dataDir . 'AllNode.csv';
if (file_exists($nodeFile)) {
    $lines = file($nodeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $diseaseIdx = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO diseases (disease_id, name, idx) VALUES (?, ?, ?)");
    foreach ($lines as $line) {
        $nodeId = trim($line);
        if (substr($nodeId, 0, 1) === 'D') {
            $stmt->execute([$nodeId, "Disease $nodeId", $diseaseIdx]);
            $diseaseIdx++;
        }
    }
    echo "[OK] Imported $diseaseIdx diseases\n";
}

// Import associations
$assocFile = $dataDir . 'DrugDiseaseAssociationNumber.csv';
if (file_exists($assocFile)) {
    $handle = fopen($assocFile, 'r');
    fgetcsv($handle); // skip header
    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO known_associations (drug_idx, disease_idx) VALUES (?, ?)");
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 2) {
            $stmt->execute([(int) $row[0], (int) $row[1]]);
            $count++;
        }
    }
    fclose($handle);
    echo "[OK] Imported $count associations\n";
}

// Summary
$drugCount = $pdo->query("SELECT COUNT(*) FROM drugs")->fetchColumn();
$diseaseCount = $pdo->query("SELECT COUNT(*) FROM diseases")->fetchColumn();
$assocCount = $pdo->query("SELECT COUNT(*) FROM known_associations")->fetchColumn();

echo "\n============================\n";
echo "[OK] SETUP COMPLETE!\n";
echo "  Drugs: $drugCount\n";
echo "  Diseases: $diseaseCount\n";
echo "  Associations: $assocCount\n";
echo "============================\n";
echo "\nBước tiếp theo:\n";
echo "1. Chạy AI Server: python website/ai_server.py\n";
echo "2. Mở website: http://localhost:88/AMDGT-main/website/\n";
echo "</pre>";
?>