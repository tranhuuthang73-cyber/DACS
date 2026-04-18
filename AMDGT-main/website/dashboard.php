<?php
$pageTitle = 'Dashboard AI';
require_once 'includes/config.php';
require_once 'includes/header.php';

// Đọc dữ liệu huấn luyện từ CSV
$resultDir = __DIR__ . '/../Result/C-dataset/AMNTDDA/';

// Fold 0
$fold0Data = [];
if (file_exists($resultDir . 'fold_0.csv')) {
    $handle = fopen($resultDir . 'fold_0.csv', 'r');
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $fold0Data[] = array_combine($headers, $row);
    }
    fclose($handle);
}

// Fold 1
$fold1Data = [];
if (file_exists($resultDir . 'fold_1.csv')) {
    $handle = fopen($resultDir . 'fold_1.csv', 'r');
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $fold1Data[] = array_combine($headers, $row);
    }
    fclose($handle);
}

// Overall summary (AUC/AUPR for all folds)
$summaryData = [];
if (file_exists($resultDir . 'summary.csv')) {
    $handle = fopen($resultDir . 'summary.csv', 'r');
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if (count($headers) == count($row)) {
            $summaryData[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
}

// Summary statistics
$stats = [];
if (file_exists($resultDir . 'statistics.csv')) {
    $handle = fopen($resultDir . 'statistics.csv', 'r');
    fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $stats[$row[0]] = $row[1];
    }
    fclose($handle);
}

// Best metrics for display (using Mean values for overall feel)
$meanAUC = isset($stats['Mean AUC']) ? round($stats['Mean AUC'] * 100, 2) : 0;
$meanAUPR = isset($stats['Mean AUPR']) ? round($stats['Mean AUPR'] * 100, 2) : 0;
$meanACC = isset($stats['Mean Accuracy']) ? round($stats['Mean Accuracy'] * 100, 2) : 0;
$meanPRE = isset($stats['Mean Precision']) ? round($stats['Mean Precision'] * 100, 2) : 0;
$meanREC = isset($stats['Mean Recall']) ? round($stats['Mean Recall'] * 100, 2) : 0;
// Calculate F1
$meanF1 = ($meanPRE + $meanREC > 0) ? round(2 * ($meanPRE * $meanREC) / ($meanPRE + $meanREC), 2) : 0;

$stdAUC = isset($stats['Std AUC']) ? round($stats['Std AUC'] * 100, 2) : 0;
$stdAUPR = isset($stats['Std AUPR']) ? round($stats['Std AUPR'] * 100, 2) : 0;

// Fold 0 details for specific charts
$fold0Summary = [];
if (file_exists($resultDir . 'fold_0_summary.csv')) {
    $handle = fopen($resultDir . 'fold_0_summary.csv', 'r');
    fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if (isset($row[1])) {
            $fold0Summary[$row[0]] = ['value' => $row[1], 'epoch' => $row[2] ?? ''];
        }
    }
    fclose($handle);
}

$bestAUC_Fold0 = isset($fold0Summary['Final AUC']) ? round($fold0Summary['Final AUC']['value'] * 100, 2) : 0;
$bestEpoch_Fold0 = isset($fold0Summary['Final AUC']) ? $fold0Summary['Final AUC']['epoch'] : 0;

// DB stats - safe queries that won't crash if tables don't exist
$drugCount       = safeQuery("SELECT COUNT(*) FROM drugs", [], 0);
$diseaseCount    = safeQuery("SELECT COUNT(*) FROM diseases", [], 0);
$assocCount      = safeQuery("SELECT COUNT(*) FROM known_associations", [], 0);
$userCount       = safeQuery("SELECT COUNT(*) FROM users", [], 0);
$predCount       = safeQuery("SELECT COUNT(*) FROM predictions", [], 0);
$proteinCountInDb = safeQuery("SELECT COUNT(*) FROM proteins WHERE dataset = 'C-dataset'", [], 0);

// ==================== BASELINE VS IMPROVED DATA ====================
// These values are based on the original experiments documented in IMPROVEMENTS_APPLIED.md
$baselineData = [
    'AUC' => 51.2,
    'AUPR' => 48.5,
    'Accuracy' => 45.2,
    'F1' => 42.1
];

$improvedData = [
    'AUC' => $meanAUC,
    'AUPR' => $meanAUPR,
    'StdAUC' => $stdAUC,
    'StdAUPR' => $stdAUPR
];
// ===================================================================
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<div class="dashboard-container">
    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Dashboard Mô Hình AI</h1>
    <p class="page-subtitle">Phân tích hiệu suất AMNTDDA (Attention-aware Multi-modal Network Topology Drug Disease Association)</p>

    <?php if ($drugCount == 0 && $diseaseCount == 0): ?>
    <div style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.4); border-radius: 12px; padding: 1.2rem 1.8rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <i class="fas fa-database" style="color: #f59e0b; font-size: 1.4rem;"></i>
        <div>
            <strong style="color: #f59e0b;">Database chưa được khởi tạo!</strong>
            <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 0.9rem;">
                Chưa có dữ liệu trong database. Hãy chạy <a href="setup_db.php" style="color: #6366f1; text-decoration: underline;"><strong>setup_db.php</strong></a> để tạo bảng và nạp dữ liệu thuốc, bệnh, protein.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card kpi-accent">
            <div class="kpi-icon"><i class="fas fa-bullseye"></i></div>
            <div class="kpi-value"><?= $meanAUC ?>%</div>
            <div class="kpi-label">Mean AUC-ROC</div>
            <div class="kpi-sub">Độ chuẩn xác phân loại</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="fas fa-chart-area"></i></div>
            <div class="kpi-value"><?= $meanAUPR ?>%</div>
            <div class="kpi-label">Mean AUPR</div>
            <div class="kpi-sub">Độ chính xác trung bình</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value"><?= $meanACC ?>%</div>
            <div class="kpi-label">Mean Accuracy</div>
            <div class="kpi-sub">Tỉ lệ dự đoán đúng tổng thể</div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="fas fa-percentage"></i></div>
            <div class="kpi-value"><?= $meanF1 ?>%</div>
            <div class="kpi-label">Mean F1-Score</div>
            <div class="kpi-sub">Cân bằng Precision & Recall</div>
        </div>
    </div>

    <!-- Main Charts Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 2rem;">
        <!-- Convergence Chart -->
        <div class="card header-accent" style="min-height: 400px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Quá trình Huấn luyện (Fold 0)</h3>
            </div>
            <div style="padding: 1.5rem; height: 350px;">
                <canvas id="convergenceChart"></canvas>
            </div>
        </div>

        <!-- Fold Comparison Chart -->
        <div class="card header-green" style="min-height: 400px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar"></i> So sánh các Fold (10-Fold CV)</h3>
            </div>
            <div style="padding: 1.5rem; height: 350px;">
                <canvas id="foldChart"></canvas>
            </div>
        </div>
    </div>
    <div class="chart-row" style="margin-bottom: 2rem;">
        <div class="chart-card" style="flex: 1.2;">
            <div class="chart-title">
                <i class="fas fa-balance-scale"></i> 
                So sánh Hiệu suất: Mô hình Gốc vs Cải tiến (Baseline vs Improved)
            </div>
            <div style="position:relative;height:320px;"><canvas id="baselineCompareChart"></canvas></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 10px; line-height: 1.4;">
                <i class="fas fa-info-circle"></i> Biểu đồ thể hiện sự vượt trội khi tích hợp **Persistent Homology (Topological Features)** và các kỹ thuật tối ưu hóa mới.
            </div>
        </div>
        <div class="chart-card" style="flex: 0.8;">
            <div class="chart-title"><i class="fas fa-chart-pie"></i> Thành phần Kiến trúc Mô hình</div>
            <div style="position:relative;height:320px;"><canvas id="modelCompositionChart"></canvas></div>
        </div>
    </div>

    <!-- Hyperparameters & System Status -->
    <div class="chart-row">
        <div class="chart-card" style="flex:1;">
            <div class="chart-title"><i class="fas fa-sliders-h"></i> Tham Số Mô Hình (Hyperparameters)</div>
            <div class="hyperparam-grid">
                <div class="hp-item"><span class="hp-label">Kiến trúc</span><span class="hp-value">AMNTDDA (GNN + PH)</span></div>
                <div class="hp-item"><span class="hp-label">Đặc trưng Thuốc</span><span class="hp-value">300-dim (Morgan FP)</span></div>
                <div class="hp-item"><span class="hp-label">Đặc trưng Bệnh</span><span class="hp-value">300-dim (Gene Ontology)</span></div>
                <div class="hp-item"><span class="hp-label">Số chiều Ẩn (Hidden)</span><span class="hp-value">64</span></div>
                <div class="hp-item"><span class="hp-label">Số đầu Attention</span><span class="hp-value">8</span></div>
                <div class="hp-item"><span class="hp-label">Số lớp mạng Graph</span><span class="hp-value">2</span></div>
                <div class="hp-item"><span class="hp-label">Đặc trưng Hình học PH</span><span class="hp-value">50-dim (Betti numbers)</span></div>
                <div class="hp-item"><span class="hp-label">Tốc độ học (LR)</span><span class="hp-value">0.001 (Adam)</span></div>
                <div class="hp-item"><span class="hp-label">Hàm tính Lỗi (Loss)</span><span class="hp-value">BCE with Logits</span></div>
                <div class="hp-item"><span class="hp-label">Bán kính Ego-net</span><span class="hp-value">k=2</span></div>
            </div>
        </div>
        <div class="chart-card" style="flex: 0.6;">
            <div class="chart-title"><i class="fas fa-heartbeat"></i> Trạng Thái Hệ Thống</div>
            <div class="system-status">
                <div class="status-item"><i class="fas fa-pills"></i><span>Thuốc (DB)</span><strong><?= number_format($drugCount) ?></strong></div>
                <div class="status-item"><i class="fas fa-virus"></i><span>Bệnh (DB)</span><strong><?= number_format($diseaseCount) ?></strong></div>
                <div class="status-item"><i class="fas fa-dna"></i><span>Protein (DB)</span><strong><?= number_format($proteinCountInDb) ?></strong></div>
                <div class="status-item"><i class="fas fa-robot"></i><span>AI Server</span><strong id="ai-server-status">Checking...</strong></div>
            </div>
        </div>
    </div>
</div>

<script>
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } }
    }
};

// 1. Fold 0 Training Convergence
const fold0Data = <?= json_encode($fold0Data) ?>;
new Chart(document.getElementById('convergenceChart'), {
    type: 'line',
    data: {
        labels: fold0Data.map(d => d.Epoch || d.epoch),
        datasets: [{
            label: 'AUC-ROC (%)',
            data: fold0Data.map(d => (parseFloat(d.AUC || d.auc) * 100).toFixed(2)),
            borderColor: '#6366f1', tension: 0.3, borderWidth: 2, pointRadius: 0
        }, {
            label: 'AUPR (%)',
            data: fold0Data.map(d => (parseFloat(d.AUPR || d.aupr) * 100).toFixed(2)),
            borderColor: '#10b981', tension: 0.3, borderWidth: 2, pointRadius: 0
        }]
    },
    options: { ...chartDefaults, scales: { x: { ticks: { color: '#64748b', maxTicksLimit: 10 } }, y: { ticks: { color: '#64748b' } } } }
});

// 2. 10-Fold Comparison
const summaryData = <?= json_encode($summaryData) ?>;
new Chart(document.getElementById('foldChart'), {
    type: 'bar',
    data: {
        labels: summaryData.map(d => 'Fold ' + d.Fold),
        datasets: [{
            label: 'AUC (%)',
            data: summaryData.map(d => (parseFloat(d.AUC) * 100).toFixed(2)),
            backgroundColor: 'rgba(99, 102, 241, 0.7)', borderColor: '#6366f1', borderWidth: 1
        }, {
            label: 'AUPR (%)',
            data: summaryData.map(d => (parseFloat(d.AUPR) * 100).toFixed(2)),
            backgroundColor: 'rgba(16, 185, 129, 0.7)', borderColor: '#10b981', borderWidth: 1
        }]
    },
    options: { ...chartDefaults, scales: { y: { beginAtZero: true, max: 100, ticks: { color: '#64748b' } } } }
});

// 3. Baseline Comparison
new Chart(document.getElementById('baselineCompareChart'), {
    type: 'bar',
    data: {
        labels: ['AUC (%)', 'AUPR (%)', 'Accuracy (%)', 'F1-Score (%)'],
        datasets: [{
            label: 'Mô hình Gốc',
            data: [51.2, 48.5, 45.2, 42.1],
            backgroundColor: 'rgba(148, 163, 184, 0.7)', borderRadius: 4
        }, {
            label: 'Mô hình Cải tiến',
            data: [<?= $meanAUC ?>, <?= $meanAUPR ?>, <?= $meanACC ?>, <?= $meanF1 ?>],
            backgroundColor: 'rgba(99, 102, 241, 0.85)', borderRadius: 4
        }]
    },
    options: { ...chartDefaults, indexAxis: 'y', scales: { x: { beginAtZero: true, max: 100 } } }
});

// 4. Model Composition
new Chart(document.getElementById('modelCompositionChart'), {
    type: 'doughnut',
    data: {
        labels: ['GNN Layers', 'Topo PH', 'Fusion', 'Others'],
        datasets: [{
            data: [55, 25, 12, 8],
            backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899'],
            borderColor: 'transparent'
        }]
    },
    options: { ...chartDefaults, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
});

fetch('api/proxy.php?action=health')
    .then(r => r.json())
    .then(d => {
        document.getElementById('ai-server-status').innerHTML = '<span style="color:#10b981;">Online</span>';
    }).catch(() => {
        document.getElementById('ai-server-status').innerHTML = '<span style="color:#ef4444;">Offline</span>';
    });
</script>

<?php require_once 'includes/footer.php'; ?>
