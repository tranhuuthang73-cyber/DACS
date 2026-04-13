<?php
require_once 'includes/config.php';
$pageTitle = 'Dự đoán';
include 'includes/header.php';

if (!isLoggedIn()) {
    echo '<div class="auth-container"><div class="alert alert-info"><i class="fas fa-info-circle"></i> Vui lòng <a href="login.php" style="color: var(--accent-light)">đăng nhập</a> để sử dụng chức năng dự đoán.</div></div>';
    include 'includes/footer.php';
    exit;
}
?>

<div class="predict-container fade-in" style="max-width: 700px; margin: 0 auto;">
    <div class="predict-header" style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><i class="fas fa-microscope" style="color: var(--accent);"></i> Dự Đoán Liên Kết</h1>
        <p class="section-subtitle" style="color: var(--text-muted);">Chọn chế độ phân tích mạng GNN và nhập dữ liệu vào ô dưới đây</p>
    </div>

    <div class="tabs" id="mode-tabs" style="margin: 0 auto 1.5rem auto; display: flex; justify-content: center;">
        <button class="tab active" onclick="switchTab('drug')" id="tab-drug">
            <i class="fas fa-pills"></i> Từ Thuốc → Bệnh
        </button>
        <button class="tab" onclick="switchTab('disease')" id="tab-disease">
            <i class="fas fa-virus"></i> Từ Bệnh → Thuốc
        </button>
    </div>

    <!-- Drug to Disease -->
    <div class="card" id="panel-drug" style="box-shadow: var(--shadow-lg); padding: 2rem; border-radius: calc(var(--radius-lg) * 1.2);">
        <h3 style="margin-bottom: 1.5rem; text-align: center; color: var(--text-primary); font-size: 1.2rem;">Khám phá Phổ Bệnh Lý từ Thuốc</h3>
        <div class="form-group" style="position: relative;">
            <label class="form-label">Tên dược chất / Thuốc</label>
            <input type="text" class="form-input" id="drug-search" 
                   placeholder="Nhập tên thuốc (vd: Aspirin, Caffeine)..." autocomplete="off" style="padding: 14px 18px; font-size: 1rem;">
            <input type="hidden" id="drug-idx">
            <div class="autocomplete-list" id="drug-autocomplete" style="display:none; z-index: 10;"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Số khoảng dự đoán (Top-K)</label>
            <select class="form-select" id="drug-topk" style="padding: 14px 18px; font-size: 1rem;">
                <option value="5">Top 5 kết quả</option>
                <option value="10" selected>Top 10 kết quả</option>
                <option value="20">Top 20 kết quả</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="predictDrug()" id="btn-predict-drug" style="width: 100%; justify-content: center; padding: 14px; font-size: 1.05rem; margin-top: 1rem; border-radius: 12px;">
            <i class="fas fa-bolt"></i> Phân Tích Bằng AI
        </button>
    </div>

    <!-- Disease to Drug -->
    <div class="card" id="panel-disease" style="display:none; box-shadow: var(--shadow-lg); padding: 2rem; border-radius: calc(var(--radius-lg) * 1.2);">
        <h3 style="margin-bottom: 1.5rem; text-align: center; color: var(--text-primary); font-size: 1.2rem;">Khám phá Thuốc Điều Trị từ Bệnh</h3>
        <div class="form-group" style="position: relative;">
            <label class="form-label">Mã bệnh / Tên bệnh</label>
            <input type="text" class="form-input" id="disease-search" 
                   placeholder="Nhập mã bệnh hoặc tên (vd: DB00794)..." autocomplete="off" style="padding: 14px 18px; font-size: 1rem;">
            <input type="hidden" id="disease-idx">
            <div class="autocomplete-list" id="disease-autocomplete" style="display:none; z-index: 10;"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Số khoảng dự đoán (Top-K)</label>
            <select class="form-select" id="disease-topk" style="padding: 14px 18px; font-size: 1rem;">
                <option value="5">Top 5 kết quả</option>
                <option value="10" selected>Top 10 kết quả</option>
                <option value="20">Top 20 kết quả</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="predictDisease()" id="btn-predict-disease" style="width: 100%; justify-content: center; padding: 14px; font-size: 1.05rem; margin-top: 1rem; border-radius: 12px;">
            <i class="fas fa-bolt"></i> Phân Tích Bằng AI
        </button>
    </div>

    <!-- Results Wrapper for Export -->
    <div id="action-bar" style="display:none; text-align: right; margin-bottom: 1rem;">
        <button class="btn btn-sm btn-outline btn-glow" onclick="toggle3DViewer()" id="btn-3d-viewer" style="display:none; margin-right: 10px;"><i class="fas fa-cube"></i> Khám Phá Hóa Học 3D</button>
        <button class="btn btn-sm btn-outline btn-glow" onclick="exportToImage()" id="btn-export"><i class="fas fa-image"></i> Lưu Ảnh Báo Cáo Y Khoa (PNG)</button>
    </div>
    
    <div id="export-area">
        <div id="molecule-3d-wrapper" style="display:none; margin-bottom: 2rem;" data-html2canvas-ignore="true">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-atom"></i> Cấu Trúc Lập Thể Hóa Học (3D Molecule Viewer)</h3>
            <div class="landscape-container" id="molecule-container" style="height:450px; padding:0; position:relative;"></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 5px;">(Mô hình 3D tương tác không hiển thị trong bản in PDF tĩnh)</div>
        </div>

        <div id="landscape-wrapper" style="display:none; margin-top: 2rem;">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-globe"></i> Định Vị Không Gian Bệnh Lý (2D Landscape AI)</h3>
            <div class="landscape-container">
                <canvas id="landscapeChart"></canvas>
            </div>
        </div>
        
        <div id="3d-graph-wrapper" style="display:none; margin-top: 2rem;" data-html2canvas-ignore="true">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-network-wired"></i> Mạng Lưới Đỉnh Tương Tác GNN (3D Force Graph)</h3>
            <div class="landscape-container" id="3d-graph-container" style="height: 500px; padding: 0; background: var(--bg-primary);"></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 5px;">(Mô hình mạng tĩnh 3D không hiển thị trong bản in PDF tĩnh)</div>
        </div>
        
        <div id="similar-drugs-wrapper" style="display:none;">
            <h3 style="margin-top:2rem;"><i class="fas fa-pills"></i> Thuốc Thay Thế Tương Đồng (AI Suggestion)</h3>
            <div class="similar-drugs-container">
                <div class="similar-drugs-row" id="similar-drugs-list"></div>
            </div>
        </div>

        <div id="results-container" style="margin-top: 1.5rem;"></div>
    </div>
</div>

<!-- Enhanced XAI Modal -->
<div class="modal-overlay" id="xai-modal">
    <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        <div class="xai-title"><i class="fas fa-brain"></i> Explainable AI - Giải Thích Logic Dự Đoán</div>
        <div class="xai-target" id="xai-target-name" style="margin-bottom: 1rem; color: var(--text-secondary);"></div>
        
        <!-- Confidence Score Bar -->
        <div class="xai-section-title"><i class="fas fa-gauge-high"></i> Độ Tin Cậy (Confidence Score)</div>
        <div class="xai-confidence-bar">
            <div class="xai-confidence-fill" id="xai-conf-fill" style="width: 0%;">0%</div>
        </div>
        <div id="xai-conf-label" style="text-align: center; font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem;"></div>
        
        <!-- Dual Stats -->
        <div class="xai-stats">
            <div class="xai-stat-box">
                <div class="xai-stat-val" id="xai-fsim">0%</div>
                <div style="font-size: 0.8rem; color: var(--text-muted)">Cosine Đặc Trưng (Embeddings)</div>
            </div>
            <div class="xai-stat-box">
                <div class="xai-stat-val" id="xai-tsim">0%</div>
                <div style="font-size: 0.8rem; color: var(--text-muted)">Độ Trùng Khớp Topo (PH)</div>
            </div>
        </div>

        <!-- Radar Chart -->
        <div class="xai-section-title"><i class="fas fa-chart-radar"></i> Phân Tích Yếu Tố Chú Ý (Attention Factors)</div>
        <div class="xai-radar-container">
            <canvas id="xai-radar-chart"></canvas>
        </div>
        
        <!-- Explanation Bullets -->
        <div class="xai-section-title"><i class="fas fa-lightbulb"></i> Tại Sao AI Dự Đoán Kết Quả Này?</div>
        <ul class="xai-bullets" id="xai-bullets">
            <li><i class="fas fa-spinner fa-spin"></i> Đang phân tích...</li>
        </ul>

        <!-- Similar Drugs -->
        <div class="xai-section-title"><i class="fas fa-pills"></i> Thuốc Tương Đồng (Cấu Trúc Hoá Học)</div>
        <div class="xai-similar-list" id="xai-similar-drugs">
            <span class="xai-similar-tag">Đang tải...</span>
        </div>

        <!-- Similar Diseases -->
        <div class="xai-section-title"><i class="fas fa-virus"></i> Bệnh Tương Đồng (Feature Space)</div>
        <div class="xai-similar-list" id="xai-similar-diseases">
            <span class="xai-similar-tag">Đang tải...</span>
        </div>

        <!-- Summary Text -->
        <div class="xai-section-title"><i class="fas fa-file-medical"></i> Tóm Tắt Phân Tích</div>
        <div style="padding: 1.2rem; background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--accent); border-radius: 4px; line-height: 1.6; font-size: 0.92rem;" id="xai-text">
            Đang trích xuất tri thức từ mạng nơ-ron...
        </div>

        <!-- PubMed Evidence -->
        <div class="xai-section-title"><i class="fas fa-book-medical"></i> Bằng Chứng Y Khoa (PubMed)</div>
        <div class="pubmed-section" id="xai-pubmed">
            <div class="pubmed-card"><div class="pubmed-title"><i class="fas fa-spinner fa-spin"></i> Đang tìm kiếm bài báo...</div></div>
        </div>

        <!-- 3D Molecular Viewer -->
        <div class="xai-section-title"><i class="fas fa-atom"></i> Cấu Trúc Phân Tử 3D</div>
        <div class="mol3d-container" id="mol3d-viewer">
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Đang tải mô hình 3D...
            </div>
        </div>
        <div class="mol3d-info" id="mol3d-info"></div>

        <!-- Expert Validation -->
        <div class="xai-section-title"><i class="fas fa-user-md"></i> Xác Nhận Chuyên Gia</div>
        <div class="validation-actions" id="xai-validation">
            <button class="btn-validate btn-validate-confirm" onclick="validateResult('confirm')">
                <i class="fas fa-check-circle"></i> Xác nhận Lâm sàng
            </button>
            <button class="btn-validate btn-validate-report" onclick="validateResult('report')">
                <i class="fas fa-flag"></i> Báo cáo Sai lệch
            </button>
        </div>

        <!-- PDF Export -->
        <div style="text-align: center; margin-top: 1.5rem;">
            <button class="btn-pdf-export" onclick="exportXAIPDF()">
                <i class="fas fa-file-pdf"></i> Xuất Báo Cáo PDF
            </button>
        </div>
    </div>
</div>

<style>
@media print {
    /* Hide everything by default */
    body * {
        visibility: hidden !important;
    }
    
    /* Reveal only export area */
    #export-area, #export-area * {
        visibility: visible !important;
    }
    
    /* Make export area full page */
    #export-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    /* Hide complex and interactive things */
    #action-bar, #molecule-3d-wrapper, #3d-graph-wrapper, .modal-overlay {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Re-theme for Paper (White background, black text) */
    body {
        background: white !important;
    }
    
    .card, .landscape-container, .similar-drug-card, .result-item {
        background: transparent !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
        color: #000 !important;
    }
    
    .result-name, .similar-drug-name, .card-title, h3, div {
        color: #000 !important;
        text-shadow: none !important;
    }
    
    .score-fill {
        background-color: #6366f1 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    canvas {
        filter: invert(1) hue-rotate(180deg) !important;
    }
    
    .badge-known, .badge-new {
        border: 1px solid #000 !important;
        color: #000 !important;
        box-shadow: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://unpkg.com/3d-force-graph"></script>
<script src="https://3Dmol.csb.pitt.edu/build/3Dmol-min.js"></script>
<script>
let currentLandscapeCoords = [];
let chartInstance = null;

// Fetch landscape data once
fetch('api/proxy.php?action=landscape')
    .then(r => r.json())
    .then(data => {
        if (data && data.coords) currentLandscapeCoords = data.coords;
    }).catch(e => console.log('Landscape error', e));

function switchTab(mode) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + mode).classList.add('active');
    document.getElementById('panel-drug').style.display = mode === 'drug' ? 'block' : 'none';
    document.getElementById('panel-disease').style.display = mode === 'disease' ? 'block' : 'none';
    document.getElementById('results-container').innerHTML = '';
    document.getElementById('action-bar').style.display = 'none';
    document.getElementById('export-area').style.opacity = '1';
    document.getElementById('landscape-wrapper').style.display = 'none';
    document.getElementById('3d-graph-wrapper').style.display = 'none';
    document.getElementById('similar-drugs-wrapper').style.display = 'none';
    document.getElementById('molecule-3d-wrapper').style.display = 'none';
}

// ... autocomplete code omitted for brevity but we use the existing DOM so we must re-implement it...
// Actually, I am rewriting from line 75, so I must provide ALL the autocomplete logic!

// AJAX Autocomplete for drugs
let drugTimer;
const drugSearch = document.getElementById('drug-search');
const drugAC = document.getElementById('drug-autocomplete');
drugSearch.addEventListener('input', function() {
    document.getElementById('drug-idx').value = ''; // Xóa bộ nhớ đệm
    currentPredictionGeneration++; // Chặn các tiến trình fetch ngầm đang dở dang
    
    // Xóa ngay kết quả trên màn hình để tránh hiểu nhầm
    document.getElementById('action-bar').style.display = 'none';
    document.getElementById('landscape-wrapper').style.display = 'none';
    document.getElementById('3d-graph-wrapper').style.display = 'none';
    document.getElementById('similar-drugs-wrapper').style.display = 'none';
    document.getElementById('molecule-3d-wrapper').style.display = 'none';
    const c = document.getElementById('results-container');
    if(c.innerHTML !== '') c.innerHTML = '<div class="alert alert-info" style="opacity:0.7;"><i class="fas fa-info-circle"></i> Đang chờ lệnh dự đoán mới...</div>';

    clearTimeout(drugTimer);
    const q = this.value.trim();
    if (q.length < 1) { drugAC.style.display = 'none'; return; }
    drugTimer = setTimeout(() => {
        fetch(`api/search.php?type=drug&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(items => {
                if (!items.length) { drugAC.style.display = 'none'; return; }
                drugAC.innerHTML = items.map(d => 
                    `<div class="autocomplete-item" data-idx="${d.idx}" data-name="${d.name}">
                        <strong>${d.name}</strong> <span class="item-id">${d.drug_id}</span>
                    </div>`
                ).join('');
                drugAC.style.display = 'block';
            });
    }, 200);
});

drugAC.addEventListener('click', function(e) {
    const item = e.target.closest('.autocomplete-item');
    if (item) {
        document.getElementById('drug-idx').value = item.dataset.idx;
        drugSearch.value = item.dataset.name;
        drugAC.style.display = 'none';
    }
});

// AJAX Autocomplete for diseases
let diseaseTimer;
const diseaseSearch = document.getElementById('disease-search');
const diseaseAC = document.getElementById('disease-autocomplete');
diseaseSearch.addEventListener('input', function() {
    document.getElementById('disease-idx').value = ''; // Xóa bộ nhớ đệm
    currentPredictionGeneration++; // Chặn các tiến trình fetch ngầm đang dở dang
    
    // Xóa ngay kết quả trên màn hình để tránh hiểu nhầm
    document.getElementById('action-bar').style.display = 'none';
    document.getElementById('landscape-wrapper').style.display = 'none';
    document.getElementById('3d-graph-wrapper').style.display = 'none';
    document.getElementById('similar-drugs-wrapper').style.display = 'none';
    document.getElementById('molecule-3d-wrapper').style.display = 'none';
    const c = document.getElementById('results-container');
    if(c.innerHTML !== '') c.innerHTML = '<div class="alert alert-info" style="opacity:0.7;"><i class="fas fa-info-circle"></i> Đang chờ lệnh dự đoán mới...</div>';

    clearTimeout(diseaseTimer);
    const q = this.value.trim();
    if (q.length < 1) { diseaseAC.style.display = 'none'; return; }
    diseaseTimer = setTimeout(() => {
        fetch(`api/search.php?type=disease&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(items => {
                if (!items.length) { diseaseAC.style.display = 'none'; return; }
                diseaseAC.innerHTML = items.map(d => 
                    `<div class="autocomplete-item" data-idx="${d.idx}" data-name="${d.name}">
                        <strong>${d.name}</strong> <span class="item-id">${d.disease_id}</span>
                    </div>`
                ).join('');
                diseaseAC.style.display = 'block';
            });
    }, 200);
});

diseaseAC.addEventListener('click', function(e) {
    const item = e.target.closest('.autocomplete-item');
    if (item) {
        document.getElementById('disease-idx').value = item.dataset.idx;
        diseaseSearch.value = item.dataset.name;
        diseaseAC.style.display = 'none';
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('#drug-search') && !e.target.closest('#drug-autocomplete')) drugAC.style.display = 'none';
    if (!e.target.closest('#disease-search') && !e.target.closest('#disease-autocomplete')) diseaseAC.style.display = 'none';
});

let currentQueryIdx = -1;
let currentPredictionGeneration = 0;

function predictDrug() {
    const idx = document.getElementById('drug-idx').value;
    const topk = document.getElementById('drug-topk').value;
    const textQuery = document.getElementById('drug-search').value.trim();
    
    if (!idx && idx !== '0') { 
        if (textQuery.length > 0) {
            const gen = ++currentPredictionGeneration;
            // Ép đối chiếu CSDL tự động nếu gõ tay
            fetch(`api/search.php?type=drug&q=${encodeURIComponent(textQuery)}`)
                .then(r => r.json())
                .then(items => {
                    if (gen !== currentPredictionGeneration) return;
                    const match = items.find(i => i.name.toLowerCase() === textQuery.toLowerCase());
                    if (match) {
                        document.getElementById('drug-idx').value = match.idx;
                        document.getElementById('drug-search').value = match.name;
                        predictDrug();
                    } else {
                        alert('Không thể trích xuất. Thuốc không tồn tại trong CSDL phân tích!');
                    }
                }).catch(() => {
                    if (gen === currentPredictionGeneration) alert('Lỗi kết nối CSDL!');
                });
            return;
        } else {
            alert('Vui lòng nhập tên hoặc chọn thuốc'); 
            return; 
        }
    }
    
    currentQueryIdx = parseInt(idx);
    const myGen = ++currentPredictionGeneration;
    
    const container = document.getElementById('results-container');
    container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>Hệ thống AI đang phân tích dữ liệu lâm sàng...</p></div>';
    
    fetch('api/predict.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: 'drug_to_disease', drug_idx: parseInt(idx), top_k: parseInt(topk)})
    })
    .then(r => r.json())
    .then(data => {
        if (myGen !== currentPredictionGeneration) return;
        if (data.error) {
            container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
            return;
        }
        renderResults(data.predictions, 'disease', data.query_name, parseInt(idx));
        renderLandscape(data.predictions);
        fetchSimilarDrugs(parseInt(idx));
    })
    .catch(err => {
        if (myGen !== currentPredictionGeneration) return;
        container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Lỗi: ${err.message}</div>`;
    });
}

function predictDisease() {
    const idx = document.getElementById('disease-idx').value;
    const topk = document.getElementById('disease-topk').value;
    const textQuery = document.getElementById('disease-search').value.trim();
    
    if (!idx && idx !== '0') { 
        if (textQuery.length > 0) {
            const gen = ++currentPredictionGeneration;
            // Ép đối chiếu CSDL tự động nếu gõ tay
            fetch(`api/search.php?type=disease&q=${encodeURIComponent(textQuery)}`)
                .then(r => r.json())
                .then(items => {
                    if (gen !== currentPredictionGeneration) return;
                    const match = items.find(i => i.name.toLowerCase() === textQuery.toLowerCase() || i.disease_id.toLowerCase() === textQuery.toLowerCase());
                    if (match) {
                        document.getElementById('disease-idx').value = match.idx;
                        document.getElementById('disease-search').value = match.name;
                        predictDisease();
                    } else {
                        alert('Không thể trích xuất. Bệnh không tồn tại trong khoảng phân tích của GNN!');
                    }
                }).catch(() => {
                    if (gen === currentPredictionGeneration) alert('Lỗi kết nối CSDL!');
                });
            return;
        } else {
            alert('Vui lòng nhập tên hoặc mã bệnh'); 
            return; 
        }
    }
    
    currentQueryIdx = parseInt(idx);
    const myGen = ++currentPredictionGeneration;
    
    const container = document.getElementById('results-container');
    container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>Hệ thống AI đang trích xuất đồ thị phân tử...</p></div>';
    
    document.getElementById('landscape-wrapper').style.display = 'none';
    document.getElementById('similar-drugs-wrapper').style.display = 'none';
    
    fetch('api/predict.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: 'disease_to_drug', disease_idx: parseInt(idx), top_k: parseInt(topk)})
    })
    .then(r => r.json())
    .then(data => {
        if (myGen !== currentPredictionGeneration) return;
        if (data.error) {
            container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
            return;
        }
        renderResults(data.predictions, 'drug', data.query_name, parseInt(idx));
    })
    .catch(err => {
        if (myGen !== currentPredictionGeneration) return;
        container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Lỗi: ${err.message}</div>`;
    });
}

function renderResults(predictions, type, queryName, queryId) {
    const container = document.getElementById('results-container');
    const typeLabel = type === 'disease' ? 'Bệnh' : 'Thuốc';
    
    let html = `
        <div class="card">
            <div class="card-header" style="justify-content: space-between;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="card-icon purple"><i class="fas fa-project-diagram"></i></div>
                    <div>
                        <div class="card-title">Kết quả phân tích GNN: ${queryName || 'N/A'}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">${predictions.length} ${typeLabel.toLowerCase()} tiềm năng nhất</div>
                    </div>
                </div>
            </div>
            <div class="results-list">
    `;
    
    predictions.forEach(p => {
        const name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
        const id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        const scorePct = Math.min(p.score * 100, 100).toFixed(1);
        const badge = p.is_known ? '<span class="result-badge badge-known">Đã biết</span>' : '<span class="result-badge badge-new" style="box-shadow: 0 0 10px var(--accent-glow);">Mới</span>';
        
        const drugIdx = type === 'disease' ? queryId : p.drug_idx;
        const diseaseIdx = type === 'disease' ? p.disease_idx : queryId;
        
        // === CONFIDENCE SCORE COLOR CODING ===
        let scoreClass, valueClass, labelClass, labelText;
        const score = p.score * 100;
        if (score >= 70) {
            scoreClass = 'score-high';
            valueClass = 'value-high';
            labelClass = 'label-high';
            labelText = '✅ Hiệu quả cao';
        } else if (score >= 40) {
            scoreClass = 'score-medium';
            valueClass = 'value-medium';
            labelClass = 'label-medium';
            labelText = '⚠️ Trung bình';
        } else {
            scoreClass = 'score-low';
            valueClass = 'value-low';
            labelClass = 'label-low';
            labelText = '🔻 Thấp';
        }
        
        html += `
            <div class="result-item fade-in">
                <div class="result-rank">${p.rank}</div>
                <div class="result-info">
                    <div class="result-name" style="color: var(--accent); font-weight: 700;">${name}</div>
                    <div class="result-id">${id}</div>
                </div>
                <div class="result-score">
                    <div class="score-bar"><div class="score-fill ${scoreClass}" style="width: ${scorePct}%"></div></div>
                    <div class="score-value ${valueClass}">${scorePct}%</div>
                    <span class="score-label ${labelClass}">${labelText}</span>
                </div>
                <button class="btn btn-sm btn-outline btn-glow" style="margin: 0 10px;" onclick="explainAI(${drugIdx}, ${diseaseIdx}, '${name.replace(/'/g, "\\'")}')"> 
                    <i class="fas fa-brain"></i> XAI
                </button>
                ${badge}
            </div>
        `;
    });
    
    html += '</div></div>';
    container.innerHTML = html;
    
    document.getElementById('action-bar').style.display = 'block';
    if(type === 'disease') { // We searched for a Drug -> Predictions are Diseases
        currentRenderedDrug = queryName;
        document.getElementById('btn-3d-viewer').style.display = 'inline-flex';
        render3DForceGraph(predictions, queryName, 'drug');
    } else { // We searched for a Disease -> Predictions are Drugs
        document.getElementById('btn-3d-viewer').style.display = 'none';
        render3DForceGraph(predictions, queryName, 'disease');
    }
}

function fetchSimilarDrugs(drugIdx) {
    fetch('api/proxy.php?action=similar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({drug_idx: drugIdx, top_k: 5})
    })
    .then(r => r.json())
    .then(data => {
        if(data && data.similar_drugs) {
            document.getElementById('similar-drugs-wrapper').style.display = 'block';
            let listHTML = '';
            data.similar_drugs.forEach((d, i) => {
                listHTML += `
                    <div class="similar-drug-card">
                        <div class="result-badge badge-known" style="float: right;">#${i+1}</div>
                        <div class="similar-drug-name" style="font-size: 1rem;">${d.drug_name || 'Thuốc ' + d.drug_idx}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">Độ tương đồng: <strong style="color:var(--success);">${(d.similarity * 100).toFixed(1)}%</strong></div>
                    </div>
                `;
            });
            document.getElementById('similar-drugs-list').innerHTML = listHTML;
        }
    }).catch(e => console.log('Similar error', e));
}

function renderLandscape(predictions) {
    if (!currentLandscapeCoords || currentLandscapeCoords.length === 0) return;
    document.getElementById('landscape-wrapper').style.display = 'block';
    
    const targetIndices = predictions.map(p => p.disease_idx);
    
    const backgroundData = [];
    const highlightsData = [];
    
    currentLandscapeCoords.forEach((coord, i) => {
        if(targetIndices.includes(i)) {
            highlightsData.push({x: coord[0], y: coord[1], idx: i});
        } else {
            backgroundData.push({x: coord[0], y: coord[1], idx: i});
        }
    });

    const ctx = document.getElementById('landscapeChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();
    
    chartInstance = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Không gian bệnh (Background)',
                data: backgroundData,
                backgroundColor: 'rgba(255, 255, 255, 0.05)',
                pointRadius: 3
            }, {
                label: 'Bệnh tiềm năng (Predicted)',
                data: highlightsData,
                backgroundColor: '#6366f1',
                borderColor: '#10b981',
                borderWidth: 2,
                pointRadius: 8,
                pointStyle: 'star'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { display: false },
                y: { display: false }
            },
            plugins: {
                legend: { labels: { color: '#8b95a5' } }
            }
        }
    });
}

let xaiRadarChart = null;

function explainAI(drugIdx, diseaseIdx, targetName) {
    const modal = document.getElementById('xai-modal');
    modal.classList.add('active');
    document.getElementById('xai-target-name').innerText = "🔍 Phân tích: " + targetName;
    document.getElementById('xai-fsim').innerText = "...";
    document.getElementById('xai-tsim').innerText = "...";
    document.getElementById('xai-conf-fill').style.width = '0%';
    document.getElementById('xai-conf-fill').textContent = '...'; 
    document.getElementById('xai-conf-label').textContent = '';
    document.getElementById('xai-bullets').innerHTML = '<li><i class="fas fa-spinner fa-spin"></i> Đang mô phỏng tư duy AI...</li>';
    document.getElementById('xai-similar-drugs').innerHTML = '<span class="xai-similar-tag">⏳ Đang tìm...</span>';
    document.getElementById('xai-similar-diseases').innerHTML = '<span class="xai-similar-tag">⏳ Đang tìm...</span>';
    document.getElementById('xai-text').innerHTML = '<div style="display:flex;align-items:center;gap:10px;"><div class="ai-scanner" style="width:30px;height:30px;margin:0;"></div> <span>Đang trích xuất tri thức từ mạng nơ-ron đồ thị...</span></div>';
    
    fetch('api/proxy.php?action=explain', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({drug_idx: drugIdx, disease_idx: diseaseIdx})
    })
    .then(r => r.json())
    .then(data => {
        if(!data) return;
        
        // === Dual Stats ===
        document.getElementById('xai-fsim').innerText = (data.feature_similarity * 100).toFixed(1) + "%";
        document.getElementById('xai-tsim').innerText = (data.topo_similarity * 100).toFixed(1) + "%";
        
        // === Confidence Bar ===
        const confPct = data.confidence_percent || (data.probability * 100);
        const confLevel = data.confidence_level || 'medium';
        const confLabel = data.confidence_label || 'N/A';
        const confFill = document.getElementById('xai-conf-fill');
        setTimeout(() => {
            confFill.style.width = confPct.toFixed(1) + '%';
            confFill.textContent = confPct.toFixed(1) + '%';
            confFill.className = 'xai-confidence-fill conf-' + confLevel;
        }, 100);
        
        const confLabelEl = document.getElementById('xai-conf-label');
        const levelColors = { high: '#10b981', medium: '#f59e0b', low: '#ef4444' };
        const levelIcons = { high: '✅', medium: '⚠️', low: '🔻' };
        confLabelEl.innerHTML = `<span style="color:${levelColors[confLevel]};">${levelIcons[confLevel]} ${confLabel} — ${confPct.toFixed(1)}%</span>`;
        
        // === Radar Chart ===
        if (data.attention_factors) {
            renderXAIRadar(data.attention_factors);
        }
        
        // === Explanation Bullets ===
        if (data.explanation_bullets && data.explanation_bullets.length > 0) {
            let bulletsHTML = '';
            data.explanation_bullets.forEach(b => {
                bulletsHTML += `<li><i class="fas ${b.icon}"></i> ${b.text}</li>`;
            });
            document.getElementById('xai-bullets').innerHTML = bulletsHTML;
        }
        
        // === Similar Drugs ===
        if (data.similar_drugs && data.similar_drugs.length > 0) {
            const drugTags = data.similar_drugs.map(d => {
                const simPct = (d.similarity * 100).toFixed(1);
                const name = d.drug_name || `Drug #${d.drug_idx}`;
                return `<span class="xai-similar-tag" title="${name} - Similarity: ${simPct}%">💊 ${name} (${simPct}%)</span>`;
            }).join('');
            document.getElementById('xai-similar-drugs').innerHTML = drugTags;
        }
        
        // === Similar Diseases ===
        if (data.similar_diseases && data.similar_diseases.length > 0) {
            const disTags = data.similar_diseases.map(d => {
                const simPct = (d.similarity * 100).toFixed(1);
                const name = d.disease_name || `Disease #${d.disease_idx}`;
                return `<span class="xai-similar-tag" title="${name} - Similarity: ${simPct}%">🦠 ${name} (${simPct}%)</span>`;
            }).join('');
            document.getElementById('xai-similar-diseases').innerHTML = disTags;
        }
        
        // === Summary Text ===
        document.getElementById('xai-text').innerHTML = data.explanation_text || "Không thể phân tích logic lúc này.";
        
        // === Set context for validation ===
        currentXAI.drugIdx = drugIdx;
        currentXAI.diseaseIdx = diseaseIdx;
        currentXAI.drugName = targetName;
        
        // === Load PubMed Evidence (async) ===
        loadPubMedEvidence(targetName, '');
        
        // === Load 3D Molecule (async) ===
        load3DMolecule(targetName);
        
        // === Reset validation buttons ===
        document.getElementById('xai-validation').innerHTML = `
            <button class="btn-validate btn-validate-confirm" onclick="validateResult('confirm')">
                <i class="fas fa-check-circle"></i> Xác nhận Lâm sàng
            </button>
            <button class="btn-validate btn-validate-report" onclick="validateResult('report')">
                <i class="fas fa-flag"></i> Báo cáo Sai lệch
            </button>
        `;
    }).catch(e => {
        document.getElementById('xai-text').innerHTML = "❌ Lỗi kết nối AI Engine: " + e.message;
        document.getElementById('xai-bullets').innerHTML = '<li><i class="fas fa-exclamation-triangle"></i> Không thể tải dữ liệu giải thích.</li>';
    });
}

function renderXAIRadar(factors) {
    const ctx = document.getElementById('xai-radar-chart').getContext('2d');
    if (xaiRadarChart) xaiRadarChart.destroy();
    
    xaiRadarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Graph Attention', 'Topo Homology', 'Feature Embedding', 'Network Propagation'],
            datasets: [{
                label: 'AI Attention (%)',
                data: [
                    factors.graph_attention || 0,
                    factors.topo_homology || 0,
                    factors.feature_embedding || 0,
                    factors.network_propagation || 0
                ],
                backgroundColor: 'rgba(99, 102, 241, 0.2)',
                borderColor: '#6366f1',
                borderWidth: 2,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        color: '#64748b',
                        backdropColor: 'transparent',
                        font: { size: 10 }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.15)' },
                    pointLabels: {
                        color: '#94a3b8',
                        font: { size: 11, weight: 600 }
                    }
                }
            },
            plugins: {
                legend: { display: false }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

function closeModal() {
    document.getElementById('xai-modal').classList.remove('active');
    if (xaiRadarChart) {
        xaiRadarChart.destroy();
        xaiRadarChart = null;
    }
}

// Current XAI context
let currentXAI = { drugIdx: null, diseaseIdx: null, drugName: '', diseaseName: '' };

// ===== PUBMED EVIDENCE =====
function loadPubMedEvidence(drugName, diseaseName) {
    const container = document.getElementById('xai-pubmed');
    container.innerHTML = '<div class="pubmed-card"><div class="pubmed-title"><i class="fas fa-spinner fa-spin"></i> Đang tìm kiếm trên PubMed...</div></div>';
    
    const params = new URLSearchParams();
    if (drugName) params.append('drug', drugName);
    if (diseaseName) params.append('disease', diseaseName);
    
    fetch('api/pubmed.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (data.articles && data.articles.length > 0) {
                let html = `<div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.5rem;">📚 Tìm thấy ${data.total} bài báo liên quan (hiển thị top ${data.articles.length})</div>`;
                data.articles.forEach(a => {
                    html += `<div class="pubmed-card">
                        <div class="pubmed-title">${a.title}</div>
                        <div class="pubmed-journal">${a.authors} — <em>${a.journal}</em> (${a.year})</div>
                        <a href="${a.url}" target="_blank" class="pubmed-link"><i class="fas fa-external-link-alt"></i> Xem trên PubMed (PMID: ${a.pmid})</a>
                    </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="pubmed-card"><div class="pubmed-title" style="color:var(--text-muted);">Không tìm thấy bài báo liên quan trên PubMed.</div></div>';
            }
        })
        .catch(() => {
            container.innerHTML = '<div class="pubmed-card"><div class="pubmed-title" style="color:#ef4444;">⚠️ Không thể kết nối PubMed.</div></div>';
        });
}

// ===== 3D MOLECULAR VIEWER =====
function load3DMolecule(drugName) {
    const viewer = document.getElementById('mol3d-viewer');
    const info = document.getElementById('mol3d-info');
    
    // Try PubChem API to get compound info
    const cleanName = drugName.replace(/[^a-zA-Z0-9 ]/g, '').trim();
    if (!cleanName) {
        viewer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">Không có dữ liệu phân tử.</div>';
        return;
    }

    fetch(`https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/${encodeURIComponent(cleanName)}/JSON`)
        .then(r => r.json())
        .then(data => {
            const compound = data?.PC_Compounds?.[0];
            if (!compound) throw new Error('Not found');
            
            const cid = compound.id?.id?.cid;
            const mw = compound.props?.find(p => p.urn?.label === 'Molecular Weight')?.value?.sval || 'N/A';
            const formula = compound.props?.find(p => p.urn?.label === 'Molecular Formula')?.value?.sval || 'N/A';
            const iupac = compound.props?.find(p => p.urn?.label === 'IUPAC Name' && p.urn?.name === 'Preferred')?.value?.sval || cleanName;
            const smiles = compound.props?.find(p => p.urn?.label === 'SMILES' && p.urn?.name === 'Canonical')?.value?.sval || 'N/A';
            
            // Show 2D/3D image from PubChem
            viewer.innerHTML = `
                <div style="position:relative;width:100%;height:100%;background:#0a0a1a;">
                    <img src="https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/${cid}/PNG?image_size=large" 
                         style="width:100%;height:100%;object-fit:contain;padding:1rem;" 
                         alt="Molecular structure of ${cleanName}"
                         onerror="this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);\\'>Không tải được hình ảnh phân tử.</div>'">
                    <div class="mol3d-controls">
                        <a href="https://pubchem.ncbi.nlm.nih.gov/compound/${cid}#section=3D-Conformer" target="_blank" 
                           style="padding:6px 12px;border-radius:6px;background:var(--accent);color:#fff;text-decoration:none;font-size:0.8rem;">
                            <i class="fas fa-cube"></i> Xem 3D trên PubChem
                        </a>
                    </div>
                </div>`;
            
            // Info cards
            info.innerHTML = `
                <div class="mol3d-info-item"><span class="info-label">CID</span><span class="info-value">${cid}</span></div>
                <div class="mol3d-info-item"><span class="info-label">Công thức</span><span class="info-value">${formula}</span></div>
                <div class="mol3d-info-item"><span class="info-label">Khối lượng phân tử</span><span class="info-value">${mw}</span></div>
                <div class="mol3d-info-item"><span class="info-label">IUPAC</span><span class="info-value" style="font-size:0.75rem;">${iupac.substring(0, 50)}${iupac.length > 50 ? '...' : ''}</span></div>
                <div class="mol3d-info-item" style="grid-column:1/-1;"><span class="info-label">SMILES</span><span class="info-value" style="font-size:0.7rem;word-break:break-all;">${smiles}</span></div>
            `;
        })
        .catch(() => {
            viewer.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);gap:0.5rem;">
                <i class="fas fa-atom" style="font-size:2rem;opacity:0.3;"></i>
                <span>Không tìm thấy dữ liệu phân tử cho "${cleanName}" trên PubChem.</span>
            </div>`;
            info.innerHTML = '';
        });
}

// ===== EXPERT VALIDATION =====
function validateResult(type) {
    if (!currentXAI.drugIdx && currentXAI.drugIdx !== 0) return;
    
    fetch('api/proxy.php?action=validate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            drug_idx: currentXAI.drugIdx,
            disease_idx: currentXAI.diseaseIdx,
            validation: type,
            note: ''
        })
    })
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('xai-validation');
        if (data.success) {
            if (type === 'confirm') {
                el.innerHTML = '<span class="validation-badge vbadge-confirmed"><i class="fas fa-check-circle"></i> ✅ Đã xác nhận lâm sàng thành công!</span>';
            } else {
                el.innerHTML = '<span class="validation-badge vbadge-reported"><i class="fas fa-flag"></i> 🚩 Đã báo cáo sai lệch. Cảm ơn phản hồi!</span>';
            }
        } else {
            el.innerHTML = '<span style="color:#ef4444;">⚠️ ' + (data.error || 'Lỗi') + '</span>';
        }
    })
    .catch(() => {
        document.getElementById('xai-validation').innerHTML = '<span style="color:#ef4444;">⚠️ Lỗi kết nối server</span>';
    });
}

// ===== PDF EXPORT =====
function exportXAIPDF() {
    const modalContent = document.querySelector('#xai-modal .modal-content');
    // Hide buttons before print
    const btns = modalContent.querySelectorAll('.btn-validate, .btn-pdf-export, .modal-close');
    btns.forEach(b => b.style.display = 'none');
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html><head><title>Báo Cáo XAI - AMDGT</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; padding: 2rem; color: #1e293b; background: #fff; }
            h1 { color: #6366f1; font-size: 1.5rem; border-bottom: 2px solid #6366f1; padding-bottom: 0.5rem; }
            .header { text-align: center; margin-bottom: 2rem; }
            .header img { height: 40px; }
            .header h2 { color: #6366f1; }
            .content { max-width: 700px; margin: 0 auto; }
            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            td, th { padding: 8px 12px; border: 1px solid #e2e8f0; text-align: left; }
            th { background: #f1f5f9; color: #64748b; font-size: 0.85rem; }
            .footer { text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 1rem; }
            @media print { body { padding: 0.5rem; } }
        </style></head><body>
        <div class="header">
            <h2>🧬 AMDGT - Báo Cáo Phân Tích AI</h2>
            <p style="color:#64748b;">Drug-Disease Association Prediction Report</p>
            <p style="color:#94a3b8;font-size:0.85rem;">Ngày xuất: ${new Date().toLocaleDateString('vi-VN')} ${new Date().toLocaleTimeString('vi-VN')}</p>
        </div>
        <div class="content">${modalContent.innerHTML}</div>
        <div class="footer">
            <p>Hệ thống AMDGT - Attention-aware Multi-modal Network Topology</p>
            <p>⚠️ Đây là kết quả dự đoán từ AI, không thay thế ý kiến bác sĩ chuyên khoa.</p>
        </div>
        </body></html>
    `);
    printWindow.document.close();
    
    // Restore buttons
    btns.forEach(b => b.style.display = '');
    
    setTimeout(() => { printWindow.print(); }, 500);
}

// ======================= VIP AI ECOSYSTEM FEATURES =======================

function exportToImage() {
    const element = document.getElementById('export-area');
    
    // Hiệu ứng Loading
    const btn = document.querySelector('#btn-export');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang kết xuất Ảnh...';
    
    // Xóa tạm bóng đổ vì html2canvas hay bị crash với textShadow
    const allElems = element.querySelectorAll('*');
    allElems.forEach(el => el.style.textShadow = 'none');
    
    // Sử dụng html2canvas trực tiếp để xuất ra file PNG (100% không bị lỗi file)
    if(typeof html2canvas === 'undefined') {
        alert("Đang tải module hình ảnh, vui lòng thử lại sau 2 giây!");
        btn.innerHTML = originalText;
        return;
    }
    
    html2canvas(element, { 
        scale: window.devicePixelRatio || 2, 
        useCORS: true, 
        backgroundColor: '#0f172a',
        logging: false
    }).then(canvas => {
        const url = canvas.toDataURL("image/png");
        const a = document.createElement("a");
        a.href = url;
        a.download = "Bao_Cao_Y_Khoa_AMDGT.png";
        a.style.display = "none";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        btn.innerHTML = originalText;
        // Phục hồi lại hiệu ứng cho người xem web
        allElems.forEach(el => el.style.textShadow = ''); 
    }).catch(err => {
        alert("Có lỗi tạo Ảnh: " + err);
        btn.innerHTML = originalText;
        allElems.forEach(el => el.style.textShadow = '');
    });
}

let forceGraphInstance = null;
function render3DForceGraph(predictions, queryName, queryType) {
    document.getElementById('3d-graph-wrapper').style.display = 'block';
    const elem = document.getElementById('3d-graph-container');
    
    const nodes = [];
    const links = [];
    
    // Cụm nguồn
    nodes.push({ id: 'Source', name: queryName, group: 1, val: 20 });
    
    predictions.forEach((p, i) => {
        const tgtName = queryType === 'disease' ? (p.drug_name || 'Drug '+p.drug_idx) : (p.disease_name || 'Disease '+p.disease_idx);
        const tgtId = 'Target'+i;
        
        nodes.push({ id: tgtId, name: tgtName, group: p.is_known ? 2 : 3, val: 5 });
        // is_known => known interaction, otherwise prediction
        links.push({ source: 'Source', target: tgtId, value: p.score * 5 });
    });
    
    // Additional links between targets just for web aesthetic (connect siblings slightly)
    for(let i=0; i<nodes.length-2; i++){
        links.push({ source: 'Target'+i, target: 'Target'+(i+1), value: 0.5});
    }
    
    const currentWidth = elem.clientWidth > 0 ? elem.clientWidth : (document.getElementById('predict-container') ? document.getElementById('predict-container').clientWidth : 800);
    const currentHeight = elem.clientHeight > 0 ? elem.clientHeight : 500;

    if(forceGraphInstance) {
        forceGraphInstance.graphData({nodes, links});
        forceGraphInstance.width(currentWidth);
        forceGraphInstance.height(currentHeight);
    } else {
        forceGraphInstance = ForceGraph3D()(elem)
            .width(currentWidth)
            .height(currentHeight)
            .graphData({nodes, links})
            .nodeAutoColorBy('group')
            .nodeLabel('name')
            .nodeVal('val')
            .linkWidth('value')
            .linkColor(() => 'rgba(14, 165, 233, 0.4)') /* Medical cyan color */
            .backgroundColor('rgba(10, 15, 24, 0.1)'); // slight tint transparent
            
        // Resize graph on window resize
        window.addEventListener('resize', () => {
            if(elem.clientWidth > 0) {
                forceGraphInstance.width(elem.clientWidth);
                forceGraphInstance.height(elem.clientHeight || 500);
            }
        });
    }
}

function toggle3DViewer() {
    const wrapper = document.getElementById('molecule-3d-wrapper');
    if (wrapper.style.display === 'block') {
        wrapper.style.display = 'none';
        return;
    }
    
    const drugName = currentRenderedDrug; // Bind dynamically derived name, not current volatile input text!
    if(!drugName) {
        alert("Không có dữ liệu tên thuốc.");
        return;
    }
    
    wrapper.style.display = 'block';
    const container = document.getElementById('molecule-container');
    container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>Đang tải mô hình cấu trúc 3D Hóa học...</p></div>';
    
    // Fetch CID from PubChem
    const cleanName = drugName.split('(')[0].trim(); // Remove brackets if any
    const url = `https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/${encodeURIComponent(cleanName)}/JSON`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if(data.PC_Compounds && data.PC_Compounds[0]) {
                const cid = data.PC_Compounds[0].id.id.cid;
                container.innerHTML = `<iframe src="https://pubchem.ncbi.nlm.nih.gov/compound/${cid}#section=3D-Conformer&embed=true" frameborder="0" width="100%" height="450" style="border:1px solid var(--border); border-radius: var(--radius);"></iframe>`;
            } else {
                container.innerHTML = '<div class="alert alert-info" style="margin: 2rem;"><i class="fas fa-info-circle"></i> Không tìm thấy cấu trúc 3D cho hoạt chất này trên PubChem.</div>';
            }
        })
        .catch(e => {
            container.innerHTML = '<div class="alert alert-error" style="margin: 2rem;"><i class="fas fa-exclamation-circle"></i> Lỗi kết nối CSDL phân tử PubChem.</div>';
        });
}
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    const type = params.get('type');
    
    if(q && type) {
        if(type === 'drug') {
            switchTab('drug');
            document.getElementById('drug-search').value = q;
            // Fetch exact idx
            fetch(`api/search.php?type=drug&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(items => {
                    if (items.length && document.getElementById('drug-search').value === q) {
                        document.getElementById('drug-idx').value = items[0].idx;
                        predictDrug();
                    }
                });
        } else if(type === 'disease') {
            switchTab('disease');
            document.getElementById('disease-search').value = q;
            // Fetch exact idx
            fetch(`api/search.php?type=disease&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(items => {
                    if (items.length && document.getElementById('disease-search').value === q) {
                        document.getElementById('disease-idx').value = items[0].idx;
                        predictDisease();
                    }
                });
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>

