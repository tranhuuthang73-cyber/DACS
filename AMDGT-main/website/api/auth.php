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
        if ($db === null) {
            header('Location: ../login.php?error=' . urlencode('Database chưa được khởi tạo. Hãy chạy setup_db.php!'));
            exit;
        }
        
        // Use prepared statement to prevent SQL injection
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Support both old SHA256 and new bcrypt passwords
        $passwordValid = false;
        if ($user) {
            // Check bcrypt (new format)
            if (password_verify($password, $user['password_hash'])) {
                $passwordValid = true;
            }
            // Check SHA256 (old format for backward compatibility)
            else if ($user['password_hash'] === hash('sha256', $password)) {
                $passwordValid = true;
                // Upgrade to bcrypt
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $user['id']]);
            }
        }
        
        if ($user && $passwordValid) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Redirect based on role
            $redirect = '../index.php';
            if (isset($_GET['redirect'])) {
                $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
            }
            header('Location: ' . $redirect);
        } else {
            header('Location: ../login.php?error=' . urlencode('Sai tên đăng nhập hoặc mật khẩu'));
        }
        exit;
        
    case 'register':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        // Validation
        if (strlen($username) < 3 || strlen($username) > 50) {
            header('Location: ../register.php?error=' . urlencode('Username phải từ 3-50 ký tự'));
            exit;
        }
        if (strlen($password) < 6) {
            header('Location: ../register.php?error=' . urlencode('Mật khẩu phải từ 6 ký tự trở lên'));
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            header('Location: ../register.php?error=' . urlencode('Username chỉ được chứa chữ, số và dấu gạch dưới'));
            exit;
        }
        
        $db = getDB();
        if ($db === null) {
            header('Location: ../register.php?error=' . urlencode('Database chưa được khởi tạo'));
            exit;
        }
        
        try {
            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                header('Location: ../register.php?error=' . urlencode('Username đã tồn tại'));
                exit;
            }
            
            // Hash password with bcrypt
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'user', CURRENT_TIMESTAMP)");
            $stmt->execute([$username, $passwordHash]);
            header('Location: ../login.php?success=' . urlencode('Đăng ký thành công! Hãy đăng nhập.'));
        } catch (PDOException $e) {
            header('Location: ../register.php?error=' . urlencode('Đăng ký thất bại. Vui lòng thử lại.'));
        }
        exit;
        
    case 'change_password':
        // Require login
        if (!isLoggedIn()) {
            header('Location: ../login.php?error=' . urlencode('Vui lòng đăng nhập'));
            exit;
        }
        
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        
        // Validation
        if (empty($currentPassword) || empty($newPassword)) {
            header('Location: ../profile.php?error=' . urlencode('Vui lòng nhập đầy đủ'));
            exit;
        }
        if ($newPassword !== $confirmPassword) {
            header('Location: ../profile.php?error=' . urlencode('Mật khẩu mới không khớp'));
            exit;
        }
        if (strlen($newPassword) < 6) {
            header('Location: ../profile.php?error=' . urlencode('Mật khẩu mới phải từ 6 ký tự'));
            exit;
        }
        if ($currentPassword === $newPassword) {
            header('Location: ../profile.php?error=' . urlencode('Mật khẩu mới phải khác mật khẩu cũ'));
            exit;
        }
        
        $db = getDB();
        if ($db === null) {
            header('Location: ../profile.php?error=' . urlencode('Lỗi database'));
            exit;
        }
        
        try {
            // Get current user
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                header('Location: ../login.php?error=' . urlencode('Người dùng không tồn tại'));
                exit;
            }
            
            // Verify current password
            $currentValid = password_verify($currentPassword, $user['password_hash']);
            if (!$currentValid && $user['password_hash'] !== hash('sha256', $currentPassword)) {
                header('Location: ../profile.php?error=' . urlencode('Mật khẩu hiện tại không đúng'));
                exit;
            }
            
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newHash, $_SESSION['user_id']]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'change_password', 'Password changed successfully');
            
            header('Location: ../profile.php?success=' . urlencode('Đổi mật khẩu thành công!'));
        } catch (PDOException $e) {
            header('Location: ../profile.php?error=' . urlencode('Lỗi: Không thể đổi mật khẩu'));
        }
        exit;
        
    case 'logout':
        // Log activity before destroying session
        if (isLoggedIn()) {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_destroy();
        header('Location: ../login.php?success=' . urlencode('Đã đăng xuất'));
        exit;
        
    case 'api_login':
        // JSON API for AJAX login
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Vui lòng nhập đầy đủ']);
            exit;
        }
        
        $db = getDB();
        if ($db === null) {
            echo json_encode(['success' => false, 'error' => 'Database chưa khả dụng']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        $passwordValid = false;
        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                $passwordValid = true;
            } else if ($user['password_hash'] === hash('sha256', $password)) {
                $passwordValid = true;
            }
        }
        
        if ($user && $passwordValid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            logActivity($user['id'], 'api_login', 'API login successful');
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Sai tên đăng nhập hoặc mật khẩu']);
        }
        exit;
        
    default:
        header('Location: ../login.php');
        exit;
}
?>
