<?php
// Tắt bộ nhớ đệm (cache) hoàn toàn để force load code mới nhất
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'includes/config.php';
$pageTitle = 'Dự đoán';
include 'includes/header.php';

if (!isLoggedIn()) {
    // Tạm thời comment đoạn check login để bạn có thể xem luôn giao diện 3D
    // echo '<div class="auth-container"><div class="alert alert-info"><i class="fas fa-info-circle"></i> Pls <a href="login.php" style="color: var(--accent-light)">dang nhap</a> de su dung chuc nang du doan.</div></div>';
    // include 'includes/footer.php';
    // exit;
}
?>

<link rel="stylesheet" href="assets/css/amdgt-predict.css?v=<?= time() ?>">

<script src="assets/js/chart.min.js"></script>
<script src="assets/js/html2canvas.min.js"></script>
<script src="assets/js/html2pdf.bundle.min.js"></script>
<script src="assets/js/3d-force-graph.min.js"></script>
<script src="assets/js/3Dmol-min.js"></script>
<script src="assets/js/d3.v7.min.js"></script>
<!-- SmilesDrawer for 2D Molecule Rendering -->
<script src="assets/js/smiles-drawer.min.js"></script>

<div class="predict-hero">
    <div
        style="display: inline-block; padding: 4px 12px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 30px; color: #818cf8; font-size: 0.65rem; font-weight: 800; letter-spacing: 1px; margin-bottom: 0.8rem; text-transform: uppercase;">
        <i class="fas fa-sparkles"></i> AI-Powered Discovery
    </div>
    <h1>Trình Phân Tích Đa Tầng</h1>
    <p>Khám phá liên kết Thuốc - Bệnh thông qua mạng lưới Protein trung gian và Topology đa chiều.</p>
</div>

<input type="hidden" id="global-dataset" value="C-dataset">

<!-- DATASET SELECTOR VIP -->
<div class="dataset-selector-container"
    style="display: flex; justify-content: center; gap: 8px; margin-bottom: 1rem; padding: 0 1rem;">
    <div
        style="display: flex; background: var(--bg-secondary); padding: 4px; border-radius: 12px; border: 1px solid var(--border); backdrop-filter: blur(10px);">
        <button type="button" class="ds-tab active" onclick="setGlobalDataset('C-dataset', this)"
            style="padding: 8px 16px; border-radius: 10px; border: none; background: rgba(99, 102, 241, 0.2); color: #818cf8; font-weight: 800; font-size: 0.8rem; cursor: pointer; transition: all 0.3s;">C-DATASET</button>
        <button type="button" class="ds-tab" onclick="setGlobalDataset('B-dataset', this)"
            style="padding: 8px 16px; border-radius: 10px; border: none; background: transparent; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: all 0.3s;">B-DATASET</button>
        <button type="button" class="ds-tab" onclick="setGlobalDataset('F-dataset', this)"
            style="padding: 8px 16px; border-radius: 10px; border: none; background: transparent; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: all 0.3s;">F-DATASET</button>
    </div>
</div>


<!-- SEARCH CARDS -->
<div class="search-cards-container">
    <!-- DRUG CARD -->
    <div class="search-card drug">
        <div class="search-card-icon"><i class="fas fa-capsules"></i></div>
        <h3>Thuốc (Drugs)</h3>
        <p class="card-subtitle">Nhập tên dược chất để tìm kiếm</p>



        <div class="search-input-wrapper">
            <i class="fas fa-search input-icon"></i>
            <input type="text" id="drug-search" placeholder="Nhập tên thuốc hoặc mã ID (vd: Aspirin, DB001)..." autocomplete="off"
                style="width: 100%; padding: 10px 12px 10px 38px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary); font-size: 0.85rem;">
            <input type="hidden" id="drug-idx" value="">
            <input type="hidden" id="drug-dataset" value="C-dataset">
            <div class="autocomplete-list" id="drug-autocomplete" style="display:none;"></div>
        </div>

        <!-- Multi-select tags -->
        <div class="selected-tags-container" id="drug-tags"></div>

        <div class="topk-selector" style="display: flex; gap: 6px; margin: 0.8rem 0;">
            <button type="button" class="topk-btn" data-type="drug" data-k="10">Top 10</button>
            <button type="button" class="topk-btn active" data-type="drug" data-k="20">Top 20</button>
            <button type="button" class="topk-btn" data-type="drug" data-k="50">Top 50</button>
        </div>
        <input type="hidden" id="drug-topk" value="20">
        <input type="hidden" id="btn-drug" value="">
        <div class="batch-progress" id="drug-progress" style="display:none;">
            <div class="batch-progress-bar" style="width:0%"></div>
        </div>
    </div>

    <!-- PROTEIN CARD -->
    <div class="search-card protein" style="display: none;">
        <div class="search-card-icon"><i class="fas fa-dna"></i></div>
        <h3>Protein</h3>
        <p class="card-subtitle">Nhập ID protein để phân tích</p>



        <div class="search-input-wrapper" style="position: relative;">
            <i class="fas fa-search input-icon"></i>
            <input type="text" id="protein-search" placeholder="Vd: P01137, P05067..." autocomplete="off"
                style="width: 100%; padding: 10px 12px 10px 38px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary); font-size: 0.85rem;">
            <input type="hidden" id="protein-idx" value="">
            <input type="hidden" id="protein-dataset" value="C-dataset">
            <div class="autocomplete-list" id="protein-autocomplete" style="display:none;">
            </div>
        </div>

        <!-- Multi-select tags -->
        <div class="selected-tags-container" id="protein-tags"></div>

        <div class="topk-selector" style="display: flex; gap: 6px; margin: 0.8rem 0;">
            <button type="button" class="topk-btn" data-type="protein" data-k="10">Top 10</button>
            <button type="button" class="topk-btn active" data-type="protein" data-k="20"
                style="background: rgba(236, 72, 153, 0.2); border-color: rgba(236, 72, 153, 0.4); color: #f472b6;">Top
                20</button>
            <button type="button" class="topk-btn" data-type="protein" data-k="50">Top 50</button>
        </div>
        <input type="hidden" id="protein-topk" value="20">

        <button type="button" id="btn-protein"
            style="width: 100%; padding: 12px; background: linear-gradient(135deg, #ec4899, #f472b6); border: none; border-radius: 10px; color: white; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: opacity 0.3s;"
            onclick="predictProtein()">
            <i class="fas fa-brain"></i> PHÂN TÍCH PROTEIN
        </button>
        <div class="batch-progress" id="protein-progress" style="display:none;">
            <div class="batch-progress-bar" style="width:0%"></div>
        </div>
    </div>

    <!-- DISEASE CARD -->
    <div class="search-card disease">
        <div class="search-card-icon"><i class="fas fa-virus"></i></div>
        <h3>Bệnh (Diseases)</h3>
        <p class="card-subtitle">Nhập mã hoặc tên bệnh</p>



        <div class="search-input-wrapper" style="position: relative;">
            <i class="fas fa-search input-icon"></i>
            <input type="text" id="disease-search" placeholder="Vd: C0001129, Diabetes..." autocomplete="off"
                style="width: 100%; padding: 10px 12px 10px 38px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary); font-size: 0.85rem;">
            <input type="hidden" id="disease-idx" value="">
            <input type="hidden" id="disease-dataset" value="C-dataset">
            <div class="autocomplete-list" id="disease-autocomplete" style="display:none;">
            </div>
        </div>

        <!-- Multi-select tags -->
        <div class="selected-tags-container" id="disease-tags"></div>

        <div class="topk-selector" style="display: flex; gap: 6px; margin: 0.8rem 0;">
            <button type="button" class="topk-btn" data-type="disease" data-k="10">Top 10</button>
            <button type="button" class="topk-btn active" data-type="disease" data-k="20"
                style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4); color: #34d399;">Top
                20</button>
            <button type="button" class="topk-btn" data-type="disease" data-k="50">Top 50</button>
        </div>
        <input type="hidden" id="disease-topk" value="20">
        <input type="hidden" id="btn-disease" value="">
        <div class="batch-progress" id="disease-progress" style="display:none;">
            <div class="batch-progress-bar" style="width:0%"></div>
        </div>
    </div>
</div>

<!-- UNIFIED ANALYZE BUTTON -->
<div class="unified-analyze-container">
    <button type="button" class="unified-analyze-btn" id="btn-combined" onclick="predictCombined()">
        <div class="unified-btn-inner">
            <div class="unified-btn-icon">
                <i class="fas fa-brain"></i>
            </div>
            <div class="unified-btn-text">
                <span class="unified-btn-title">PHÂN TÍCH LIÊN KẾT THUỐC — BỆNH</span>
                <span class="unified-btn-subtitle">Nhập thuốc và bệnh ở trên, sau đó nhấn để phân tích mối quan hệ</span>
            </div>
            <div class="unified-btn-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>
    </button>
    <div class="batch-progress" id="combined-progress" style="display:none;">
        <div class="batch-progress-bar" style="width:0%"></div>
    </div>
</div>


<!-- ACTION BAR -->
<div id="action-bar" class="action-bar" style="display:none;">
    <button class="action-btn secondary" onclick="exportToImage()">
        <i class="fas fa-image"></i> Lưu Ảnh Báo Cáo
    </button>
    <button class="action-btn secondary" onclick="exportToPDF()">
        <i class="fas fa-file-pdf"></i> Xuất PDF
    </button>
    <button class="action-btn primary" onclick="window.print()">
        <i class="fas fa-print"></i> In Báo Cáo
    </button>
</div>

<!-- RESULTS SECTION -->
<div id="results-section" style="display: none; padding: 2rem 1rem;">
    <h3 id="results-header" style="margin-bottom: 1.5rem; font-weight: 800; font-size: 1.4rem;"></h3>
    <div class="section-header">
        <h2><i class="fas fa-list-check" style="-webkit-text-fill-color:unset;"></i> Kết Quả Dự Đoán</h2>
        <div class="stats-badges" id="stats-badges"></div>
    </div>
    <div class="results-grid" id="results-grid"></div>
</div>

<!-- VISUALIZATION SECTION -->
<div class="viz-section">
    <div class="section-header">
        <h2><i class="fas fa-chart-scatter" style="-webkit-text-fill-color:unset;"></i> Trực Quan Hóa Mạng Lưới</h2>
    </div>

    <div class="viz-tabs-container" style="flex-wrap: wrap;">
        <button class="viz-tab-btn active" onclick="switchVizTab(this, 'landscape')">
            <i class="fas fa-map-marked-alt"></i> Bản Đồ Bệnh Lý
        </button>
        <button class="viz-tab-btn" onclick="switchVizTab(this, '3d')">
            <i class="fas fa-cube"></i> Đồ Thị 3D
        </button>
        <!-- <button class="viz-tab-btn" onclick="switchVizTab(this, 'curve')">
            <i class="fas fa-chart-line"></i> Đường Huấn Luyện
        </button> -->

    </div>

    <!-- LANDSCAPE PANEL -->
    <div id="panel-landscape" class="viz-panel active">
        <div class="viz-card">
            <div class="viz-card-header">
                <h3><i class="fas fa-map-marked-alt" style="color:#818cf8;"></i> Định Vị Không Gian Bệnh Lý (2D
                    Landscape AI)</h3>
                <div class="viz-legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background:#64748b;"></div> Không gian bệnh
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:#6366f1;"></div> Bệnh tiềm năng
                    </div>
                </div>
            </div>
            <div class="viz-card-body">
                <div class="landscape-container" id="landscape-container">
                    <canvas id="landscapeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 3D PANEL - DUAL MODEL COMPARISON -->
    <div id="panel-3d" class="viz-panel">
        <div class="viz-card">
            <div class="viz-card-header">
                <h3><i class="fas fa-cube" style="color:#f472b6;"></i> Đồ Thị Quan Hệ 3D & Bảng Kết Quả</h3>
            </div>
            <div class="viz-card-body" style="padding:0;">
                <div class="dual-3d-wrapper" style="padding: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- ========== LEFT: MÔ HÌNH GỐC ========== -->
                    <div class="model-column model-original" style="display: flex; flex-direction: column; background: #0f172a; border-radius: 16px; border: 1px solid rgba(244,114,182,0.3); overflow: hidden;">
                        <!-- Header -->
                        <div class="model-col-header original-header" style="padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; background: rgba(244,114,182,0.05);">
                            <div class="model-col-title" style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #f1f5f9;">
                                <span class="model-dot" style="width:10px;height:10px;border-radius:50%;background:#f472b6;box-shadow:0 0 8px #ec4899;"></span>
                                <span>3D TRƯỚC CẢI TIẾN</span>
                            </div>
                            <span class="model-version-badge" style="background:rgba(244,114,182,0.15);color:#f472b6;padding:4px 10px;border-radius:12px;font-size:0.65rem;font-weight:800;">BASELINE</span>
                        </div>
                        
                        <!-- 3D Graph -->
                        <div class="model-3d-box" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <div id="3d-graph-container-original" class="dual-3d-canvas" style="width:100%;height:320px;border-radius:12px;overflow:hidden;background:radial-gradient(ellipse at center, #0f172a 0%, #020617 100%);"></div>
                        </div>

                        <!-- Rich Results Panel -->
                        <div id="panel-3d-info-original" class="model-rich-results" style="flex: 1; min-height: 400px; display: flex; flex-direction: column; background: #0b1120;">
                            <div class="info-panel-placeholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2rem;text-align:center;color:#475569;">
                                <div class="placeholder-icon" style="font-size:2.5rem;color:rgba(244,114,182,0.3);margin-bottom:1rem;"><i class="fas fa-flask"></i></div>
                                <h4 style="color:#94a3b8;font-weight:600;margin-bottom:0.5rem;">Chưa có dữ liệu</h4>
                                <p style="font-size:0.8rem;">Hãy chạy dự đoán để xem kết quả.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ========== RIGHT: MÔ HÌNH CẢI TIẾN ========== -->
                    <div class="model-column model-improved" style="display: flex; flex-direction: column; background: #0f172a; border-radius: 16px; border: 1px solid rgba(52,211,153,0.3); overflow: hidden;">
                        <!-- Header -->
                        <div class="model-col-header improved-header" style="padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; background: rgba(52,211,153,0.05);">
                            <div class="model-col-title" style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #f1f5f9;">
                                <span class="model-dot" style="width:10px;height:10px;border-radius:50%;background:#34d399;box-shadow:0 0 8px #10b981;"></span>
                                <span>3D ĐÃ CẢI TIẾN</span>
                            </div>
                            <span class="model-version-badge" style="background:rgba(52,211,153,0.15);color:#34d399;padding:4px 10px;border-radius:12px;font-size:0.65rem;font-weight:800;">IMPROVED</span>
                        </div>
                        
                        <!-- 3D Graph -->
                        <div class="model-3d-box" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <div id="3d-graph-container-improved" class="dual-3d-canvas" style="width:100%;height:320px;border-radius:12px;overflow:hidden;background:radial-gradient(ellipse at center, #0f172a 0%, #020617 100%);"></div>
                        </div>

                        <!-- Rich Results Panel -->
                        <div id="panel-3d-info-improved" class="model-rich-results" style="flex: 1; min-height: 400px; display: flex; flex-direction: column; background: #0b1120;">
                            <div class="info-panel-placeholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2rem;text-align:center;color:#475569;">
                                <div class="placeholder-icon" style="font-size:2.5rem;color:rgba(52,211,153,0.3);margin-bottom:1rem;"><i class="fas fa-rocket"></i></div>
                                <h4 style="color:#94a3b8;font-weight:600;margin-bottom:0.5rem;">Chưa có dữ liệu</h4>
                                <p style="font-size:0.8rem;">Hãy chạy dự đoán để xem kết quả.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CURVE PANEL -->
    <div id="panel-curve" class="viz-panel">
        <div class="viz-card">
            <div class="viz-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <h3><i class="fas fa-chart-line" style="color:#34d399;"></i> So Sánh Đường Huấn Luyện: Gốc vs Cải Tiến</h3>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.75rem;color:#94a3b8;font-weight:700;">Chỉ số:</label>
                    <select id="curve-metric-select" onchange="loadTrainingCurve()"
                        style="background:rgba(30,41,59,0.8);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e2e8f0;padding:6px 12px;font-size:0.8rem;font-weight:700;cursor:pointer;">
                        <option value="auc">AUC</option>
                        <option value="aupr">AUPR</option>
                        <option value="accuracy">Accuracy</option>
                        <option value="f1">F1-Score</option>
                    </select>
                </div>
            </div>
            <div class="viz-card-body">
                <div style="display:flex;gap:8px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:24px;height:3px;background:#f472b6;border-radius:2px;display:inline-block;border-top:2px dashed #f472b6;"></span>
                        <span style="font-size:0.75rem;color:#f472b6;font-weight:700;">Chưa cải tiến (Baseline)</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:24px;height:3px;background:#34d399;border-radius:2px;display:inline-block;"></span>
                        <span style="font-size:0.75rem;color:#34d399;font-weight:700;">Đã cải tiến (Improved)</span>
                    </div>
                </div>
                <canvas id="trainingCurveChart" style="height:400px;"></canvas>
                <div id="curve-comparison-stats" style="margin-top:20px;"></div>
            </div>
        </div>
    </div>


</div>

<!-- CLINICAL ABSTRACT MODAL -->
<div id="abstract-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="abstract-modal-title"><i class="fas fa-file-medical" style="color:#10b981;"></i> Clinical Abstract -
                MedBot 2.0</h3>
            <button class="modal-close" onclick="closeAbstractModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="abstract-modal-body" style="max-height: 70vh; overflow-y: auto;"></div>
    </div>
</div>

<!-- MODAL -->
<div id="modal-overlay" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title"><i class="fas fa-info-circle" style="color:#818cf8;"></i> Chi Tiết</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modal-body"></div>
    </div>
</div>

<script src="assets/js/amdgt-predict-v2.js?v=<?= time() ?>"></script>

<script>
    // Auto-predict logic for "Tái tạo 3D" from History page
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const q = urlParams.get('q');
        const type = urlParams.get('type');

        if (q && type) {
            // Kiểm tra xem trang có đang bị F5 (reload) không
            const navEntries = window.performance.getEntriesByType("navigation");
            const isReload = (navEntries.length > 0 && navEntries[0].type === "reload") ||
                (window.performance.navigation && window.performance.navigation.type === 1);

            if (!isReload) {
                // Xóa tham số khỏi URL
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: newUrl }, '', newUrl);

                // Đợi 800ms để đảm bảo amdgt-predict.js đã khởi tạo xong các component
                setTimeout(() => {
                    const dataset = document.getElementById('global-dataset').value || 'C-dataset';
                    fetch(`api/search.php?type=${type}&q=${encodeURIComponent(q)}&dataset=${dataset}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.length > 0) {
                                // Tìm kết quả khớp tên chính xác, nếu không lấy kết quả đầu tiên
                                const match = data.find(d => d.name.toLowerCase() === q.toLowerCase()) || data[0];

                                if (type === 'drug') {
                                    const input = document.getElementById('drug-search');
                                    const idxInput = document.getElementById('drug-idx');
                                    if (input && idxInput) {
                                        input.value = match.name;
                                        idxInput.value = match.idx;
                                        if (typeof predictDrug === 'function') predictDrug();
                                    }
                                } else if (type === 'disease') {
                                    const input = document.getElementById('disease-search');
                                    const idxInput = document.getElementById('disease-idx');
                                    if (input && idxInput) {
                                        input.value = match.name;
                                        idxInput.value = match.idx;
                                        if (typeof predictDisease === 'function') predictDisease();
                                    }
                                }
                            }
                        })
                        .catch(err => console.error('Auto-predict error:', err));
                }, 800);
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>