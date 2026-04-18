<?php
require_once 'includes/config.php';
$pageTitle = 'Trang chủ';
include 'includes/header.php';
?>

<section class="hero fade-in">
    <div class="hero-badge">
        <i class="fas fa-flask"></i> GNN + Persistent Homology
    </div>
    <h1>Dự Đoán Liên Kết<br><span class="gradient-text">Thuốc – Bệnh</span></h1>
    <p>Hệ thống AI sử dụng Graph Neural Network kết hợp đặc trưng cấu trúc đồ thị (Persistent Homology) để dự đoán mối liên hệ giữa thuốc và bệnh.</p>
    <div class="hero-actions">
        <a href="predict.php" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Bắt đầu dự đoán</a>
        <a href="#features" class="btn btn-outline btn-lg"><i class="fas fa-info-circle"></i> Tìm hiểu thêm</a>
    </div>
</section>

<?php
// Stats - tổng hợp từ tất cả dataset
$drugCount = safeQuery("SELECT COUNT(*) FROM drugs", [], 0);
$diseaseCount = safeQuery("SELECT COUNT(*) FROM diseases", [], 0);
$assocCount = safeQuery("SELECT COUNT(*) FROM known_associations", [], 0);
$predCount = safeQuery("SELECT COUNT(*) FROM predictions", [], 0);

// Hiển thị theo từng dataset nếu có
$datasetStats = [];
$dsRows = safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM drugs GROUP BY dataset", [], []);
foreach ($dsRows as $row) {
    $datasetStats[$row['dataset']]['drugs'] = $row['cnt'];
}
$dsRows = safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM diseases GROUP BY dataset", [], []);
foreach ($dsRows as $row) {
    $datasetStats[$row['dataset']]['diseases'] = $row['cnt'];
}
?>

<div class="stats-grid fade-in" id="features">
    <div class="stat-card">
        <div class="stat-value"><?= $drugCount ?></div>
        <div class="stat-label">Thuốc trong hệ thống</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $diseaseCount ?></div>
        <div class="stat-label">Bệnh trong hệ thống</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $assocCount ?></div>
        <div class="stat-label">Liên kết đã biết</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $predCount ?></div>
        <div class="stat-label">Lần dự đoán</div>
    </div>
</div>

<div class="card-grid">
    <div class="card fade-in">
        <div class="card-header">
            <div class="card-icon purple"><i class="fas fa-pills"></i></div>
            <div class="card-title">Thuốc → Bệnh</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Nhập tên thuốc để tìm các bệnh tiềm năng mà thuốc có thể điều trị. Mô hình AI sẽ phân tích cấu trúc đồ thị và trả về top kết quả.</p>
    </div>
    <div class="card fade-in">
        <div class="card-header">
            <div class="card-icon blue"><i class="fas fa-virus"></i></div>
            <div class="card-title">Bệnh → Thuốc</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Nhập tên bệnh để tìm các thuốc tiềm năng có thể điều trị. Hệ thống sử dụng Dual Graph Transformer để dự đoán chính xác.</p>
    </div>
    <div class="card fade-in">
        <div class="card-header">
            <div class="card-icon green"><i class="fas fa-project-diagram"></i></div>
            <div class="card-title">Persistent Homology</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Trích xuất đặc trưng topo (H0: connected components, H1: loops) từ đồ thị Drug-Disease để tăng cường khả năng dự đoán.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
