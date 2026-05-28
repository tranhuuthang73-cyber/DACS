<?php
require_once 'includes/config.php';
$pageTitle = 'Trang chủ';
include 'includes/header.php';
?>

<section class="hero fade-in" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,0.2); border-radius: 24px; padding: 4rem 2rem; text-align: center; margin-bottom: 3rem; box-shadow: 0 10px 30px -10px rgba(99,102,241,0.2);">
    <div class="hero-badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; padding: 8px 16px; border-radius: 20px; display: inline-block; margin-bottom: 1.5rem; font-weight: 700; border: 1px solid rgba(99,102,241,0.3);">
        <i class="fas fa-flask"></i> GNN + Persistent Homology
    </div>
    <h1 style="font-size: 3.5rem; font-weight: 900; margin-bottom: 1.5rem;">Dự Đoán Liên Kết<br><span class="gradient-text" style="background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Thuốc – Bệnh</span></h1>
    <p style="font-size: 1.2rem; color: var(--text-secondary); max-width: 800px; margin: 0 auto 2.5rem; line-height: 1.6;">Hệ thống AI sử dụng Graph Neural Network kết hợp đặc trưng cấu trúc đồ thị (Persistent Homology) để dự đoán mối liên hệ giữa thuốc và bệnh một cách chính xác.</p>
    <div class="hero-actions" style="display: flex; gap: 15px; justify-content: center;">
        <a href="predict.php" class="btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 1.1rem; text-decoration: none; box-shadow: 0 4px 15px rgba(99,102,241,0.4); transition: transform 0.2s;"><i class="fas fa-search"></i> Bắt đầu dự đoán</a>
        <a href="#features" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 1.1rem; text-decoration: none; transition: transform 0.2s;"><i class="fas fa-info-circle"></i> Tìm hiểu thêm</a>
    </div>
</section>

<?php
// Stats - tổng hợp từ tất cả dataset
$drugCount = safeQuery("SELECT COUNT(*) FROM drugs", [], 0);
$diseaseCount = safeQuery("SELECT COUNT(*) FROM diseases", [], 0);
$assocCount = safeQuery("SELECT COUNT(*) FROM known_associations", [], 0);
$predCount = safeQuery("SELECT COUNT(*) FROM predictions", [], 0);

// Khởi tạo mảng thống kê
$datasetStats = [];

// Đọc thư mục data để lấy danh sách dataset và đọc fallback CSV trước
$dataDir = __DIR__ . '/../data';
if (is_dir($dataDir)) {
    $scan = scandir($dataDir);
    foreach($scan as $d) {
        if ($d !== '.' && $d !== '..' && is_dir($dataDir . '/' . $d) && str_ends_with($d, '-dataset')) {
            $datasetStats[$d] = [
                'drugs' => 0, 'diseases' => 0, 'associations' => 0,
                'proteins' => 0, 'drug_protein' => 0, 'protein_disease' => 0
            ];
            
            $dsPath = $dataDir . '/' . $d;
            
            if (file_exists($dsPath . '/DrugInformation.csv')) {
                $datasetStats[$d]['drugs'] = max(0, count(file($dsPath . '/DrugInformation.csv', FILE_SKIP_EMPTY_LINES)) - 1);
            }
            if (file_exists($dsPath . '/DiseaseFeature.csv')) {
                $datasetStats[$d]['diseases'] = count(file($dsPath . '/DiseaseFeature.csv', FILE_SKIP_EMPTY_LINES));
            }
            if (file_exists($dsPath . '/ProteinInformation.csv')) {
                $datasetStats[$d]['proteins'] = max(0, count(file($dsPath . '/ProteinInformation.csv', FILE_SKIP_EMPTY_LINES)) - 1);
            }
            if (file_exists($dsPath . '/DrugDiseaseAssociationNumber.csv')) {
                $datasetStats[$d]['associations'] = max(0, count(file($dsPath . '/DrugDiseaseAssociationNumber.csv', FILE_SKIP_EMPTY_LINES)) - 1);
            }
            if (file_exists($dsPath . '/DrugProteinAssociationNumber.csv')) {
                $datasetStats[$d]['drug_protein'] = max(0, count(file($dsPath . '/DrugProteinAssociationNumber.csv', FILE_SKIP_EMPTY_LINES)) - 1);
            }
            if (file_exists($dsPath . '/ProteinDiseaseAssociationNumber.csv')) {
                $datasetStats[$d]['protein_disease'] = max(0, count(file($dsPath . '/ProteinDiseaseAssociationNumber.csv', FILE_SKIP_EMPTY_LINES)) - 1);
            }
        }
    }
}

// Cập nhật bằng dữ liệu thực tế từ Database (DB luôn đúng nhất vì có chứa dữ liệu thêm tay)
$dbStats = [
    'drugs' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM drugs GROUP BY dataset", [], []),
    'diseases' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM diseases GROUP BY dataset", [], []),
    'proteins' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM proteins GROUP BY dataset", [], []),
    'associations' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM known_associations GROUP BY dataset", [], []),
    'drug_protein' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM drug_protein_associations GROUP BY dataset", [], []),
    'protein_disease' => safeQueryAll("SELECT dataset, COUNT(*) as cnt FROM protein_disease_associations GROUP BY dataset", [], [])
];

foreach ($dbStats as $key => $rows) {
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $ds = $row['dataset'];
            $cnt = (int)$row['cnt'];
            if ($cnt > 0) {
                if (!isset($datasetStats[$ds])) {
                    $datasetStats[$ds] = ['drugs'=>0, 'diseases'=>0, 'proteins'=>0, 'associations'=>0, 'drug_protein'=>0, 'protein_disease'=>0];
                }
                
                // drug_protein_associations và protein_disease_associations chưa được import từ CSV vào DB trong setup_db.php, 
                // nên DB hiện tại chỉ chứa các liên kết do người dùng thêm thủ công. 
                // Do đó, ta phải CỘNG DỒN (+=) với số lượng đã đếm từ file CSV.
                if ($key === 'drug_protein' || $key === 'protein_disease') {
                    $datasetStats[$ds][$key] += $cnt;
                } else {
                    // Các bảng khác (drugs, diseases, proteins, known_associations) đã được import đầy đủ vào DB.
                    // Do đó số lượng trong DB là con số Tổng chính xác nhất.
                    $datasetStats[$ds][$key] = $cnt;
                }
            }
        }
    }
}

// Sắp xếp lại danh sách dataset theo tên (B, C, F...)
ksort($datasetStats);
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

<div class="stats-grid fade-in" id="features" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(99,102,241,0.2);">
        <div class="stat-value" style="font-size: 2.5rem; font-weight: 900; color: #818cf8;"><?= number_format($totalDrugs) ?></div>
        <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-pills"></i> Thuốc trong hệ thống</div>
    </div>
    <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(16,185,129,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(16,185,129,0.2);">
        <div class="stat-value" style="font-size: 2.5rem; font-weight: 900; color: #34d399;"><?= number_format($totalDiseases) ?></div>
        <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-virus"></i> Bệnh trong hệ thống</div>
    </div>
    <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(236,72,153,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(236,72,153,0.2);">
        <div class="stat-value" style="font-size: 2.5rem; font-weight: 900; color: #f472b6;"><?= number_format($totalProteins) ?></div>
        <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-dna"></i> Protein trong hệ thống</div>
    </div>
    <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(245,158,11,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(245,158,11,0.2);">
        <div class="stat-value" style="font-size: 2.5rem; font-weight: 900; color: #fbbf24;"><?= number_format($totalAssocs) ?></div>
        <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-project-diagram"></i> Tổng liên kết đã biết</div>
    </div>
    <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(56,189,248,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(56,189,248,0.2);">
        <div class="stat-value" style="font-size: 2.5rem; font-weight: 900; color: #38bdf8;"><?= number_format($predCount) ?></div>
        <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-search"></i> Tổng Lượt dự đoán</div>
    </div>
</div>

<div class="fade-in" style="margin: 3rem 0; padding: 2rem; background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 24px; box-shadow: var(--shadow-lg);">
    <h3 style="margin-bottom: 1.5rem; color: var(--text-primary); font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 0.8rem;">
        <i class="fas fa-database" style="color: #6366f1;"></i> Chi tiết theo Dataset
    </h3>
    <div style="overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; background: rgba(0,0,0,0.1);">
            <thead>
                <tr style="border-bottom: 2px solid rgba(255,255,255,0.1); color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">
                    <th style="padding: 1.2rem; white-space: nowrap; font-weight: 700;">Tên Dataset</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Số Thuốc</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Số Bệnh</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Số Protein</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Thuốc-Bệnh liên kết</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Thuốc-Protein liên kết</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Protein-Bệnh liên kết</th>
                    <th style="padding: 1.2rem; font-weight: 700;">Sparsity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datasetStats as $dsName => $stats): ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(99,102,241,0.05)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 1.2rem; font-weight: 700; white-space: nowrap;">
                        <a href="compare.php?dataset=<?= urlencode($dsName) ?>" style="display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)); border: 1px solid rgba(99,102,241,0.3); color: #818cf8; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; text-decoration: none; transition: all 0.2s; cursor: pointer; box-shadow: 0 4px 12px rgba(99,102,241,0.1);" onmouseover="this.style.transform='translateY(-2px) scale(1.05)'; this.style.boxShadow='0 6px 18px rgba(99,102,241,0.25)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 12px rgba(99,102,241,0.1)';">
                            <i class="fas fa-layer-group" style="margin-right: 6px;"></i> <?= htmlspecialchars($dsName) ?>
                        </a>
                    </td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['drugs'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['diseases'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['proteins'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['associations'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['drug_protein'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;"><?= number_format($stats['protein_disease'] ?? 0) ?></td>
                    <td style="padding: 1.2rem; color: var(--text-primary); font-weight: 600;">
                        <?php 
                        $sparsities = [
                            'B-dataset' => '0.1144',
                            'C-dataset' => '0.0093',
                            'F-dataset' => '0.0104'
                        ];
                        echo htmlspecialchars($sparsities[$dsName] ?? '0.0000');
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($datasetStats)): ?>
                <tr>
                    <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-muted); font-weight: 600;">Chưa có dữ liệu</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 4rem;">
    <div class="card fade-in" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(139,92,246,0.3); border-radius: 20px; padding: 2rem; transition: transform 0.3s; box-shadow: 0 10px 30px -10px rgba(139,92,246,0.2);">
        <div class="card-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 1.2rem;">
            <div class="card-icon" style="width: 50px; height: 50px; border-radius: 16px; background: rgba(139,92,246,0.2); color: #a78bfa; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-pills"></i></div>
            <div class="card-title" style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary);">Thuốc → Bệnh</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 1rem; line-height: 1.6;">Nhập tên thuốc để tìm các bệnh tiềm năng mà thuốc có thể điều trị. Mô hình AI sẽ phân tích cấu trúc đồ thị và trả về top kết quả.</p>
    </div>
    <div class="card fade-in" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(56,189,248,0.3); border-radius: 20px; padding: 2rem; transition: transform 0.3s; box-shadow: 0 10px 30px -10px rgba(56,189,248,0.2);">
        <div class="card-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 1.2rem;">
            <div class="card-icon" style="width: 50px; height: 50px; border-radius: 16px; background: rgba(56,189,248,0.2); color: #7dd3fc; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-virus"></i></div>
            <div class="card-title" style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary);">Bệnh → Thuốc</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 1rem; line-height: 1.6;">Nhập tên bệnh để tìm các thuốc tiềm năng có thể điều trị. Hệ thống sử dụng Dual Graph Transformer để dự đoán chính xác.</p>
    </div>
    <div class="card fade-in" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(16,185,129,0.3); border-radius: 20px; padding: 2rem; transition: transform 0.3s; box-shadow: 0 10px 30px -10px rgba(16,185,129,0.2);">
        <div class="card-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 1.2rem;">
            <div class="card-icon" style="width: 50px; height: 50px; border-radius: 16px; background: rgba(16,185,129,0.2); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-project-diagram"></i></div>
            <div class="card-title" style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary);">Persistent Homology</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 1rem; line-height: 1.6;">Trích xuất đặc trưng topo (H0: connected components, H1: loops) từ đồ thị Drug-Disease để tăng cường khả năng dự đoán.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
