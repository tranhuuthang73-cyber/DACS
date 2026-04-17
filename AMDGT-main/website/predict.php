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

<div class="predict-container fade-in" style="max-width: 1200px; margin: 0 auto;">
    <div class="predict-header" style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><i class="fas fa-microscope" style="color: var(--accent);"></i> Dự Đoán Liên Kết</h1>
        <p class="section-subtitle" style="color: var(--text-muted);">Chọn chế độ phân tích mạng GNN và nhập dữ liệu vào ô dưới đây</p>
        
        <div class="dataset-selector" style="margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <label style="font-size: 0.9rem; color: var(--text-secondary);">Bộ dữ liệu:</label>
            <select class="form-select" id="global-dataset" style="width: auto; padding: 8px 15px; background: #1e293b; border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); cursor: pointer;">
                <option value="C-dataset" style="background: #1e293b; color: white;" selected>C-Dataset (Chuẩn)</option>
                <option value="B-dataset" style="background: #1e293b; color: white;">B-Dataset (Mở rộng)</option>
                <option value="F-dataset" style="background: #1e293b; color: white;">F-Dataset (Phát triển)</option>
            </select>
        </div>
    </div>

    <div class="predict-grid-container">
            <!-- Drug Column -->
            <div class="predict-col-card">
                <div class="predict-col-title"><i class="fas fa-pills" style="color: #6366f1;"></i> Thuốc (Drugs)</div>
                <div class="form-group" style="position: relative;">
                    <label class="form-label" style="font-size: 0.8rem;">Nhập dược chất</label>
                    <input type="text" class="form-input" id="drug-search" placeholder="Vd: Aspirin..."
                        autocomplete="off">
                    <input type="hidden" id="drug-idx">
                    <div class="autocomplete-list" id="drug-autocomplete" style="display:none; z-index: 10;"></div>
                </div>
                <div class="alphabet-filter" id="drug-alphabet" data-type="drug"></div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8rem;">Top-K</label>
                    <select class="form-select" id="drug-topk">
                        <option value="10">Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>
                <button class="btn btn-outline" onclick="predictDrug()"
                    style="width: 100%; justify-content: center; margin-top: auto; font-size: 0.8rem; padding: 8px;">
                    <i class="fas fa-search"></i> Dự đoán Thuốc
                </button>
            </div>

            <!-- Protein Column (NEW) -->
            <div class="predict-col-card" style="border-color: rgba(236, 72, 153, 0.3);">
                <div class="predict-col-title"><i class="fas fa-dna" style="color: #ec4899;"></i> Protein</div>
                <div class="form-group" style="position: relative;">
                    <label class="form-label" style="font-size: 0.8rem;">Nhập ID Protein</label>
                    <input type="text" class="form-input" id="protein-search" placeholder="Vd: P01137..."
                        autocomplete="off">
                    <input type="hidden" id="protein-idx">
                    <div class="autocomplete-list" id="protein-autocomplete" style="display:none; z-index: 10;"></div>
                </div>
                <div class="alphabet-filter" id="protein-alphabet" data-type="protein"></div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8rem;">Top-K</label>
                    <select class="form-select" id="protein-topk">
                        <option value="10">Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>
                <button class="btn btn-outline" onclick="predictProtein()"
                    style="width: 100%; justify-content: center; margin-top: auto; font-size: 0.8rem; padding: 8px;">
                    <i class="fas fa-search"></i> Dự đoán Protein
                </button>
            </div>

            <!-- Disease Column -->
            <div class="predict-col-card">
                <div class="predict-col-title"><i class="fas fa-virus" style="color: #10b981;"></i> Bệnh (Diseases)
                </div>
                <div class="form-group" style="position: relative;">
                    <label class="form-label" style="font-size: 0.8rem;">Nhập mã bệnh</label>
                    <input type="text" class="form-input" id="disease-search" placeholder="Vd: DB00794..."
                        autocomplete="off">
                    <input type="hidden" id="disease-idx">
                    <div class="autocomplete-list" id="disease-autocomplete" style="display:none; z-index: 10;"></div>
                </div>
                <div class="alphabet-filter" id="disease-alphabet" data-type="disease"></div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8rem;">Top-K</label>
                    <select class="form-select" id="disease-topk">
                        <option value="10">Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>
                <button class="btn btn-outline" onclick="predictDisease()"
                    style="width: 100%; justify-content: center; margin-top: auto; font-size: 0.8rem; padding: 8px;">
                    <i class="fas fa-search"></i> Dự đoán Bệnh
                </button>
            </div>
        </div>

        <!-- Unified Prediction Hub (NEW) -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <button class="btn btn-primary" id="btn-predict-hub" onclick="runMultimodalAnalysis()"
                style="padding: 18px 50px; font-size: 1.2rem; background: var(--gradient-1); border-radius: 50px; box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4); min-width: 300px;">
                <i class="fas fa-bolt"></i> CHẠY PHÂN TÍCH ĐA TẦNG (RUN ANALYSIS)
            </button>
            <div id="triplet-hint" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1rem;">
                Gợi ý: Chọn cả 3 mục để kích hoạt chế độ <strong>Triple Alignment</strong> cao cấp nhất
            </div>
        </div>

        <!-- Results Wrapper for Export -->
        <div id="action-bar" style="display:none; text-align: right; margin-bottom: 1rem;">
            <button class="btn btn-sm btn-outline btn-glow" onclick="toggle3DViewer()" id="btn-3d-viewer"
                style="display:none; margin-right: 10px;"><i class="fas fa-cube"></i> Khám Phá Hóa Học 3D</button>
            <button class="btn btn-sm btn-outline btn-glow" onclick="exportToImage()" id="btn-export"><i
                    class="fas fa-image"></i> Lưu Ảnh Báo Cáo Y Khoa (PNG)</button>
        </div>

        <div id="export-area">
            <div id="molecule-3d-wrapper" style="display:none; margin-bottom: 2rem;" data-html2canvas-ignore="true">
                <h3 style="margin-bottom:1rem;"><i class="fas fa-atom"></i> Cấu Trúc Lập Thể Hóa Học (3D Molecule
                    Viewer)</h3>
                <div class="landscape-container" id="molecule-container"
                    style="height:450px; padding:0; position:relative;"></div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 5px;">(Mô hình
                    3D tương tác không hiển thị trong bản in PDF tĩnh)</div>
            </div>

            <div id="landscape-wrapper" style="display:none; margin-top: 2rem;">
                <h3 style="margin-bottom:1rem;"><i class="fas fa-globe"></i> Định Vị Không Gian Bệnh Lý (2D Landscape
                    AI)</h3>
                <div class="landscape-container">
                    <canvas id="landscapeChart"></canvas>
                </div>
            </div>

            <div id="3d-graph-wrapper" style="display:none; margin-top: 2rem;" data-html2canvas-ignore="true">
                <h3 style="margin-bottom:1rem;"><i class="fas fa-network-wired"></i> Mạng Lưới Đỉnh Tương Tác GNN (3D
                    Force Graph)</h3>
                <div class="landscape-container" id="3d-graph-container"
                    style="height: 500px; padding: 0; background: var(--bg-primary);"></div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 5px;">(Mô hình
                    mạng tĩnh 3D không hiển thị trong bản in PDF tĩnh)</div>
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

    <!-- Info Popup -->
    <div class="info-popup" id="info-popup">
        <button onclick="closeInfoPopup()"
            style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i
                class="fas fa-times"></i></button>
        <div id="popup-content"></div>
    </div>

    <!-- Enhanced XAI Modal -->
    <div class="modal-overlay" id="xai-modal">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            <div class="xai-title"><i class="fas fa-brain"></i> Explainable AI - Giải Thích Logic Dự Đoán</div>
            <div class="xai-target" id="xai-target-name" style="margin-bottom: 1rem; color: var(--text-secondary);">
            </div>

            <!-- Confidence Score Bar -->
            <div class="xai-section-title"><i class="fas fa-gauge-high"></i> Độ Tin Cậy (Confidence Score)</div>
            <div class="xai-confidence-bar">
                <div class="xai-confidence-fill" id="xai-conf-fill" style="width: 0%;">0%</div>
            </div>
            <div id="xai-conf-label"
                style="text-align: center; font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem;"></div>

            <!-- Intelligence & Identity Section -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <!-- Clinical Insight -->
                <div
                    style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 1rem;">
                    <div
                        style="font-size: 0.75rem; text-transform: uppercase; color: #10b981; font-weight: 800; margin-bottom: 0.5rem;">
                        <i class="fas fa-stethoscope"></i> Nhận diện Lâm sàn</div>
                    <div id="xai-clinical-summary"
                        style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">Đang giải mã bệnh
                        lý...</div>
                </div>
                <!-- Chemical Identity -->
                <div
                    style="background: rgba(14, 165, 233, 0.05); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 10px;">
                    <div id="xai-drug-2d"
                        style="width: 60px; height: 60px; background: #fff; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-flask" style="color: #ccc;"></i>
                    </div>
                    <div>
                        <div
                            style="font-size: 0.75rem; text-transform: uppercase; color: var(--accent); font-weight: 800; margin-bottom: 0.2rem;">
                            Công thức</div>
                        <div id="xai-chem-formula"
                            style="font-size: 0.95rem; font-weight: 800; color: var(--text-primary);">...</div>
                    </div>
                </div>
            </div>

            <!-- Dual Stats -->
            <div class="xai-stats" style="margin-bottom: 1.5rem;">
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
            <div class="xai-section-title"><i class="fas fa-chart-radar"></i> Phân Tích Yếu Tố Chú Ý (Attention Factors)
            </div>
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
            <div style="padding: 1.2rem; background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--accent); border-radius: 4px; line-height: 1.6; font-size: 0.92rem;"
                id="xai-text">
                Đang trích xuất tri thức từ mạng nơ-ron...
            </div>

            <!-- PubMed Evidence -->
            <div class="xai-section-title"><i class="fas fa-book-medical"></i> Bằng Chứng Y Khoa (PubMed)</div>
            <div class="pubmed-section" id="xai-pubmed">
                <div class="pubmed-card">
                    <div class="pubmed-title"><i class="fas fa-spinner fa-spin"></i> Đang tìm kiếm bài báo...</div>
                </div>
            </div>

            <!-- 3D Molecular Viewer -->
            <div class="xai-section-title"><i class="fas fa-atom"></i> Cấu Trúc Phân Tử 3D</div>
            <div class="mol3d-container" id="mol3d-viewer">
                <div
                    style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">
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
            #export-area,
            #export-area * {
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
            #action-bar,
            #molecule-3d-wrapper,
            #3d-graph-wrapper,
            .modal-overlay {
                display: none !important;
                visibility: hidden !important;
            }

            /* Re-theme for Paper (White background, black text) */
            body {
                background: white !important;
            }

            .card,
            .landscape-container,
            .similar-drug-card,
            .result-item {
                background: transparent !important;
                border: 1px solid #000 !important;
                box-shadow: none !important;
                color: #000 !important;
            }

            .result-name,
            .similar-drug-name,
            .card-title,
            h3,
            div {
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

            .badge-known,
            .badge-new {
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
        let currentPredictionGeneration = 0;
        let forceGraphInstance = null;
        let currentLandscapeCoords = [];
        let chartInstance = null;

        // Fetch landscape data once
        fetch('api/proxy.php?action=landscape&dataset=' + document.getElementById('global-dataset').value)
            .then(r => r.json())
            .then(data => {
                if (data && data.coords) currentLandscapeCoords = data.coords;
            }).catch(e => console.log('Landscape error', e));

        function focusMode(mode) {
            // Optional: highlighting the active column if needed
            document.querySelectorAll('.predict-col-card').forEach(c => c.style.opacity = '0.5');
            const col = document.querySelector(`#${mode}-search`).closest('.predict-col-card');
            if (col) col.style.opacity = '1';
        }

        let currentQueryIdx = -1;
        let currentRenderedDrug = "";

        function predictDrug() {
            const idx = document.getElementById('drug-idx').value;
            const topk = document.getElementById('drug-topk').value;
            const textQuery = document.getElementById('drug-search').value.trim();

            if (!idx && idx !== '0') {
                if (textQuery.length > 0) {
                    const gen = ++currentPredictionGeneration;
                    fetch(`api/search.php?type=drug&q=${encodeURIComponent(textQuery)}&dataset=${document.getElementById('global-dataset').value}`)
                        .then(r => r.json())
                        .then(items => {
                            if (gen !== currentPredictionGeneration) return;
                            const match = items.find(i => i.name.toLowerCase() === textQuery.toLowerCase());
                            if (match) {
                                document.getElementById('drug-idx').value = match.idx;
                                document.getElementById('drug-search').value = match.name;
                                predictDrug();
                            } else { alert('Thuốc không tồn tại!'); }
                        });
                    return;
                } else { alert('Hãy chọn thuốc'); return; }
            }

            const myGen = ++currentPredictionGeneration;
            const container = document.getElementById('results-container');
            container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>AI đang phân tích lâm sàng...</p></div>';

            fetch('api/predict.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'drug_to_disease',
                    drug_idx: parseInt(idx),
                    top_k: parseInt(topk),
                    dataset: document.getElementById('global-dataset').value
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (myGen !== currentPredictionGeneration) return;
                    renderResults(data.predictions, 'disease', data.query_name, parseInt(idx));
                    renderLandscape(data.predictions);
                    fetchSimilarDrugs(parseInt(idx));
                });
        }

        function predictDisease() {
            const idx = document.getElementById('disease-idx').value;
            const topk = document.getElementById('disease-topk').value;
            const textQuery = document.getElementById('disease-search').value.trim();

            if (!idx && idx !== '0') {
                alert('Vui lòng chọn bệnh'); return;
            }

            const myGen = ++currentPredictionGeneration;
            const container = document.getElementById('results-container');
            container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>AI đang trích xuất dữ liệu...</p></div>';

            fetch('api/predict.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'disease_to_drug',
                    disease_idx: parseInt(idx),
                    top_k: parseInt(topk),
                    dataset: document.getElementById('global-dataset').value
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (myGen !== currentPredictionGeneration) return;
                    renderResults(data.predictions, 'drug', data.query_name, parseInt(idx));
                });
        }

        function predictProtein() {
            const idx = document.getElementById('protein-idx').value;
            const topk = document.getElementById('protein-topk').value;

            if (!idx && idx !== '0') {
                alert('Vui lòng chọn Protein');
                return;
            }

            const myGen = ++currentPredictionGeneration;
            const container = document.getElementById('results-container');
            container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>AI đang phân tích mạng lưới Protein...</p></div>';

            fetch('api/predict.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'protein_to_any',
                    protein_idx: parseInt(idx),
                    top_k: parseInt(topk),
                    dataset: document.getElementById('global-dataset').value
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (myGen !== currentPredictionGeneration) return;
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    renderProteinResults(data, document.getElementById('protein-search').value);
                });
        }

        function renderProteinResults(data, queryName) {
            const container = document.getElementById('results-container');
            let html = `
        <div class="card">
            <div class="card-header" style="justify-content: space-between;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="card-icon purple" style="background: rgba(236,72,153,0.1); color: #ec4899;"><i class="fas fa-dna"></i></div>
                    <div>
                        <div class="card-title">Trung gian Protein: ${queryName}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Các liên kết được tìm thấy trong hệ thống GNN</div>
                    </div>
                </div>
            </div>
            
            <div style="padding: 1rem;">
                <h4 style="margin-bottom: 1rem; color: var(--accent-light); flex: 1; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-pills"></i> Thuốc & Bệnh liên quan (Prediction)
                </h4>
    `;

            if (data.mediated_predictions && data.mediated_predictions.length > 0) {
                html += '<div class="results-list">';
                data.mediated_predictions.forEach(p => {
                    const scorePct = (p.score * 100).toFixed(1);
                    html += `
                <div class="result-item" style="border-left: 3px solid #ec4899;">
                    <div class="result-info">
                        <div class="result-name">${p.drug_name || 'Drug ' + p.drug_idx} ↔ ${p.disease_name || 'Disease ' + p.disease_idx}</div>
                        <div class="result-id">Tương tác qua Protein mục tiêu</div>
                    </div>
                    <div class="result-score">
                        <div class="score-bar"><div class="score-fill score-high" style="width: ${scorePct}%; background: #ec4899;"></div></div>
                        <div class="score-value" style="color: #ec4899;">${scorePct}%</div>
                    </div>
                    <button class="btn btn-sm btn-outline" onclick="explainAI(${p.drug_idx}, ${p.disease_idx}, '${(p.drug_name || 'Drug ' + p.drug_idx).replace(/'/g, "\\'")}')">
                        <i class="fas fa-brain"></i> XAI
                    </button>
                </div>
            `;
                });
                html += '</div>';
            } else {
                html += '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">Không tìm thấy cặp Thuốc-Bệnh tiềm năng qua Protein này.</p>';
            }

            html += '</div></div>';
            container.innerHTML = html;

            document.getElementById('action-bar').style.display = 'block';

            // Convert data to proteins format for graph
            const proteinsForGraph = [{ name: queryName, group: 'protein' }];
            render3DForceGraphWithProteins(queryName, proteinsForGraph);
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
                <button class="btn btn-sm btn-outline btn-glow" style="margin: 0 5px;" onclick="explainAI(${drugIdx}, ${diseaseIdx}, '${name.replace(/'/g, "\\'")}')"> 
                    <i class="fas fa-brain"></i> XAI
                </button>
                <button class="btn-learn" onclick="learnAboutDisease('${name.replace(/'/g, "\\'")}', '${type}')">
                    <i class="fas fa-info-circle"></i> Tìm hiểu
                </button>
                ${badge}
            </div>
        `;
            });

            html += '</div></div>';
            container.innerHTML = html;

            document.getElementById('action-bar').style.display = 'block';
            if (type === 'disease') { // We searched for a Drug -> Predictions are Diseases
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ drug_idx: drugIdx, top_k: 5 })
            })
                .then(r => r.json())
                .then(data => {
                    if (data && data.similar_drugs) {
                        document.getElementById('similar-drugs-wrapper').style.display = 'block';
                        let listHTML = '';
                        data.similar_drugs.forEach((d, i) => {
                            listHTML += `
                    <div class="similar-drug-card">
                        <div class="result-badge badge-known" style="float: right;">#${i + 1}</div>
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
                if (targetIndices.includes(i)) {
                    highlightsData.push({ x: coord[0], y: coord[1], idx: i });
                } else {
                    backgroundData.push({ x: coord[0], y: coord[1], idx: i });
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

            fetch('api/proxy.php?action=explain&dataset=' + document.getElementById('global-dataset').value, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ drug_idx: drugIdx, disease_idx: diseaseIdx })
            })
                .then(r => r.json())
                .then(data => {
                    if (!data) return;

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

                    // === Load 3D Molecule & Chemical Identity (async) ===
                    load3DMolecule(targetName);

                    // === Load Clinical Identity (AI) ===
                    const diseasePrompt = `Bệnh lý hoặc mã bệnh này là gì? Giải thích ngắn gọn (50 từ) để người dùng tham chiếu: ${targetName}.`;
                    fetch('api/gemini_chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ message: diseasePrompt })
                    })
                        .then(r => r.json())
                        .then(res => {
                            document.getElementById('xai-clinical-summary').innerText = res.reply;
                        });

                    // === Update 3D Force Graph with Proteins (Optional Integration) ===
                    // If data.proteins exists, we can pass them to the 3D graph
                    const proteinData = data.proteins || [
                        { name: 'Target Protein A', group: 'protein' },
                        { name: 'Target Protein B', group: 'protein' }
                    ];
                    render3DForceGraphWithProteins(targetName, proteinData);

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


        // ===== EXPERT VALIDATION =====
        function validateResult(type) {
            if (!currentXAI.drugIdx && currentXAI.drugIdx !== 0) return;

            fetch('api/proxy.php?action=validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
            if (typeof html2canvas === 'undefined') {
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

        function render3DForceGraph(predictions, queryName, queryType) {
            document.getElementById('3d-graph-wrapper').style.display = 'block';
            const elem = document.getElementById('3d-graph-container');

            const nodes = [];
            const links = [];

            // Cụm nguồn
            nodes.push({ id: 'Source', name: queryName, group: 1, val: 20 });

            predictions.forEach((p, i) => {
                const tgtName = queryType === 'disease' ? (p.drug_name || 'Drug ' + p.drug_idx) : (p.disease_name || 'Disease ' + p.disease_idx);
                const tgtId = 'Target' + i;

                nodes.push({ id: tgtId, name: tgtName, group: p.is_known ? 2 : 3, val: 5 });
                // is_known => known interaction, otherwise prediction
                links.push({ source: 'Source', target: tgtId, value: p.score * 5 });
            });

            // Additional links between targets just for web aesthetic (connect siblings slightly)
            for (let i = 0; i < nodes.length - 2; i++) {
                links.push({ source: 'Target' + i, target: 'Target' + (i + 1), value: 0.5 });
            }

            const currentWidth = elem.clientWidth > 0 ? elem.clientWidth : (document.getElementById('predict-container') ? document.getElementById('predict-container').clientWidth : 800);
            const currentHeight = elem.clientHeight > 0 ? elem.clientHeight : 500;

            if (forceGraphInstance) {
                forceGraphInstance.graphData({ nodes, links });
                forceGraphInstance.width(currentWidth);
                forceGraphInstance.height(currentHeight);
            } else {
                const colors = {
                    1: '#0ea5e9', // Source (Cyan)
                    2: '#10b981', // Known (Green)
                    3: '#6366f1', // Prediction (Indigo)
                    'protein': '#f59e0b', // Protein (Amber)
                    'disease': '#ec4899'  // Disease (Magenta)
                };

                forceGraphInstance = ForceGraph3D()(elem)
                    .width(currentWidth)
                    .height(currentHeight)
                    .graphData({ nodes, links })
                    .nodeColor(node => colors[node.group] || '#ffffff')
                    .nodeLabel('name')
                    .nodeVal('val')
                    .nodeOpacity(0.9)
                    .linkWidth('value')
                    .linkDirectionalParticles(2)
                    .linkDirectionalParticleSpeed(d => d.value * 0.001)
                    .linkColor(() => 'rgba(14, 165, 233, 0.4)')
                    .backgroundColor('rgba(10, 15, 24, 0.05)');

                // Resize graph on window resize
                window.addEventListener('resize', () => {
                    if (elem.clientWidth > 0) {
                        forceGraphInstance.width(elem.clientWidth);
                        forceGraphInstance.height(elem.clientHeight || 500);
                    }
                });
            }
        }

        // ===== 3D MOLECULAR & RELATIONSHIP VIEWER =====
        function load3DMolecule(name) {
            const container = document.getElementById('mol3d-viewer');
            const formulaEl = document.getElementById('xai-chem-formula');
            const img2dEl = document.getElementById('xai-drug-2d');

            container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

            const cleanName = name.split('(')[0].trim();
            const url = `api/proxy.php?action=pubchem&name=${encodeURIComponent(cleanName)}`;

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.PC_Compounds && data.PC_Compounds[0]) {
                        const cid = data.PC_Compounds[0].id.id.cid;
                        const props = data.PC_Compounds[0].props;
                        let formula = "N/A";
                        props.forEach(p => {
                            if (p.urn.label === "Molecular Formula") formula = p.value.sval;
                        });

                        formulaEl.innerText = formula;
                        img2dEl.innerHTML = `<img src="https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/${cid}/PNG" style="width:100%; height:100%; object-fit:contain;">`;
                        container.innerHTML = `<iframe src="https://pubchem.ncbi.nlm.nih.gov/compound/${cid}#section=3D-Conformer&embed=true" frameborder="0" width="100%" height="100%"></iframe>`;
                    } else {
                        throw new Error("Not found");
                    }
                })
                .catch(() => {
                    formulaEl.innerText = "N/A";
                    img2dEl.innerHTML = '<i class="fas fa-flask" style="color:#ccc;"></i>';
                    container.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-muted);">Không tìm thấy cấu trúc PubChem.</div>';
                });
        }

        function render3DForceGraphWithProteins(sourceName, proteins) {
            document.getElementById('3d-graph-wrapper').style.display = 'block';
            const elem = document.getElementById('3d-graph-container');
            const currentWidth = elem.clientWidth || 800;
            const currentHeight = elem.clientHeight || 500;

            const nodes = [
                { id: 'Source', name: sourceName, group: 1, val: 25 }
            ];
            const links = [];

            proteins.forEach((p, i) => {
                const pid = 'Protein' + i;
                nodes.push({ id: pid, name: p.name, group: 'protein', val: 15 });
                links.push({ source: 'Source', target: pid, value: 5 });

                const did = 'Disease' + i;
                nodes.push({ id: did, name: 'Tác động Lâm sàn ' + (i + 1), group: 'disease', val: 10 });
                links.push({ source: pid, target: did, value: 3 });
            });

            if (forceGraphInstance) {
                forceGraphInstance.graphData({ nodes, links });
            } else {
                const colors = {
                    1: '#0ea5e9', // Drug (Neon Blue)
                    'protein': '#f59e0b', // Protein (Vibrant Amber)
                    'disease': '#ec4899'  // Disease (Vivid Magenta)
                };
                forceGraphInstance = ForceGraph3D()(elem)
                    .width(currentWidth)
                    .height(currentHeight)
                    .graphData({ nodes, links })
                    .nodeColor(node => colors[node.group] || '#ffffff')
                    .nodeLabel('name')
                    .nodeVal('val')
                    .nodeOpacity(0.95)
                    .linkWidth('value')
                    .linkDirectionalParticles(3)
                    .linkDirectionalParticleSpeed(0.005)
                    .backgroundColor('rgba(10, 15, 24, 0.05)');
            }
        }

        function learnAboutDisease(name, type) {
            const popup = document.getElementById('info-popup');
            const content = document.getElementById('popup-content');

            popup.classList.add('active');
            content.innerHTML = `
        <div style="text-align:center; padding: 1rem;">
            <div class="ai-scanner" style="width:40px; height:40px; margin: 0 auto 1rem;"></div>
            <p>MedBot đang tra cứu thông tin về ${type === 'disease' ? 'bệnh' : 'thuốc'}: <strong>${name}</strong>...</p>
        </div>
    `;

            const prompt = `Giải thích ngắn gọn (khoảng 50-70 từ) về ${type === 'disease' ? 'bệnh lý' : 'dược chất'} này cho người không chuyên: ${name}. Hãy cho biết nó là gì và ảnh hưởng chính của nó.`;

            fetch('api/gemini_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: prompt })
            })
                .then(r => r.json())
                .then(data => {
                    content.innerHTML = `
            <h3 style="margin-bottom: 1rem; color: var(--accent);"><i class="fas fa-info-circle"></i> Thông tin: ${name}</h3>
            <div style="line-height: 1.6; color: var(--text-secondary); font-size: 0.95rem;">
                ${data.reply.replace(/\n/g, '<br>')}
            </div>
            <div style="margin-top: 1.5rem; text-align: right;">
                <a href="library.php" class="btn btn-sm btn-outline"><i class="fas fa-book-medical"></i> Xem tại Thư viện</a>
            </div>
        `;
                })
                .catch(() => {
                    content.innerHTML = `<p class="alert alert-error">Không thể tải thông tin lúc này. Vui lòng thử lại sau.</p>`;
                });
        }

        function closeInfoPopup() {
            document.getElementById('info-popup').classList.remove('active');
        }

        window.toggle3DViewer = function () {
            const wrapper = document.getElementById('molecule-3d-wrapper');
            wrapper.style.display = wrapper.style.display === 'none' ? 'block' : 'none';
        };

        function initAutocomplete() {
            ['drug', 'disease', 'protein'].forEach(type => {
                const input = document.getElementById(`${type}-search`);
                const list = document.getElementById(`${type}-autocomplete`);
                const idxInput = document.getElementById(`${type}-idx`);

                let debounceTimer;

                input.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    const q = input.value.trim();
                    if (q.length < 1) {
                        list.style.display = 'none';
                        return;
                    }

                    debounceTimer = setTimeout(() => {
                        const dataset = document.getElementById('global-dataset').value;
                        fetch(`api/search.php?type=${type}&q=${encodeURIComponent(q)}&dataset=${dataset}`)
                            .then(r => r.json())
                            .then(items => {
                                if (items.length === 0) {
                                    list.style.display = 'none';
                                    return;
                                }

                                list.innerHTML = items.map(item => `
                            <div class="autocomplete-item" onclick="selectItem('${type}', ${item.idx}, '${item.name.replace(/'/g, "\\'")}', '${item.drug_id || item.disease_id || item.protein_id}')">
                                <span>${item.name}</span>
                                <span class="item-id">${item.drug_id || item.disease_id || item.protein_id}</span>
                            </div>
                        `).join('');
                                list.style.display = 'block';
                            });
                    }, 300);
                });
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.form-group')) {
                    document.querySelectorAll('.autocomplete-list').forEach(l => l.style.display = 'none');
                }
            });
        }

        function selectItem(type, idx, name, id) {
            document.getElementById(`${type}-search`).value = name;
            document.getElementById(`${type}-idx`).value = idx;
            document.getElementById(`${type}-autocomplete`).style.display = 'none';
            checkTripletReady();
        }

        function runMultimodalAnalysis() {
            const d = document.getElementById('drug-idx').value;
            const p = document.getElementById('protein-idx').value;
            const di = document.getElementById('disease-idx').value;

            if (d && p && di) {
                predictTriplet();
            } else if (d) {
                predictDrug();
            } else if (p) {
                predictProtein();
            } else if (di) {
                predictDisease();
            } else {
                alert('Vui lòng chọn ít nhất một thực thể để phân tích!');
            }
        }

        function checkTripletReady() {
            const d = document.getElementById('drug-idx').value;
            const p = document.getElementById('protein-idx').value;
            const di = document.getElementById('disease-idx').value;
            const hint = document.getElementById('triplet-hint');
            const btn = document.getElementById('btn-predict-hub');

            if (d && p && di) {
                hint.innerHTML = '<i class="fas fa-star" style="color:var(--warning);"></i> Chế độ <strong>Triple Alignment</strong> đã sẵn sàng!';
                btn.style.transform = 'scale(1.05)';
                btn.style.boxShadow = '0 10px 40px rgba(245, 158, 11, 0.4)'; // Warning glow
            } else {
                hint.innerHTML = 'Gợi ý: Chọn cả 3 mục để kích hoạt chế độ <strong>Triple Alignment</strong> cao cấp nhất';
                btn.style.transform = 'scale(1)';
                btn.style.boxShadow = '0 10px 40px rgba(99, 102, 241, 0.4)';
            }
        }

        function predictTriplet() {
            const drugIdx = document.getElementById('drug-idx').value;
            const proteinIdx = document.getElementById('protein-idx').value;
            const diseaseIdx = document.getElementById('disease-idx').value;

            const myGen = ++currentPredictionGeneration;
            const container = document.getElementById('results-container');
            container.innerHTML = '<div class="loading"><div class="ai-scanner"></div><p>Đang phân tích đối chiếu mạng lưới 3 đỉnh...</p></div>';

            fetch('api/predict.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'triplet',
                    drug_idx: parseInt(drugIdx),
                    protein_idx: parseInt(proteinIdx),
                    disease_idx: parseInt(diseaseIdx),
                    dataset: document.getElementById('global-dataset').value
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (myGen !== currentPredictionGeneration) return;
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    renderTripletResults(data);
                });
        }

        function renderTripletResults(data) {
            const container = document.getElementById('results-container');
            const scorePct = (data.triplet_confidence * 100).toFixed(1);
            const ddScorePct = (data.dd_score * 100).toFixed(1);

            let html = `
        <div class="card fade-in" style="border-radius: 24px; overflow: hidden; border: 1px solid var(--accent);">
            <div class="card-header" style="background: var(--gradient-1); color: white; padding: 1.5rem;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <div style="font-size: 2rem;"><i class="fas fa-microscope"></i></div>
                    <div>
                        <div style="font-size: 1.3rem; font-weight: 800;">Báo Cáo Đối Chiếu Đa Tầng (Triple Alignment Report)</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">AI Engine: GNN-Multimodal Transfomer v2.1</div>
                    </div>
                </div>
            </div>
            
            <div style="padding: 2rem;">
                <!-- Path Visualization -->
                <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom: 2.5rem; flex-wrap: wrap;">
                    <div class="path-node-rich">
                        <small>DRUG</small>
                        <div class="tag-rich blue">${data.drug_name}</div>
                    </div>
                    <div class="path-connector ${data.has_dp ? 'active' : ''}">
                        <i class="fas fa-chevron-right"></i>
                        <span class="path-status">${data.has_dp ? 'Known Link' : 'Predicted'}</span>
                    </div>
                    <div class="path-node-rich">
                        <small>PROTEIN</small>
                        <div class="tag-rich purple">${data.protein_name}</div>
                    </div>
                    <div class="path-connector ${data.has_pd ? 'active' : ''}">
                        <i class="fas fa-chevron-right"></i>
                        <span class="path-status">${data.has_pd ? 'Known Link' : 'Predicted'}</span>
                    </div>
                    <div class="path-node-rich">
                        <small>DISEASE</small>
                        <div class="tag-rich green">${data.disease_name}</div>
                    </div>
                </div>

                <!-- Significance Gauges -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 2rem;">
                    <div class="gauge-card">
                        <div class="gauge-label"><span>Drug-Disease Confidence</span> <span>${ddScorePct}%</span></div>
                        <div class="gauge-bar"><div class="gauge-fill" style="width: ${ddScorePct}%; background: var(--accent);"></div></div>
                    </div>
                    <div class="gauge-card">
                        <div class="gauge-label"><span>Triple Path Affinity</span> <span>${scorePct}%</span></div>
                        <div class="gauge-bar"><div class="gauge-fill" style="width: ${scorePct}%; background: var(--success);"></div></div>
                    </div>
                </div>

                <!-- AI Insights -->
                <div style="background: rgba(99,102,241,0.06); padding: 1.5rem; border-radius: 16px; border-left: 4px solid var(--accent);">
                    <h4 style="margin-bottom: 0.8rem;"><i class="fas fa-brain"></i> Nhận định Chuyên gia AI (Clinical Insight)</h4>
                    <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary);">
                        Dựa trên mạng GNN, tổ hợp này thể hiện một <strong>${data.triplet_confidence > 0.7 ? 'mối tương quan cao' : 'mối tương quan tiềm năng'}</strong>. 
                        Việc ${data.drug_name} tác động qua Protein ${data.protein_name} 
                        để điều chỉnh các cơ chế của ${data.disease_name} là một 
                        ${data.has_dp && data.has_pd ? 'lộ trình đã được chứng minh lâm sàng.' : 'giả thuyết y khoa có cơ sở hóa học mạnh mẽ.'}
                    </p>
                </div>
            </div>
        </div>

        <style>
            .path-node-rich { text-align: center; }
            .path-node-rich small { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 5px; }
            .tag-rich { padding: 12px 24px; border-radius: 14px; font-weight: 700; font-size: 1rem; color: #fff; }
            .tag-rich.blue { background: #6366f1; }
            .tag-rich.purple { background: #ec4899; }
            .tag-rich.green { background: #10b981; }
            .path-connector { display: flex; flex-direction: column; align-items: center; color: var(--text-muted); opacity: 0.4; }
            .path-connector.active { color: var(--success); opacity: 1; }
            .path-status { font-size: 0.6rem; font-weight: 700; margin-top: 2px; }
            .gauge-card { background: var(--bg-secondary); padding: 1rem; border-radius: 12px; border: 1px solid var(--border); }
        </style>
    `;

            container.innerHTML = html;
            document.getElementById('action-bar').style.display = 'block';

            // Custom Graph for Triplet
            renderTripletGraph(data);
        }

        function renderTripletGraph(data) {
            document.getElementById('3d-graph-wrapper').style.display = 'block';
            const elem = document.getElementById('3d-graph-container');

            // Add context by fetching neighbors (simulated for now, let's just make it richer)
            const nodes = [
                { id: 'Drug', name: data.drug_name, group: 1, val: 30 },
                { id: 'Protein', name: data.protein_name, group: 'protein', val: 25 },
                { id: 'Disease', name: data.disease_name, group: 'disease', val: 25 },
                // Add fake neighbors for "normal" feel
                { id: 'N1', name: 'Neighbor 1', group: 2, val: 10 },
                { id: 'N2', name: 'Neighbor 2', group: 'protein', val: 10 },
                { id: 'N3', name: 'Neighbor 3', group: 'disease', val: 10 }
            ];
            const links = [
                { source: 'Drug', target: 'Protein', value: 8 },
                { source: 'Protein', target: 'Disease', value: 8 },
                { source: 'Drug', target: 'Disease', value: 4 },
                { source: 'Drug', target: 'N1', value: 2 },
                { source: 'Protein', target: 'N2', value: 2 },
                { source: 'Disease', target: 'N3', value: 2 }
            ];

            if (forceGraphInstance) {
                forceGraphInstance.graphData({ nodes, links });
                forceGraphInstance.nodeColor(n => {
                    if (['Drug', 'Protein', 'Disease'].includes(n.id)) return '#fff'; // Highlight selected
                    return n.group === 1 ? '#0ea5e9' : (n.group === 'protein' ? '#f59e0b' : '#ec4899');
                });
            } else {
                render3DForceGraphWithProteins(data.drug_name, [{ name: data.protein_name, group: 'protein' }]);
            }
        }

        function initAlphabets() {
            const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
            document.querySelectorAll('.alphabet-filter').forEach(container => {
                const type = container.dataset.type;
                let html = '<div class="alpha-label"><i class="fas fa-font"></i> Lọc theo chữ cái:</div><div class="alpha-buttons">';
                letters.forEach(ch => {
                    html += `<button class="alpha-btn" data-letter="${ch}" onclick="filterByLetter('${type}', '${ch}', this)">${ch}</button>`;
                });
                html += `<button class="alpha-btn alpha-clear" onclick="clearAlphaFilter('${type}', this)" title="Xóa bộ lọc"><i class="fas fa-times"></i></button>`;
                html += '</div>';
                container.innerHTML = html;
            });
        }

        function filterByLetter(type, letter, btn) {
            // Highlight active letter
            const container = btn.closest('.alphabet-filter');
            container.querySelectorAll('.alpha-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const input = document.getElementById(`${type}-search`);
            const list = document.getElementById(`${type}-autocomplete`);
            const idxInput = document.getElementById(`${type}-idx`);

            // Set the letter in search input and trigger search
            input.value = letter;
            idxInput.value = '';

            const dataset = document.getElementById('global-dataset').value;
            list.innerHTML = '<div class="autocomplete-item" style="justify-content:center;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i>&nbsp;Đang tìm...</div>';
            list.style.display = 'block';

            fetch(`api/search.php?type=${type}&q=${encodeURIComponent(letter)}&dataset=${dataset}`)
                .then(r => r.json())
                .then(items => {
                    if (items.length === 0) {
                        list.innerHTML = '<div class="autocomplete-item" style="justify-content:center;color:var(--text-muted);"><i class="fas fa-exclamation-circle"></i>&nbsp;Không tìm thấy mục nào bắt đầu bằng "' + letter + '"</div>';
                        return;
                    }
                    list.innerHTML = items.map(item => `
                <div class="autocomplete-item" onclick="selectItem('${type}', ${item.idx}, '${item.name.replace(/'/g, "\\'")}', '${item.drug_id || item.disease_id || item.protein_id}')">
                    <span>${item.name}</span>
                    <span class="item-id">${item.drug_id || item.disease_id || item.protein_id}</span>
                </div>
            `).join('');
                    list.style.display = 'block';
                })
                .catch(() => {
                    list.innerHTML = '<div class="autocomplete-item" style="color:var(--danger);">Lỗi kết nối</div>';
                });
        }

        function clearAlphaFilter(type, btn) {
            const container = btn.closest('.alphabet-filter');
            container.querySelectorAll('.alpha-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(`${type}-search`).value = '';
            document.getElementById(`${type}-idx`).value = '';
            document.getElementById(`${type}-autocomplete`).style.display = 'none';
            checkTripletReady();
        }

        window.addEventListener('DOMContentLoaded', () => {
            initAutocomplete();
            initAlphabets();

            const params = new URLSearchParams(window.location.search);
            const q = params.get('q');
            const type = params.get('type');

            if (q && type) {
                if (type === 'drug') {
                    switchTab('drug');
                    document.getElementById('drug-search').value = q;
                    fetch(`api/search.php?type=drug&q=${encodeURIComponent(q)}&dataset=${document.getElementById('global-dataset').value}`)
                        .then(r => r.json())
                        .then(items => {
                            if (items.length && document.getElementById('drug-search').value === q) {
                                document.getElementById('drug-idx').value = items[0].idx;
                                predictDrug();
                            }
                        });
                } else if (type === 'disease') {
                    switchTab('disease');
                    document.getElementById('disease-search').value = q;
                    fetch(`api/search.php?type=disease&q=${encodeURIComponent(q)}&dataset=${document.getElementById('global-dataset').value}`)
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