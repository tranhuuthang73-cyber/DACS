<?php
require_once 'includes/config.php';
$pageTitle = 'Đăng nhập';

// Handle messages
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include 'includes/header.php';
?>

<div class="auth-container fade-in">
    <div class="auth-card">
        <div class="auth-title">🧬 Đăng nhập</div>
        <div class="auth-subtitle">Đăng nhập để sử dụng hệ thống dự đoán</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="api/auth.php" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label class="form-label">Tên đăng nhập</label>
                <input type="text" name="username" class="form-input" placeholder="Nhập username..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-input" placeholder="Nhập mật khẩu..." required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
                <i class="fas fa-sign-in-alt"></i> Đăng nhập
            </button>
        </form>

        <div class="auth-footer">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <p style="color: var(--text-muted); font-size: 0.8rem; text-align: center;">
                <strong>Tài khoản mặc định:</strong><br>
                Admin: admin / admin123<br>
                User: user / user123
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
