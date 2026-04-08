<?php
require_once __DIR__ . '/../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            header('Location: ../login.php?error=' . urlencode('Vui lòng nhập đầy đủ'));
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['password_hash'] === hash('sha256', $password)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: ../index.php');
        } else {
            header('Location: ../login.php?error=' . urlencode('Sai tên đăng nhập hoặc mật khẩu'));
        }
        exit;
        
    case 'register':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (strlen($username) < 3 || strlen($password) < 3) {
            header('Location: ../register.php?error=' . urlencode('Username và password phải >= 3 ký tự'));
            exit;
        }
        
        $db = getDB();
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
            $stmt->execute([$username, hash('sha256', $password)]);
            header('Location: ../login.php?success=' . urlencode('Đăng ký thành công! Hãy đăng nhập.'));
        } catch (PDOException $e) {
            header('Location: ../register.php?error=' . urlencode('Username đã tồn tại'));
        }
        exit;
        
    case 'logout':
        session_destroy();
        header('Location: ../login.php?success=' . urlencode('Đã đăng xuất'));
        exit;
        
    default:
        header('Location: ../login.php');
        exit;
}
?>
