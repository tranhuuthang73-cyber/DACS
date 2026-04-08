<?php
require_once 'includes/config.php';
$pageTitle = 'Đăng ký';
$error = $_GET['error'] ?? '';
include 'includes/header.php';
?>

<div class="auth-container fade-in">
    <div class="auth-card">
        <div class="auth-title">🧬 Đăng ký</div>
        <div class="auth-subtitle">Tạo tài khoản mới</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="api/auth.php" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label class="form-label">Tên đăng nhập</label>
                <input type="text" name="username" class="form-input" placeholder="Tối thiểu 3 ký tự..." required minlength="3">
            </div>
            <div class="form-group">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-input" placeholder="Tối thiểu 3 ký tự..." required minlength="3">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
                <i class="fas fa-user-plus"></i> Đăng ký
            </button>
        </form>

        <div class="auth-footer">
            Đã có tài khoản? <a href="login.php">Đăng nhập</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
