<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'AMDGT' ?> - Drug-Disease Prediction</title>
    <meta name="description" content="Hệ thống dự đoán liên kết Thuốc-Bệnh bằng GNN + Persistent Homology">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Theme Script to avoid FOUC (Flash of Unstyled Content) -->
    <script>
        const savedTheme = localStorage.getItem('amdgt-theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark-theme');
            document.addEventListener('DOMContentLoaded', () => { document.body.classList.add('dark-theme'); });
        }
    </script>

    <!-- UI/UX LIBRARIES (Offline-First) -->
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/3Dmol-min.js"></script>
    <script src="assets/js/d3.v7.min.js"></script>
    <script src="https://unpkg.com/d3-sankey@0.12.3/dist/d3-sankey.min.js"></script>
    <script src="assets/js/3d-force-graph.min.js"></script>
</head>

<body>
    <nav class="navbar nav-premium">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <span class="nav-icon"><i class="fas fa-dna"></i></span>
                <span class="nav-title">AMDGT</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Trang chủ</a>
                <a href="predict.php" class="nav-link"><i class="fas fa-search"></i> Dự đoán</a>

                <a href="compare.php" class="nav-link"><i class="fas fa-balance-scale"></i> So sánh</a>
                <?php if (isLoggedIn()): ?>
                    <a href="history.php" class="nav-link"><i class="fas fa-history"></i> Lịch sử</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="nav-link nav-admin"><i class="fas fa-cog"></i> Admin</a>
                <?php endif; ?>
            </div>
            <div class="nav-auth">
                <button id="theme-toggle" class="btn btn-outline btn-sm theme-toggle-btn"
                    title="Chuyển chế độ Giao diện" style="padding: 6px 12px; border-radius: 50%;">
                    <i class="fas fa-moon dark-icon"></i>
                    <i class="fas fa-sun light-icon" style="display:none;"></i>
                </button>
                <?php if (isLoggedIn()): ?>
                    <span class="nav-user"><i class="fas fa-user-circle"></i>
                        <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="api/auth.php?action=logout" class="btn btn-outline btn-sm">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
        // Theme Toggle Logic
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const body = document.body;
            const darkIcon = themeToggleBtn.querySelector('.dark-icon');
            const lightIcon = themeToggleBtn.querySelector('.light-icon');

            // Sync Icon state
            if (body.classList.contains('dark-theme')) {
                lightIcon.style.display = 'none';
                darkIcon.style.display = 'inline-block';
            } else {
                darkIcon.style.display = 'none';
                lightIcon.style.display = 'inline-block';
            }

            themeToggleBtn.addEventListener('click', () => {
                body.classList.toggle('dark-theme');
                document.documentElement.classList.toggle('dark-theme');
                if (body.classList.contains('dark-theme')) {
                    localStorage.setItem('amdgt-theme', 'dark');
                    lightIcon.style.display = 'none';
                    darkIcon.style.display = 'inline-block';
                } else {
                    localStorage.setItem('amdgt-theme', 'light');
                    darkIcon.style.display = 'none';
                    lightIcon.style.display = 'inline-block';
                }
            });

            // Create floating particles
            createParticles();
        });

        // Floating Particles
        function createParticles() {
            const container = document.getElementById('particles');
            if (!container) return;
            
            const colors = ['#0ea5e9', '#6366f1', '#8b5cf6', '#10b981', '#f59e0b'];
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (15 + Math.random() * 10) + 's';
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.width = (4 + Math.random() * 6) + 'px';
                particle.style.height = particle.style.width;
                container.appendChild(particle);
            }
        }

        // Toast Notification System
        window.showToast = function(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        };

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }

        // Skeleton Loading
        window.showSkeleton = function(element, lines = 3) {
            let html = '';
            for (let i = 0; i < lines; i++) {
                const width = 60 + Math.random() * 30;
                html += `<div class="skeleton" style="height: 20px; width: ${width}%; margin-bottom: 10px;"></div>`;
            }
            element.innerHTML = html;
        };
    </script>
    <main class="main-content">