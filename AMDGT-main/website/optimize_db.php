<?php
/**
 * Toi uu hieu suat Database
 * Chay script nay de toi uu hoa database
 * 
 * Cach su dung:
 * 1. Mo trinh duyet: http://localhost/website/optimize_db.php
 * 2. Hoac chay tu command line: php optimize_db.php
 */

// Chan direct web access trong production
if (php_sapi_name() !== 'cli' && !defined('ALLOW_DIRECT_ACCESS')) {
    if (basename($_SERVER['PHP_SELF']) === 'optimize_db.php') {
        die('Truy cap bi tu choi. Hay chay tu CLI hoac dinh nghia ALLOW_DIRECT_ACCESS.');
    }
}

require_once __DIR__ . '/../website/includes/config.php';

echo "=== AMDGT Toi uu Database ===\n\n";

$db = getDB();
if ($db === null) {
    echo "LOI: Khong ket noi duoc database. Hay chay setup_db.php truoc.\n";
    exit(1);
}

$optimizations = 0;
$errors = 0;

// 1. Tao cac chi muc cho truy van nhanh hon
echo "1. Tao chi muc...\n";
$indexes = [
    // Chi muc bang drugs
    "CREATE INDEX IF NOT EXISTS idx_drugs_dataset ON drugs(dataset)",
    "CREATE INDEX IF NOT EXISTS idx_drugs_name ON drugs(name)",
    "CREATE INDEX IF NOT EXISTS idx_drugs_idx ON drugs(idx)",
    
    // Chi muc bang diseases
    "CREATE INDEX IF NOT EXISTS idx_diseases_dataset ON diseases(dataset)",
    "CREATE INDEX IF NOT EXISTS idx_diseases_name ON diseases(name)",
    "CREATE INDEX IF NOT EXISTS idx_diseases_idx ON diseases(idx)",
    
    // Chi muc bang proteins
    "CREATE INDEX IF NOT EXISTS idx_proteins_dataset ON proteins(dataset)",
    "CREATE INDEX IF NOT EXISTS idx_proteins_name ON proteins(name)",
    "CREATE INDEX IF NOT EXISTS idx_proteins_idx ON proteins(idx)",
    
    // Chi muc bang known associations (rat quan trong cho du doan)
    "CREATE INDEX IF NOT EXISTS idx_associations_dataset ON known_associations(dataset)",
    "CREATE INDEX IF NOT EXISTS idx_associations_drug ON known_associations(drug_idx)",
    "CREATE INDEX IF NOT EXISTS idx_associations_disease ON known_associations(disease_idx)",
    "CREATE INDEX IF NOT EXISTS idx_associations_drug_disease ON known_associations(dataset, drug_idx, disease_idx)",
    
    // Chi muc bang predictions
    "CREATE INDEX IF NOT EXISTS idx_predictions_user ON predictions(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_predictions_dataset ON predictions(dataset)",
    "CREATE INDEX IF NOT EXISTS idx_predictions_type ON predictions(query_type)",
    "CREATE INDEX IF NOT EXISTS idx_predictions_date ON predictions(created_at)",
    
    // Chi muc bang users
    "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
    
    // Chi muc bang activity log
    "CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_log(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_log(created_at)",
];

foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        $optimizations++;
        echo "   [OK] " . str_replace("CREATE INDEX IF NOT EXISTS ", "", $sql) . "\n";
    } catch (PDOException $e) {
        // Chi muc da ton tai thi bo qua
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "   [!] " . basename(str_replace("CREATE INDEX IF NOT EXISTS ", "", $sql)) . ": " . $e->getMessage() . "\n";
        }
    }
}

// 2. Tao bang activity_log neu chua co
echo "\n2. Tao bang activity_log...\n";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_user (user_id),
            INDEX idx_activity_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   [OK] Bang activity_log da san sang\n";
    $optimizations++;
} catch (PDOException $e) {
    echo "   [!] activity_log: " . $e->getMessage() . "\n";
    $errors++;
}

// 3. Them cac cot thieu vao bang users
echo "\n3. Kiem tra cau truc bang users...\n";
try {
    $columns = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    
    $alterations = [];
    if (!in_array('created_at', $columns)) {
        $alterations[] = "ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
    }
    if (!in_array('updated_at', $columns)) {
        $alterations[] = "ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }
    if (!in_array('last_login', $columns)) {
        $alterations[] = "ADD COLUMN last_login DATETIME";
    }
    
    foreach ($alterations as $alt) {
        $db->exec("ALTER TABLE users $alt");
        echo "   [OK] users.$alt\n";
        $optimizations++;
    }
    
    if (empty($alterations)) {
        echo "   [OK] Cau truc bang users OK\n";
    }
} catch (PDOException $e) {
    echo "   [!] Cau truc users: " . $e->getMessage() . "\n";
    $errors++;
}

// 4. Toi uu tat ca cac bang
echo "\n4. Toi uu cac bang...\n";
$tables = ['drugs', 'diseases', 'proteins', 'known_associations', 'predictions', 'users'];
foreach ($tables as $table) {
    try {
        $db->exec("OPTIMIZE TABLE $table");
        echo "   [OK] $table da toi uu\n";
        $optimizations++;
    } catch (PDOException $e) {
        echo "   [!] $table: " . $e->getMessage() . "\n";
    }
}

// 5. Don sach du lieu cu (tuy chon)
echo "\n5. Don sach du lieu cu...\n";
try {
    // Dem so luong predictions truoc
    $stmt = $db->query("SELECT COUNT(*) FROM predictions");
    $before = $stmt->fetchColumn();
    
    // Dong y de giu lai du lieu - khong xoa
    echo "   [OK] Giu nguyen tat ca predictions (neu can xoa thu cong)\n";
} catch (PDOException $e) {
    echo "   [!] Don sach: " . $e->getMessage() . "\n";
}

// 6. Phan tich cac bang de toi uu truy van
echo "\n6. Phan tich cac bang...\n";
foreach ($tables as $table) {
    try {
        $db->exec("ANALYZE TABLE $table");
        echo "   [OK] $table da phan tich\n";
    } catch (PDOException $e) {
        // Bo qua loi phan tich
    }
}

// 7. Tom tat
echo "\n=== Tom tat ===\n";
echo "So lan toi uu: $optimizations\n";
echo "So loi: $errors\n\n";

if ($errors === 0) {
    echo "[OK] Toi uu database thanh cong!\n";
    echo "\nCac cai tien duoc cho:\n";
    echo "- Tim kiem tu dong nhanh hon\n";
    echo "- Tra cuu thuoc/benh nhanh hon\n";
    echo "- Truy van du doan nhanh hon\n";
    echo "- Hieu suat database tong the tot hon\n";
} else {
    echo "[!] Mot so toi uu that bai. Kiem tra loi o tren.\n";
    echo "Phan lon cac chuc nang van hoat dong.\n";
}

echo "\n=== Toi uu Asset ===\n\n";

// Toi uu CSS
echo "7. Toi uu CSS...\n";
$cssFile = __DIR__ . '/../website/assets/css/style.css';
if (file_exists($cssFile)) {
    $css = file_get_contents($cssFile);
    
    // Xoa cac binh luan
    $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    
    // Xoa khoang trang thua
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};,:])\s*/', '$1', $css);
    $css = preg_replace('/;}/', '}', $css);
    
    // Luu phien ban da nen
    $minifiedFile = __DIR__ . '/../website/assets/css/style.min.css';
    file_put_contents($minifiedFile, $css);
    
    $originalSize = filesize($cssFile);
    $minifiedSize = strlen($css);
    $saved = round((1 - $minifiedSize / $originalSize) * 100, 1);
    
    echo "   [OK] style.css: " . round($originalSize / 1024, 1) . "KB -> " . round($minifiedSize / 1024, 1) . "KB (giam $saved%)\n";
}

echo "\nXong! Hay truy cap website de kiem tra moi thu hoat dong.\n";
?>
