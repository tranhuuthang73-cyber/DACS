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

// Fold 0 summary
$fold0Summary = [];
if (file_exists($resultDir . 'fold_0_summary.csv')) {
    $handle = fopen($resultDir . 'fold_0_summary.csv', 'r');
    fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $fold0Summary[$row[0]] = ['value' => $row[1], 'epoch' => $row[2] ?? ''];
    }
    fclose($handle);
}

// Best metrics for display
$bestAUC = isset($fold0Summary['Final AUC']) ? round($fold0Summary['Final AUC']['value'] * 100, 2) : 0;
$bestAUPR = isset($fold0Summary['Final AUPR']) ? round($fold0Summary['Final AUPR']['value'] * 100, 2) : 0;
$bestF1 = isset($fold0Summary['Final F1']) ? round($fold0Summary['Final F1']['value'] * 100, 2) : 0;
$bestEpoch = isset($fold0Summary['Final AUC']) ? $fold0Summary['Final AUC']['epoch'] : 0;

// DB stats
$db = getDB();
$drugCount = $db->query("SELECT COUNT(*) FROM drugs")->fetchColumn();
$diseaseCount = $db->query("SELECT COUNT(*) FROM diseases")->fetchColumn();
$assocCount = $db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn();
$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$predCount = $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<div class="dashboard-container">
    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Dashboard Mô Hình AI</h1>
    <p class="page-subtitle">Phân tích hiệu suất AMNTDDA (Attention-aware Multi-modal Network Topology Drug Disease Association)</p>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card kpi-accent">
            <div class="kpi-icon"><i class="fas fa-bullseye"></i></div>
            <div class="kpi-value"><?= $bestAUC ?>%</div>
            <div class="kpi-label">Điểm AUC-ROC cao nhất</div>
            <div class="kpi-sub">Fold 0, Vòng lặp (Epoch) <?= $bestEpoch ?></div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="fas fa-chart-area"></i></div>
            <div class="kpi-value"><?= $bestAUPR ?>%</div>
            <div class="kpi-label">Điểm AUPR cao nhất</div>
            <div class="kpi-sub">Diện tích dưới đường cong PR</div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="fas fa-trophy"></i></div>
            <div class="kpi-value"><?= $bestF1 ?>%</div>
            <div class="kpi-label">Chỉ số F1-Score</div>
            <div class="kpi-sub">Trung bình hài hòa (Harmonic Mean)</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="fas fa-layer-group"></i></div>
            <div class="kpi-value">2</div>
            <div class="kpi-label">Số tập kiểm thử (CV Folds)</div>
            <div class="kpi-sub">Chiến lược chia K-Fold</div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="chart-row">
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-chart-line"></i> AUC-ROC qua các Epoch (Training Progress)</div>
            <div style="position:relative;height:280px;"><canvas id="aucChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-chart-area"></i> AUPR qua các Epoch</div>
            <div style="position:relative;height:280px;"><canvas id="auprChart"></canvas></div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="chart-row">
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-balance-scale"></i> So sánh Cross-Validation (Fold 0 vs Fold 1)</div>
            <div style="position:relative;height:280px;"><canvas id="foldCompareChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-database"></i> Thống kê Dữ liệu Hệ thống</div>
            <div style="position:relative;height:280px;"><canvas id="dataStatsChart"></canvas></div>
        </div>
    </div>

    <!-- Hyperparameters -->
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
                <div class="hp-item"><span class="hp-label">Đặc trưng Hình học</span><span class="hp-value">50</span></div>
                <div class="hp-item"><span class="hp-label">Tốc độ học (Learning Rate)</span><span class="hp-value">0.001</span></div>
                <div class="hp-item"><span class="hp-label">Thuật toán tối ưu</span><span class="hp-value">Adam</span></div>
                <div class="hp-item"><span class="hp-label">Hàm tính Lỗi (Loss)</span><span class="hp-value">BCE</span></div>
                <div class="hp-item"><span class="hp-label">Tỷ lệ Dropout</span><span class="hp-value">0.3</span></div>
                <div class="hp-item"><span class="hp-label">Bán kính Ego-net</span><span class="hp-value">k=2</span></div>
            </div>
        </div>
        <div class="chart-card" style="flex: 0.6;">
            <div class="chart-title"><i class="fas fa-heartbeat"></i> Trạng Thái Hệ Thống</div>
            <div class="system-status">
                <div class="status-item">
                    <i class="fas fa-pills"></i>
                    <span>Thuốc trong DB</span>
                    <strong><?= number_format($drugCount) ?></strong>
                </div>
                <div class="status-item">
                    <i class="fas fa-virus"></i>
                    <span>Bệnh trong DB</span>
                    <strong><?= number_format($diseaseCount) ?></strong>
                </div>
                <div class="status-item">
                    <i class="fas fa-link"></i>
                    <span>Liên kết đã biết</span>
                    <strong><?= number_format($assocCount) ?></strong>
                </div>
                <div class="status-item">
                    <i class="fas fa-users"></i>
                    <span>Người dùng</span>
                    <strong><?= number_format($userCount) ?></strong>
                </div>
                <div class="status-item">
                    <i class="fas fa-search"></i>
                    <span>Lượt dự đoán</span>
                    <strong><?= number_format($predCount) ?></strong>
                </div>
                <div class="status-item status-ai">
                    <i class="fas fa-robot"></i>
                    <span>AI Server</span>
                    <strong id="ai-server-status">Đang kiểm tra...</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============ TRAINING DATA ============
const fold0 = <?= json_encode($fold0Data) ?>;
const fold1 = <?= json_encode($fold1Data) ?>;

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Inter' } } } },
    scales: {
        x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } },
        y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } }
    }
};

// AUC Chart
new Chart(document.getElementById('aucChart'), {
    type: 'line',
    data: {
        labels: fold0.map(d => d.Epoch),
        datasets: [{
            label: 'Fold 0 AUC',
            data: fold0.map(d => (parseFloat(d.AUC) * 100).toFixed(2)),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.1)',
            fill: true, borderWidth: 2, tension: 0.4, pointRadius: 1
        }, {
            label: 'Fold 1 AUC',
            data: fold1.map(d => (parseFloat(d.AUC) * 100).toFixed(2)),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            fill: true, borderWidth: 2, tension: 0.4, pointRadius: 1
        }]
    },
    options: { ...chartDefaults, scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, title: { display: true, text: 'AUC (%)', color: '#94a3b8' } } } }
});

// AUPR Chart
new Chart(document.getElementById('auprChart'), {
    type: 'line',
    data: {
        labels: fold0.map(d => d.Epoch),
        datasets: [{
            label: 'Fold 0 AUPR',
            data: fold0.map(d => (parseFloat(d.AUPR) * 100).toFixed(2)),
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245,158,11,0.1)',
            fill: true, borderWidth: 2, tension: 0.4, pointRadius: 1
        }, {
            label: 'Fold 1 AUPR',
            data: fold1.map(d => (parseFloat(d.AUPR) * 100).toFixed(2)),
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.1)',
            fill: true, borderWidth: 2, tension: 0.4, pointRadius: 1
        }]
    },
    options: { ...chartDefaults, scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, title: { display: true, text: 'AUPR (%)', color: '#94a3b8' } } } }
});

// Fold Comparison Bar Chart
const fold0Best = {
    AUC: <?= $fold0Summary['Final AUC']['value'] ?? 0 ?>,
    AUPR: <?= $fold0Summary['Final AUPR']['value'] ?? 0 ?>,
    F1: <?= $fold0Summary['Final F1']['value'] ?? 0 ?>
};
const fold1Best = fold1.length > 0 ? {
    AUC: Math.max(...fold1.map(d => parseFloat(d.AUC))),
    AUPR: Math.max(...fold1.map(d => parseFloat(d.AUPR))),
    F1: Math.max(...fold1.map(d => parseFloat(d.F1)))
} : { AUC: 0, AUPR: 0, F1: 0 };

new Chart(document.getElementById('foldCompareChart'), {
    type: 'bar',
    data: {
        labels: ['AUC-ROC', 'AUPR', 'F1-Score'],
        datasets: [{
            label: 'Fold 0 (Tốt nhất)',
            data: [fold0Best.AUC * 100, fold0Best.AUPR * 100, fold0Best.F1 * 100],
            backgroundColor: ['rgba(99,102,241,0.7)', 'rgba(99,102,241,0.7)', 'rgba(99,102,241,0.7)'],
            borderColor: '#6366f1', borderWidth: 2, borderRadius: 6
        }, {
            label: 'Fold 1 (Tốt nhất)',
            data: [fold1Best.AUC * 100, fold1Best.AUPR * 100, fold1Best.F1 * 100],
            backgroundColor: ['rgba(16,185,129,0.7)', 'rgba(16,185,129,0.7)', 'rgba(16,185,129,0.7)'],
            borderColor: '#10b981', borderWidth: 2, borderRadius: 6
        }]
    },
    options: chartDefaults
});

// Data Stats Doughnut
new Chart(document.getElementById('dataStatsChart'), {
    type: 'doughnut',
    data: {
        labels: ['Thuốc (Drugs)', 'Bệnh (Diseases)', 'Liên kết (Associations)'],
        datasets: [{
            data: [<?= $drugCount ?>, <?= $diseaseCount ?>, <?= $assocCount ?>],
            backgroundColor: ['#6366f1', '#10b981', '#f59e0b'],
            borderColor: 'transparent', borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Inter' }, padding: 15 } }
        }
    }
});

// Check AI Server Status
fetch('api/proxy.php?action=health')
    .then(r => r.json())
    .then(d => {
        document.getElementById('ai-server-status').innerHTML = '<span style="color:#10b981;">✅ Online (Port 5001)</span>';
    })
    .catch(() => {
        document.getElementById('ai-server-status').innerHTML = '<span style="color:#ef4444;">❌ Offline</span>';
    });
</script>

<?php require_once 'includes/footer.php'; ?>
