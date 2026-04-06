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

    <!-- Results -->
    <div id="results-container" style="margin-top: 1.5rem;"></div>
</div>

<script>
function switchTab(mode) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + mode).classList.add('active');
    document.getElementById('panel-drug').style.display = mode === 'drug' ? 'block' : 'none';
    document.getElementById('panel-disease').style.display = mode === 'disease' ? 'block' : 'none';
    document.getElementById('results-container').innerHTML = '';
}

// AJAX Autocomplete for drugs
let drugTimer;
const drugSearch = document.getElementById('drug-search');
const drugAC = document.getElementById('drug-autocomplete');
drugSearch.addEventListener('input', function() {
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

// Close autocomplete on click outside
document.addEventListener('click', e => {
    if (!e.target.closest('#drug-search') && !e.target.closest('#drug-autocomplete')) drugAC.style.display = 'none';
    if (!e.target.closest('#disease-search') && !e.target.closest('#disease-autocomplete')) diseaseAC.style.display = 'none';
});

// Predict Drug -> Diseases
function predictDrug() {
    const idx = document.getElementById('drug-idx').value;
    const topk = document.getElementById('drug-topk').value;
    if (!idx && idx !== '0') { alert('Vui lòng chọn thuốc'); return; }
    
    const container = document.getElementById('results-container');
    container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Đang dự đoán bằng AI... (có thể mất vài giây)</p></div>';
    
    fetch('api/predict.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: 'drug_to_disease', drug_idx: parseInt(idx), top_k: parseInt(topk)})
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
            return;
        }
        renderResults(data.predictions, 'disease', data.query_name);
    })
    .catch(err => {
        container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Lỗi: ${err.message}</div>`;
    });
}

// Predict Disease -> Drugs
function predictDisease() {
    const idx = document.getElementById('disease-idx').value;
    const topk = document.getElementById('disease-topk').value;
    if (!idx && idx !== '0') { alert('Vui lòng chọn bệnh'); return; }
    
    const container = document.getElementById('results-container');
    container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Đang dự đoán bằng AI... (có thể mất vài giây)</p></div>';
    
    fetch('api/predict.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: 'disease_to_drug', disease_idx: parseInt(idx), top_k: parseInt(topk)})
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
            return;
        }
        renderResults(data.predictions, 'drug', data.query_name);
    })
    .catch(err => {
        container.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Lỗi: ${err.message}</div>`;
    });
}

function renderResults(predictions, type, queryName) {
    const container = document.getElementById('results-container');
    const typeLabel = type === 'disease' ? 'Bệnh' : 'Thuốc';
    
    let html = `
        <div class="card">
            <div class="card-header">
                <div class="card-icon purple"><i class="fas fa-chart-bar"></i></div>
                <div>
                    <div class="card-title">Kết quả dự đoán cho: ${queryName || 'N/A'}</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">${predictions.length} ${typeLabel.toLowerCase()} tiềm năng</div>
                </div>
            </div>
            <div class="results-list">
    `;
    
    predictions.forEach(p => {
        const name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
        const id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        const scorePct = Math.min(p.score * 100, 100).toFixed(1);
        const badge = p.is_known ? '<span class="result-badge badge-known">Đã biết</span>' : '<span class="result-badge badge-new">Mới</span>';
        
        html += `
            <div class="result-item">
                <div class="result-rank">${p.rank}</div>
                <div class="result-info">
                    <div class="result-name">${name}</div>
                    <div class="result-id">${id}</div>
                </div>
                <div class="result-score">
                    <div class="score-bar"><div class="score-fill" style="width: ${scorePct}%"></div></div>
                    <div class="score-value">${scorePct}%</div>
                </div>
                ${badge}
            </div>
        `;
    });
    
    html += '</div></div>';
    container.innerHTML = html;
}
</script>

<?php include 'includes/footer.php'; ?>
