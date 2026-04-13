<?php
$pageTitle = 'Dự đoán Hàng loạt';
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<div class="batch-container">
    <h1 class="page-title"><i class="fas fa-layer-group"></i> Dự Đoán Hàng Loạt (Batch Prediction)</h1>
    <p class="page-subtitle">Upload file CSV chứa danh sách cặp Thuốc-Bệnh, AI sẽ xử lý toàn bộ và trả kết quả.</p>

    <!-- Upload Zone -->
    <div class="upload-zone" id="upload-zone" onclick="document.getElementById('csv-file').click()">
        <i class="fas fa-cloud-arrow-up"></i>
        <h3>Kéo thả file CSV hoặc Click để chọn</h3>
        <p>Định dạng: <strong>drug_idx, disease_idx</strong> (mỗi dòng một cặp)</p>
        <p style="margin-top:0.5rem;"><a href="#" onclick="event.stopPropagation(); downloadTemplate();" style="color:var(--accent);">📥 Tải file mẫu (template.csv)</a></p>
        <input type="file" id="csv-file" accept=".csv" style="display:none" onchange="handleFileUpload(this)">
    </div>

    <!-- Progress -->
    <div class="batch-progress" id="batch-progress" style="display:none;">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
            <span style="color:var(--text-secondary);font-size:0.85rem;" id="batch-status">Đang xử lý...</span>
            <span style="color:var(--accent-light);font-weight:700;font-size:0.85rem;" id="batch-percent">0%</span>
        </div>
        <div class="batch-progress-bar">
            <div class="batch-progress-fill" id="batch-fill" style="width:0%;"></div>
        </div>
    </div>

    <!-- Results -->
    <div id="batch-results" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin:1.5rem 0 1rem;">
            <h2 style="color:var(--text-primary);font-size:1.2rem;"><i class="fas fa-table" style="color:var(--accent);"></i> Kết quả Dự đoán</h2>
            <button class="btn-pdf-export" onclick="downloadResults()">
                <i class="fas fa-download"></i> Tải CSV Kết quả
            </button>
        </div>
        <div style="overflow-x:auto;">
            <table class="batch-results-table" id="results-table">
                <thead>
                    <tr>
                        <th>#</th><th>Drug Idx</th><th>Disease Idx</th><th>Tên Thuốc</th><th>Tên Bệnh</th><th>Xác suất</th><th>Mức Tin Cậy</th>
                    </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
// drag & drop
const dropZone = document.getElementById('upload-zone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); handleFile(e.dataTransfer.files[0]); });

function downloadTemplate() {
    const csv = "drug_idx,disease_idx\n0,0\n1,5\n10,20\n50,100\n100,200";
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'batch_template.csv';
    a.click();
}

function handleFileUpload(input) { if (input.files[0]) handleFile(input.files[0]); }

let batchResultData = [];

function handleFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const lines = e.target.result.trim().split('\n');
        const pairs = [];
        for (let i = 1; i < lines.length; i++) { // skip header
            const parts = lines[i].split(',').map(s => parseInt(s.trim()));
            if (parts.length >= 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                pairs.push([parts[0], parts[1]]);
            }
        }
        if (pairs.length === 0) { alert('File CSV không hợp lệ!'); return; }
        processBatch(pairs);
    };
    reader.readAsText(file);
}

async function processBatch(pairs) {
    const progress = document.getElementById('batch-progress');
    const results = document.getElementById('batch-results');
    const fill = document.getElementById('batch-fill');
    const status = document.getElementById('batch-status');
    const pctEl = document.getElementById('batch-percent');
    const tbody = document.getElementById('results-body');

    progress.style.display = 'block';
    results.style.display = 'none';
    tbody.innerHTML = '';
    batchResultData = [];

    const batchSize = 10;
    const total = pairs.length;
    let processed = 0;

    for (let i = 0; i < pairs.length; i += batchSize) {
        const chunk = pairs.slice(i, i + batchSize);
        try {
            const resp = await fetch('api/proxy.php?action=predict', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ pairs: chunk })
            });
            const data = await resp.json();
            
            if (data.scores) {
                chunk.forEach((pair, j) => {
                    const prob = data.scores[j] || 0;
                    const pct = (prob * 100).toFixed(1);
                    let level = 'Thấp', cls = 'label-low';
                    if (prob >= 0.7) { level = 'Hiệu quả cao'; cls = 'label-high'; }
                    else if (prob >= 0.4) { level = 'Trung bình'; cls = 'label-medium'; }

                    batchResultData.push({ drug_idx: pair[0], disease_idx: pair[1], score: pct, level });
                    tbody.innerHTML += `<tr>
                        <td>${processed + j + 1}</td>
                        <td>${pair[0]}</td><td>${pair[1]}</td>
                        <td>Drug #${pair[0]}</td><td>Disease #${pair[1]}</td>
                        <td><strong>${pct}%</strong></td>
                        <td><span class="score-label ${cls}">${level}</span></td>
                    </tr>`;
                });
            }
        } catch(err) {
            chunk.forEach((pair, j) => {
                tbody.innerHTML += `<tr><td>${processed + j + 1}</td><td>${pair[0]}</td><td>${pair[1]}</td><td>-</td><td>-</td><td>Lỗi</td><td>-</td></tr>`;
            });
        }
        processed += chunk.length;
        const pct = Math.round(processed / total * 100);
        fill.style.width = pct + '%';
        pctEl.textContent = pct + '%';
        status.textContent = `Đã xử lý ${processed}/${total} cặp...`;
    }

    status.textContent = `✅ Hoàn thành! Đã phân tích ${total} cặp Thuốc-Bệnh.`;
    results.style.display = 'block';
}

function downloadResults() {
    let csv = 'drug_idx,disease_idx,score_percent,confidence_level\n';
    batchResultData.forEach(r => {
        csv += `${r.drug_idx},${r.disease_idx},${r.score},${r.level}\n`;
    });
    const blob = new Blob([csv], {type: 'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `batch_results_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}
</script>

<?php require_once 'includes/footer.php'; ?>
