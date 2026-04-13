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

<div class="predict-container fade-in">
    <div class="predict-header">
        <h1>🔬 Dự Đoán Liên Kết</h1>
        <p class="section-subtitle">Chọn chế độ dự đoán và nhập thuốc hoặc bệnh cần tra cứu</p>
    </div>

    <div class="tabs" id="mode-tabs">
        <button class="tab active" onclick="switchTab('drug')" id="tab-drug">
            <i class="fas fa-pills"></i> Thuốc → Bệnh
        </button>
        <button class="tab" onclick="switchTab('disease')" id="tab-disease">
            <i class="fas fa-virus"></i> Bệnh → Thuốc
        </button>
    </div>

    <!-- Drug to Disease -->
    <div class="card" id="panel-drug">
        <h3 style="margin-bottom: 1rem;">Nhập thuốc để tìm bệnh tiềm năng</h3>
        <div class="form-group" style="position: relative;">
            <label class="form-label">Chọn thuốc</label>
            <input type="text" class="form-input" id="drug-search" 
                   placeholder="Gõ tên thuốc... (vd: Aspirin, Caffeine)" autocomplete="off">
            <input type="hidden" id="drug-idx">
            <div class="autocomplete-list" id="drug-autocomplete" style="display:none;"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Số kết quả (Top-K)</label>
            <select class="form-select" id="drug-topk">
                <option value="5">Top 5</option>
                <option value="10" selected>Top 10</option>
                <option value="20">Top 20</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="predictDrug()" id="btn-predict-drug">
            <i class="fas fa-search"></i> Dự đoán
        </button>
    </div>

    <!-- Disease to Drug -->
    <div class="card" id="panel-disease" style="display:none;">
        <h3 style="margin-bottom: 1rem;">Nhập bệnh để tìm thuốc tiềm năng</h3>
        <div class="form-group" style="position: relative;">
            <label class="form-label">Chọn bệnh</label>
            <input type="text" class="form-input" id="disease-search" 
                   placeholder="Gõ mã bệnh... (vd: D102100)" autocomplete="off">
            <input type="hidden" id="disease-idx">
            <div class="autocomplete-list" id="disease-autocomplete" style="display:none;"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Số kết quả (Top-K)</label>
            <select class="form-select" id="disease-topk">
                <option value="5">Top 5</option>
                <option value="10" selected>Top 10</option>
                <option value="20">Top 20</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="predictDisease()" id="btn-predict-disease">
            <i class="fas fa-search"></i> Dự đoán
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

<!-- XAI Modal -->
<div class="modal-overlay" id="xai-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        <div class="xai-title"><i class="fas fa-brain"></i> Phân Tích Logic Của AI</div>
        <div class="xai-target" id="xai-target-name" style="margin-bottom: 1.5rem; color: var(--text-secondary);"></div>
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
        <div style="padding: 1.2rem; background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--accent); border-radius: 4px; line-height: 1.6; font-size: 0.95rem;" id="xai-text">
            Đang trích xuất tri thức từ mạng nơ-ron...
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
        
        html += `
            <div class="result-item fade-in">
                <div class="result-rank">${p.rank}</div>
                <div class="result-info">
                    <div class="result-name" style="color: var(--accent-light); font-weight: 700;">${name}</div>
                    <div class="result-id">${id}</div>
                </div>
                <div class="result-score">
                    <div class="score-bar"><div class="score-fill" style="width: ${scorePct}%"></div></div>
                    <div class="score-value">${scorePct}%</div>
                </div>
                <button class="btn btn-sm btn-outline btn-glow" style="margin: 0 10px;" onclick="explainAI(${drugIdx}, ${diseaseIdx}, '${name}')">
                    <i class="fas fa-brain"></i> Giải Thích
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

function explainAI(drugIdx, diseaseIdx, targetName) {
    const modal = document.getElementById('xai-modal');
    modal.classList.add('active');
    document.getElementById('xai-target-name').innerText = "Đối tượng phân tích: " + targetName;
    document.getElementById('xai-fsim').innerText = "...";
    document.getElementById('xai-tsim').innerText = "...";
    document.getElementById('xai-text').innerHTML = '<div style="display:flex;align-items:center;gap:10px;"><div class="ai-scanner" style="width:30px;height:30px;margin:0;"></div> <span>Đang mô phỏng tư duy AI...</span></div>';
    
    fetch('api/proxy.php?action=explain', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({drug_idx: drugIdx, disease_idx: diseaseIdx})
    })
    .then(r => r.json())
    .then(data => {
        if(data) {
            document.getElementById('xai-fsim').innerText = (data.feature_similarity * 100).toFixed(1) + "%";
            document.getElementById('xai-tsim').innerText = (data.topo_similarity * 100).toFixed(1) + "%";
            document.getElementById('xai-text').innerHTML = data.explanation_text || "Không thể phân tích logic lúc này.";
        }
    }).catch(e => {
        document.getElementById('xai-text').innerHTML = "Lỗi kết nối AI Engine.";
    });
}

function closeModal() {
    document.getElementById('xai-modal').classList.remove('active');
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

