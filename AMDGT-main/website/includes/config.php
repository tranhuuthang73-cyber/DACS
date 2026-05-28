<?php
/**
 * Cau hinh AMDGT
 * Database + AI Server settings
 */

// Chan direct access
if (basename($_SERVER['PHP_SELF']) == 'config.php') {
    die('Truy cap bi cam.');
}

// ========== DATABASE CONFIG ==========
define('DB_HOST', 'localhost');
define('DB_NAME', 'amdgt');
define('DB_USER', 'root');
define('DB_PASS', '');

// ========== AI SERVER CONFIG ==========
define('AI_SERVER_URL', 'http://127.0.0.1:5001');

// ========== PATH CONFIG ==========
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_PATH', $base);
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $base);
define('DATA_DIR', __DIR__ . '/../../data');
define('ASSETS_URL', SITE_URL . '/assets');

// ========== SESSION ==========
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ========== DATABASE CONNECTION ==========
// ========== SQLITE INTERCEPTOR ==========
class SQLitePDO extends PDO {
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = array()) {
        $query = str_ireplace("INSERT IGNORE", "INSERT OR IGNORE", $query);
        return parent::prepare($query, $options);
    }
    #[\ReturnTypeWillChange]
    public function exec($statement) {
        $statement = str_ireplace("INSERT IGNORE", "INSERT OR IGNORE", $statement);
        return parent::exec($statement);
    }
    #[\ReturnTypeWillChange]
    public function query($statement, $mode = null, ...$extra_params) {
        $statement = str_ireplace("INSERT IGNORE", "INSERT OR IGNORE", $statement);
        if ($mode === null) {
            return parent::query($statement);
        }
        return parent::query($statement, $mode, ...$extra_params);
    }
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $sqlitePath = __DIR__ . '/../../data/database.sqlite';
        
        // NẾU CÓ FILE DATABASE.SQLITE THÌ SỬ DỤNG LUÔN (Không cần chạy MySQL server)
        if (file_exists($sqlitePath)) {
            try {
                $pdo = new SQLitePDO("sqlite:" . $sqlitePath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec("PRAGMA foreign_keys = ON;"); // Kích hoạt Foreign Key của SQLite
                return $pdo;
            } catch (PDOException $e) {
                error_log("Loi ket noi SQLite: " . $e->getMessage());
            }
        }
        
        // Tự động dò cổng MySQL phổ biến (3306, 3307, 3308) để tránh lỗi khi máy khác bị trùng cổng
        $ports = ['3306', '3307', '3308'];
        $connected = false;
        $lastException = null;
        
        foreach ($ports as $port) {
            try {
                $host = DB_HOST;
                if (strpos($host, ':') === false && $host === 'localhost') {
                    $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                } else {
                    $dsn = "mysql:host=" . $host . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                }
                
                $pdo = new PDO(
                    $dsn,
                    DB_USER, DB_PASS,
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
                );
                $connected = true;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                continue;
            }
        }
        
        if (!$connected) {
            error_log("Loi database: " . ($lastException ? $lastException->getMessage() : "Khong the ket noi"));
            return null;
        }
    }
    return $pdo;
}

// ========== TRUY VAN AN TOAN ==========
function safeQuery($sql, $params = array(), $default = null) {
    try {
        $db = getDB();
        if ($db === null) return $default;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        error_log("Loi truy van: " . $e->getMessage());
        return $default;
    }
}

function safeQueryAll($sql, $params = array(), $default = array()) {
    try {
        $db = getDB();
        if ($db === null) return $default;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Loi truy van: " . $e->getMessage());
        return $default;
    }
}

// ========== AUTH HELPERS ==========
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Can quyen quan tri');
    }
}

// ========== JSON RESPONSE ==========
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ========== AI SERVER CALL ==========
function callAI($endpoint, $data = array(), $timeout = 60) {
    $url = AI_SERVER_URL . $endpoint;
    $ch = curl_init($url);
    
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json'),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("Loi ket noi AI Server: " . $error);
        return array('error' => 'Khong the ket noi AI Server: ' . $error);
    }
    
    if ($httpCode != 200) {
        error_log("Loi AI Server: HTTP $httpCode - $response");
        return array('error' => "AI Server loi (HTTP $httpCode)");
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        error_log("Loi doc phan hoi tu AI Server: " . json_last_error_msg());
        return array('error' => 'Loi doc phan hoi tu AI Server');
    }
    
    return $decoded;
}

// ========== DATA FILE HELPERS ==========
function getDatasetDir($dataset = 'C-dataset') {
    $dir = DATA_DIR . '/' . $dataset;
    return is_dir($dir) ? $dir : null;
}

function readCSV($filepath, $hasHeader = true) {
    if (!file_exists($filepath)) return array();
    
    $data = array();
    $handle = fopen($filepath, 'r');
    
    if ($hasHeader) fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        $data[] = $row;
    }
    fclose($handle);
    return $data;
}

// ========== LOGGING ==========
function logActivity($userId, $action, $details = '') {
    try {
        $db = getDB();
        if ($db === null) return;
        
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute(array($userId, $action, $details));
    } catch (PDOException $e) {
        error_log("Loi ghi nhat ky: " . $e->getMessage());
    }
}

// ========== SECURITY HELPERS ==========
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>