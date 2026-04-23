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
$dsRows = safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM known_associations GROUP BY dataset", [], []);
foreach ($dsRows as $row) {
    $datasetStats[$row['dataset']]['associations'] = $row['cnt'];
}

// Bổ sung và cập nhật số lượng từ thư mục data thực tế
$dataDir = __DIR__ . '/../data';
if (is_dir($dataDir)) {
    $scan = scandir($dataDir);
    foreach($scan as $d) {
        if ($d !== '.' && $d !== '..' && is_dir($dataDir . '/' . $d) && str_ends_with($d, '-dataset')) {
            if (!isset($datasetStats[$d])) {
                $datasetStats[$d] = [
                    'drugs' => 0, 'diseases' => 0, 'associations' => 0,
                    'proteins' => 0, 'drug_protein' => 0, 'protein_disease' => 0
                ];
            }
            
            $dsPath = $dataDir . '/' . $d;
            
            // Đọc số lượng thuốc (có header)
            $drugFile = $dsPath . '/DrugInformation.csv';
            if (file_exists($drugFile)) {
                $lines = file($drugFile, FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $datasetStats[$d]['drugs'] = $count > 0 ? $count - 1 : 0;
            }
            
            // Đọc số lượng bệnh (DiseaseFeature không có header)
            $diseaseFile = $dsPath . '/DiseaseFeature.csv';
            if (file_exists($diseaseFile)) {
                $lines = file($diseaseFile, FILE_SKIP_EMPTY_LINES);
                $datasetStats[$d]['diseases'] = count($lines);
            }
            
            // Đọc số lượng protein (có header)
            $proteinFile = $dsPath . '/ProteinInformation.csv';
            if (file_exists($proteinFile)) {
                $lines = file($proteinFile, FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $datasetStats[$d]['proteins'] = $count > 0 ? $count - 1 : 0;
            }
            
            // Đọc số lượng liên kết Thuốc-Bệnh (có header)
            $assocFile = $dsPath . '/DrugDiseaseAssociationNumber.csv';
            if (file_exists($assocFile)) {
                $lines = file($assocFile, FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $datasetStats[$d]['associations'] = $count > 0 ? $count - 1 : 0;
            }

            // Đọc số lượng liên kết Thuốc-Protein (có header)
            $dpFile = $dsPath . '/DrugProteinAssociationNumber.csv';
            if (file_exists($dpFile)) {
                $lines = file($dpFile, FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $datasetStats[$d]['drug_protein'] = $count > 0 ? $count - 1 : 0;
            }

            // Đọc số lượng liên kết Protein-Bệnh (có header)
            $pdFile = $dsPath . '/ProteinDiseaseAssociationNumber.csv';
            if (file_exists($pdFile)) {
                $lines = file($pdFile, FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $datasetStats[$d]['protein_disease'] = $count > 0 ? $count - 1 : 0;
            }
        }
    }
}
ksort($datasetStats); // Sắp xếp dataset theo tên

// Cập nhật lại tổng số lượng từ các file
$totalDrugs = 0;
$totalDiseases = 0;
$totalProteins = 0;
$totalAssocs = 0;

foreach ($datasetStats as $stats) {
    $totalDrugs += $stats['drugs'] ?? 0;
    $totalDiseases += $stats['diseases'] ?? 0;
    $totalProteins += $stats['proteins'] ?? 0;
    $totalAssocs += ($stats['associations'] ?? 0) + ($stats['drug_protein'] ?? 0) + ($stats['protein_disease'] ?? 0);
}
?>

<div class="stats-grid fade-in" id="features" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalDrugs) ?></div>
        <div class="stat-label">Thuốc trong hệ thống</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalDiseases) ?></div>
        <div class="stat-label">Bệnh trong hệ thống</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalProteins) ?></div>
        <div class="stat-label">Protein trong hệ thống</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalAssocs) ?></div>
        <div class="stat-label">Tổng liên kết đã biết</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($predCount) ?></div>
        <div class="stat-label">Lần dự đoán</div>
    </div>
</div>

<!-- Bảng chi tiết Dataset -->
<div class="fade-in" style="margin: 2rem 0; padding: 1.5rem; background: var(--bg-card); border-radius: 12px; box-shadow: 0 4px 6px var(--shadow-color);">
    <h3 style="margin-bottom: 1.5rem; color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-database"></i> Chi tiết theo Dataset
    </h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary);">
                    <th style="padding: 1rem; white-space: nowrap; min-width: 150px;">Tên Dataset</th>
                    <th style="padding: 1rem;">Số lượng Thuốc</th>
                    <th style="padding: 1rem;">Số lượng Bệnh</th>
                    <th style="padding: 1rem;">Số lượng Protein</th>
                    <th style="padding: 1rem;">Liên kết Thuốc-Bệnh</th>
                    <th style="padding: 1rem;">Liên kết Thuốc-Protein</th>
                    <th style="padding: 1rem;">Liên kết Protein-Bệnh</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datasetStats as $dsName => $stats): ?>
                <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-hover)'" onmouseout="this.style.backgroundColor='transparent'">
                    <td style="padding: 1rem; font-weight: 500; white-space: nowrap;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.875rem; white-space: nowrap;">
                            <?= htmlspecialchars($dsName) ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;"><?= number_format($stats['drugs'] ?? 0) ?></td>
                    <td style="padding: 1rem;"><?= number_format($stats['diseases'] ?? 0) ?></td>
                    <td style="padding: 1rem;"><?= number_format($stats['proteins'] ?? 0) ?></td>
                    <td style="padding: 1rem;"><?= number_format($stats['associations'] ?? 0) ?></td>
                    <td style="padding: 1rem;"><?= number_format($stats['drug_protein'] ?? 0) ?></td>
                    <td style="padding: 1rem;"><?= number_format($stats['protein_disease'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($datasetStats)): ?>
                <tr>
                    <td colspan="7" style="padding: 1rem; text-align: center; color: var(--text-secondary);">Chưa có dữ liệu</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
