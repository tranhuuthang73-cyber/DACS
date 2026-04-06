<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'AMDGT' ?> - Drug-Disease Prediction</title>
    <meta name="description" content="Hệ thống dự đoán liên kết Thuốc-Bệnh bằng GNN + Persistent Homology">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <span class="nav-icon">🧬</span>
                <span class="nav-title">AMDGT</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Trang chủ</a>
                <a href="predict.php" class="nav-link"><i class="fas fa-search"></i> Dự đoán</a>
                <?php if (isLoggedIn()): ?>
                    <a href="history.php" class="nav-link"><i class="fas fa-history"></i> Lịch sử</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="nav-link nav-admin"><i class="fas fa-cog"></i> Admin</a>
                <?php endif; ?>
            </div>
            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <span class="nav-user"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="api/auth.php?action=logout" class="btn btn-outline btn-sm">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="main-content">
