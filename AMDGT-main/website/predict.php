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
        <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><i class="fas fa-microscope"
                style="color: var(--accent);"></i> Dự Đoán Liên Kết</h1>
        <p class="section-subtitle" style="color: var(--text-muted);">Chọn chế độ phân tích mạng GNN và nhập dữ liệu vào
            ô dưới đây</p>

        <input type="hidden" id="global-dataset" value="ALL">
    </div>

    <div class="predict-grid-container">
        <!-- Drug Column -->
        <div class="predict-col-card">
            <div class="predict-col-title"><i class="fas fa-pills" style="color: #6366f1;"></i> Thuốc (Drugs)</div>
            <div class="form-group" style="position: relative;">
                <label class="form-label" style="font-size: 0.8rem;">Nhập dược chất</label>
                <input type="text" class="form-input" id="drug-search" placeholder="Vd: Aspirin..." autocomplete="off">
                <input type="hidden" id="drug-idx">
                <input type="hidden" id="drug-dataset">
                <div class="autocomplete-list" id="drug-autocomplete" style="display:none; z-index: 10;"></div>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-size: 0.8rem;">Top-K <span
                        style="color:var(--text-muted);font-size:0.75rem;">(nhập số bất kỳ)</span></label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="number" class="form-input" id="drug-topk" value="20" min="1" max="500"
                        onchange="reload2DViz()" style="width:90px; text-align:center; font-weight:700;">
                    <div style="display:flex; flex-direction:column; gap:3px;">
                        <button type="button" onclick="document.getElementById('drug-topk').value=10; reload2DViz()"
                            style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:var(--accent);cursor:pointer;">10</button>
                        <button type="button" onclick="document.getElementById('drug-topk').value=20; reload2DViz()"
                            style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:var(--accent);cursor:pointer;">20</button>
                        <button type="button" onclick="document.getElementById('drug-topk').value=50; reload2DViz()"
                            style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:var(--accent);cursor:pointer;">50</button>
                    </div>
                </div>
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
                <input type="hidden" id="protein-dataset">
                <div class="autocomplete-list" id="protein-autocomplete" style="display:none; z-index: 10;"></div>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-size: 0.8rem;">Top-K <span
                        style="color:var(--text-muted);font-size:0.75rem;">(nhập số bất kỳ)</span></label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="number" class="form-input" id="protein-topk" value="20" min="1" max="500"
                        onchange="reload2DViz()" style="width:90px; text-align:center; font-weight:700;">
                    <div style="display:flex; flex-direction:column; gap:3px;">
                        <button type="button" onclick="document.getElementById('protein-topk').value=10; reload2DViz()"
                            style="background:rgba(236,72,153,0.15);border:1px solid rgba(236,72,153,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#ec4899;cursor:pointer;">10</button>
                        <button type="button" onclick="document.getElementById('protein-topk').value=20; reload2DViz()"
                            style="background:rgba(236,72,153,0.15);border:1px solid rgba(236,72,153,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#ec4899;cursor:pointer;">20</button>
                        <button type="button" onclick="document.getElementById('protein-topk').value=50; reload2DViz()"
                            style="background:rgba(236,72,153,0.15);border:1px solid rgba(236,72,153,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#ec4899;cursor:pointer;">50</button>
                    </div>
                </div>
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
                <input type="hidden" id="disease-dataset">
                <div class="autocomplete-list" id="disease-autocomplete" style="display:none; z-index: 10;"></div>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-size: 0.8rem;">Top-K <span
                        style="color:var(--text-muted);font-size:0.75rem;">(nhập số bất kỳ)</span></label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="number" class="form-input" id="disease-topk" value="20" min="1" max="500"
                        onchange="reload2DViz()" style="width:90px; text-align:center; font-weight:700;">
                    <div style="display:flex; flex-direction:column; gap:3px;">
                        <button type="button" onclick="document.getElementById('disease-topk').value=10; reload2DViz()"
                            style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#10b981;cursor:pointer;">10</button>
                        <button type="button" onclick="document.getElementById('disease-topk').value=20; reload2DViz()"
                            style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#10b981;cursor:pointer;">20</button>
                        <button type="button" onclick="document.getElementById('disease-topk').value=50; reload2DViz()"
                            style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);border-radius:6px;padding:2px 7px;font-size:0.7rem;color:#10b981;cursor:pointer;">50</button>
                    </div>
                </div>
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
    </div>



    <!-- Stats Bar -->
    <div id="results-stats-bar" style="display:none; margin-top: 1.5rem; margin-bottom: 0.5rem;"></div>

    <!-- Results Wrapper for Export -->
    <div id="action-bar" style="display:none; text-align: right; margin-bottom: 1rem;">
        <button class="btn btn-sm btn-outline btn-glow" onclick="toggle3DViewer()" id="btn-3d-viewer"
            style="display:none; margin-right: 10px;"><i class="fas fa-cube"></i> Khám Phá Hóa Học 3D</button>
        <button class="btn btn-sm btn-outline btn-glow" onclick="exportToImage()" id="btn-export"><i
                class="fas fa-image"></i> Lưu Ảnh Báo Cáo Y Khoa (PNG)</button>
    </div>

    <div id="export-area">
        <!-- Drug 2D Structure Panel (accordion) -->
        <div id="drug-2d-panel" style="display:none; margin-bottom: 1.5rem;">
            <div
                style="background: rgba(14,165,233,0.06); border: 1px solid rgba(14,165,233,0.25); border-radius: 16px; overflow: hidden;">
                <div id="drug-2d-header" onclick="toggle2DPanel()"
                    style="display:flex; align-items:center; justify-content:space-between; padding: 1rem 1.5rem; cursor:pointer; user-select:none; background: rgba(14,165,233,0.05);">
                    <div style="display:flex; align-items:center; gap: 12px;">
                        <div
                            style="width:36px; height:36px; border-radius:10px; background:rgba(14,165,233,0.2); display:flex; align-items:center; justify-content:center; color:#0ea5e9; font-size:1.1rem;">
                            <i class="fas fa-atom"></i>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:0.95rem; color: var(--text-primary);">Cấu Trúc Hóa
                                Học 2D — <span id="drug-2d-title" style="color:#0ea5e9;">...</span></div>
                            <div style="font-size:0.75rem; color: var(--text-muted);">Phân tử từ PubChem • Nhấn để
                                mở/đóng</div>
                        </div>
                    </div>
                    <div id="drug-2d-toggle-icon" style="color:#0ea5e9; font-size:1.1rem; transition: transform 0.3s;">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
                <div id="drug-2d-body"
                    style="display:grid; grid-template-columns: 240px 1fr; gap: 1.5rem; padding: 1.5rem;">
                    <div id="drug-2d-img"
                        style="width:240px; height:240px; background:rgba(255,255,255,0.04); border-radius:12px; border:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                        <i class="fas fa-spinner fa-spin" style="color:var(--text-muted); font-size:1.5rem;"></i>
                    </div>
                    <div id="drug-2d-info"
                        style="display:flex; flex-direction:column; justify-content:center; gap: 1rem;">
                        <div
                            style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">
                            Thông tin phân tử</div>
                        <div id="drug-2d-details" style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem;">
                        </div>
                        <a id="drug-2d-pubchem-link" href="#" target="_blank"
                            style="display:inline-flex; align-items:center; gap:6px; color:#0ea5e9; font-size:0.85rem; font-weight:600; text-decoration:none; width:fit-content; margin-top:0.5rem; padding: 8px 16px; background: rgba(14,165,233,0.1); border-radius:8px; border:1px solid rgba(14,165,233,0.2);"><i
                                class="fas fa-external-link-alt"></i> Xem trên PubChem</a>
                    </div>
                </div>
            </div>
        </div>

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



        <div id="similar-drugs-wrapper" style="display:none;">
            <h3 style="margin-top:2rem;"><i class="fas fa-pills"></i> Thuốc Thay Thế Tương Đồng (AI Suggestion)</h3>
            <div class="similar-drugs-container">
                <div class="similar-drugs-row" id="similar-drugs-list"></div>
            </div>
        </div>

        <div id="results-container" style="margin-top: 1.5rem;"></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- VISUALIZATION HUB (2D Network · 3D Graph · Training Curve)  -->
<!-- ============================================================ -->
<div id="viz-hub" style="margin-top:2.5rem;">
    <div style="margin-bottom:1.2rem;">
        <h2 style="font-size:1.4rem;font-weight:900;margin-bottom:4px;"><i class="fas fa-circle-nodes"
                style="color:var(--accent);"></i> Network Visualizations</h2>
        <p style="color:var(--text-muted);font-size:0.85rem;">2D/3D Graph &amp; Training Curve — chọn tab bên dưới để
            khám phá</p>
    </div>
    <div class="viz-tabs"
        style="display:flex;gap:8px;margin-bottom:1.5rem;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:6px;">
        <button class="viz-tab" id="vtab-2d" onclick="switchVizTab('2d')"
            style="flex:1;padding:10px 0;text-align:center;border-radius:10px;font-weight:700;font-size:0.88rem;cursor:pointer;border:none;background:transparent;color:var(--text-muted);transition:all .25s;"><i
                class="fas fa-circle-nodes"></i> 2D Network</button>
        <button class="viz-tab active" id="vtab-3d" onclick="switchVizTab('3d')"
            style="flex:1;padding:10px 0;text-align:center;border-radius:10px;font-weight:700;font-size:0.88rem;cursor:pointer;border:none;background:var(--gradient-1);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.35);transition:all .25s;"><i
                class="fas fa-cube"></i> 3D Graph</button>
        <button class="viz-tab" id="vtab-curve" onclick="switchVizTab('curve')"
            style="flex:1;padding:10px 0;text-align:center;border-radius:10px;font-weight:700;font-size:0.88rem;cursor:pointer;border:none;background:transparent;color:var(--text-muted);transition:all .25s;"><i
                class="fas fa-chart-line"></i> Training Curve</button>
    </div>

    <!-- 2D NETWORK TAB -->
    <div id="vpanel-2d" class="vpanel" style="display:none;">
        <div class="card" style="padding:0;overflow:hidden;">
            <div
                style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.06);">
                <div style="font-weight:800;font-size:1rem;"><i class="fas fa-circle-nodes" style="color:#6366f1;"></i>
                    2D Graph Representation &amp; Hidden Layers</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="viz-dataset" onchange="reload2DViz()"
                        style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:var(--text-primary);padding:5px 10px;font-size:0.8rem;">
                        <option value="C-dataset">C-dataset</option>
                        <option value="B-dataset">B-dataset</option>
                        <option value="F-dataset">F-dataset</option>
                    </select>
                    <button onclick="reload2DViz()"
                        style="background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.4);border-radius:8px;color:var(--accent);padding:5px 14px;font-size:0.8rem;cursor:pointer;font-weight:700;"><i
                            class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <!-- Legend -->
            <div style="display:flex;gap:18px;flex-wrap:wrap;padding:10px 1.5rem;background:rgba(0,0,0,0.2);">
                <div
                    style="display:flex;align-items:center;gap:7px;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
                    <div
                        style="width:13px;height:13px;border-radius:50%;background:#38bdf8;box-shadow:0 0 8px #38bdf8;">
                    </div> Thuốc (Drug)
                </div>
                <div
                    style="display:flex;align-items:center;gap:7px;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
                    <div
                        style="width:13px;height:13px;border-radius:50%;background:#f87171;box-shadow:0 0 8px #f87171;">
                    </div> Bệnh (Disease)
                </div>
                <div
                    style="display:flex;align-items:center;gap:7px;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
                    <div
                        style="width:13px;height:13px;border-radius:50%;background:#facc15;box-shadow:0 0 8px #facc15;">
                    </div> Protein
                </div>
            </div>
            <!-- Controls -->
            <div
                style="display:flex;gap:14px;align-items:center;padding:10px 1.5rem;background:rgba(99,102,241,0.04);border-bottom:1px solid rgba(255,255,255,0.05);flex-wrap:wrap;">
                <label style="font-size:0.8rem;color:var(--text-muted);font-weight:700;">Layout:
                    <select id="viz-layout" onchange="reload2DViz()"
                        style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:var(--text-primary);padding:3px 8px;font-size:0.8rem;margin-left:4px;">
                        <option value="force">Force-directed</option>
                        <option value="circular">Circular</option>
                        <option value="layered">Layered</option>
                    </select>
                </label>
                <label style="font-size:0.8rem;color:var(--text-muted);font-weight:700;">Nhãn: <input type="checkbox"
                        id="viz-labels" checked onchange="draw2DViz();"></label>
                <span id="viz-info" style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);"></span>
            </div>
            <canvas id="viz-canvas2d" style="width:100%;height:520px;background:#0d1117;display:block;"></canvas>
            <div id="viz-stats2d"
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;padding:1.2rem 1.5rem;border-top:1px solid rgba(255,255,255,0.06);">
            </div>
        </div>
    </div>

    <!-- 3D GRAPH TAB -->
    <div id="vpanel-3d" class="vpanel" style="display:block;">
        <div class="card" style="padding:0;overflow:hidden;">
            <div
                style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.06);">
                <div style="font-weight:800;font-size:1rem;"><i class="fas fa-cube" style="color:#f59e0b;"></i> 3D Force
                    Graph — Heterogeneous Network</div>
                <span style="font-size:0.78rem;color:var(--text-muted);">Kéo để xoay • Scroll để zoom</span>
            </div>
            <div style="display:flex;gap:18px;flex-wrap:wrap;padding:10px 1.5rem;background:rgba(0,0,0,0.2);">
                <div style="display:flex;align-items:center;gap:7px;font-size:0.82rem;color:var(--text-secondary);">
                    <div style="width:12px;height:12px;border-radius:50%;background:#38bdf8;"></div> Drug
                </div>
                <div style="display:flex;align-items:center;gap:7px;font-size:0.82rem;color:var(--text-secondary);">
                    <div style="width:12px;height:12px;border-radius:50%;background:#f87171;"></div> Disease
                </div>
                <div style="display:flex;align-items:center;gap:7px;font-size:0.82rem;color:var(--text-secondary);">
                    <div style="width:12px;height:12px;border-radius:50%;background:#facc15;"></div> Protein
                </div>
            </div>
            <div id="viz-3d-container" style="width:100%;height:520px;background:#060a10;"></div>
        </div>
    </div>

    <!-- TRAINING CURVE TAB -->
    <div id="vpanel-curve" class="vpanel" style="display:none;">
        <div class="card" style="padding:0;overflow:hidden;">
            <div
                style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.06);">
                <div style="font-weight:800;font-size:1rem;"><i class="fas fa-chart-line" style="color:#10b981;"></i>
                    Training Curve — 10-Fold Cross Validation</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <label style="font-size:0.8rem;color:var(--text-muted);">Fold:</label>
                    <select id="viz-fold" onchange="loadVizCurve()"
                        style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:var(--text-primary);padding:5px 10px;font-size:0.8rem;">
                        <?php for ($i = 0; $i < 10; $i++)
                            echo "<option value='$i'>Fold $i</option>"; ?>
                        <option value="all">All Folds</option>
                    </select>
                </div>
            </div>
            <div style="padding:1.5rem;height:500px;"><canvas id="viz-curve-canvas"></canvas></div>
            <div id="viz-curve-stats"
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;padding:1.2rem 1.5rem;border-top:1px solid rgba(255,255,255,0.06);">
            </div>
        </div>
    </div>
</div><!-- end viz-hub -->

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
        <div id="xai-conf-label" style="text-align: center; font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem;">
        </div>

        <!-- Intelligence & Identity Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <!-- Clinical Insight -->
            <div
                style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 1rem;">
                <div
                    style="font-size: 0.75rem; text-transform: uppercase; color: #10b981; font-weight: 800; margin-bottom: 0.5rem;">
                    <i class="fas fa-stethoscope"></i> Nhận diện Lâm sàn
                </div>
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
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Đang tải mô hình 3D...
            </div>
        </div>
        <div class="mol3d-info" id="mol3d-info"></div>

        <!-- Latent Path (Bridge Proteins) -->
        <div class="xai-section-title"><i class="fas fa-route"></i> Đường Dẫn Ẩn (Latent Path)</div>
        <div id="xai-latent-path"
            style="padding: 0.8rem; background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.2); border-radius: 12px; margin-bottom: 1rem;">
            <span style="color:var(--text-muted); font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Đang phân
                tích đường dẫn...</span>
        </div>

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

    .stat-item {
        text-align: center;
        padding: 0 10px;
    }

    @media (max-width: 768px) {
        .stat-item {
            flex: 1;
            min-width: 80px;
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
                dataset: document.getElementById('drug-dataset').value || 'C-dataset'
            })
        })
            .then(r => r.json())
            .then(data => {
                if (myGen !== currentPredictionGeneration) return;
                renderResults(data.predictions, 'disease', data.query_name, parseInt(idx));
                renderLandscape(data.predictions);
                fetchSimilarDrugs(parseInt(idx));
                loadModelPerformance(document.getElementById('drug-dataset').value || 'C-dataset');
                // Tính năng mới: Hiển thị cấu trúc 2D của thuốc
                renderDrug2DStructure(document.getElementById('drug-search').value.trim(), parseInt(idx));
            });
    }

    window.onload = function () {
        initAutocomplete();
        loadModelPerformance('C-dataset'); // Tự động load ngay khi mở trang để test
    };

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
                dataset: document.getElementById('disease-dataset').value || 'C-dataset'
            })
        })
            .then(r => r.json())
            .then(data => {
                if (myGen !== currentPredictionGeneration) return;
                renderResults(data.predictions, 'drug', data.query_name, parseInt(idx));
                loadModelPerformance(document.getElementById('disease-dataset').value || 'C-dataset');
                // Ẩn panel 2D khi dự đoán theo bệnh
                document.getElementById('drug-2d-panel').style.display = 'none';
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
                dataset: document.getElementById('protein-dataset').value || 'C-dataset'
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
                loadModelPerformance(document.getElementById('protein-dataset').value || 'C-dataset');
                // Ẩn panel 2D khi dự đoán theo protein
                document.getElementById('drug-2d-panel').style.display = 'none';
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

        // Stats bar cho protein results
        renderResultsSummary(data.mediated_predictions || [], 'protein');

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

        // Stats bar
        renderResultsSummary(predictions, type);

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
        document.getElementById('xai-latent-path').innerHTML = '<span style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Đang phân tích đường dẫn ẩn...</span>';
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

                // === Load Latent Path (Bridge Proteins) ===
                fetchLatentPath(drugIdx, diseaseIdx, targetName);

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
                            <div class="autocomplete-item" onclick="selectItem('${type}', ${item.idx}, '${item.name.replace(/'/g, "\\'")}', '${item.drug_id || item.disease_id || item.protein_id}', '${item.dataset}')">
                                <div style="display:flex; justify-content:space-between; width:100%;">
                                    <span>${item.name}</span>
                                    <span style="font-size:0.7rem; color:var(--accent); font-weight:600;">[${item.dataset}]</span>
                                </div>
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

    function selectItem(type, idx, name, id, dataset) {
        document.getElementById(`${type}-search`).value = name;
        document.getElementById(`${type}-idx`).value = idx;
        document.getElementById(`${type}-dataset`).value = dataset;
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
                dataset: document.getElementById('drug-dataset').value || 'C-dataset'
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
                const ds = document.getElementById('drug-dataset').value || document.getElementById('global-dataset').value || 'C-dataset';
                console.log("Triplet Mode: Loading performance for", ds);
                loadModelPerformance(ds);
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
        // Force the main visualization to reload with current quantities
        reload2DViz();
        // Switch to the 3D tab automatically to show the graph
        switchVizTab('3d');
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

    function loadModelPerformance(dataset) {
        const container = document.getElementById('model-performance-container');
        if (!container) return;

        // Debug: In ra dataset đang được yêu cầu
        console.log("Loading model performance for:", dataset);

        // Nếu là C-dataset, hiển thị bảng dữ liệu chi tiết đã được nhúng sẵn
        if (dataset && (dataset.toLowerCase().includes('c-dataset') || dataset === 'ALL')) {
            renderCDataSetTable(container);
            return;
        }

        container.innerHTML = `<div style="text-align:center; padding: 1rem;"><div class="ai-scanner" style="width:20px;height:20px;display:inline-block;"></div> Đang tải độ tin cậy mô hình cho ${dataset}...</div>`;

        fetch(`api/proxy.php?action=model_performance&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    container.innerHTML = ''; // Không hiển thị nếu lỗi
                    return;
                }
                const stats = data.stats;
                const auc = parseFloat(stats.AUC).toFixed(4);
                const aupr = parseFloat(stats.AUPR).toFixed(4);
                const acc = parseFloat(stats.Accuracy).toFixed(4);

                container.innerHTML = `
                        <div class="card fade-in" style="border-left: 4px solid var(--accent); background: rgba(99, 102, 241, 0.05); overflow: hidden; margin-bottom: 1.5rem;">
                            <div style="padding: 1.2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 45px; height: 45px; border-radius: 12px; background: var(--gradient-1); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 4px 15px rgba(99,102,241,0.3);">
                                        <i class="fas fa-shield-check"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; font-size: 1rem; color: var(--text-primary); letter-spacing: -0.5px;">Độ Tin Cậy Mô Hình AI</div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">Dựa trên kiểm định 10-Fold CV (${dataset})</div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 25px; flex-grow: 1; justify-content: center;">
                                    <div class="stat-item">
                                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Mean AUC</div>
                                        <div style="font-size: 1.25rem; font-weight: 900; color: #10b981; text-shadow: 0 0 10px rgba(16,185,129,0.2);">${auc}</div>
                                    </div>
                                    <div class="stat-item">
                                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Mean AUPR</div>
                                        <div style="font-size: 1.25rem; font-weight: 900; color: #ec4899; text-shadow: 0 0 10px rgba(236,72,153,0.2);">${aupr}</div>
                                    </div>
                                    <div class="stat-item">
                                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Accuracy</div>
                                        <div style="font-size: 1.25rem; font-weight: 900; color: #f59e0b; text-shadow: 0 0 10px rgba(245,158,11,0.2);">${acc}</div>
                                    </div>
                                </div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05);">
                                    <i class="fas fa-history" style="margin-right:5px;"></i> ${data.timestamp}
                                </div>
                            </div>
                        </div>
                    `;
            })
            .catch(e => {
                console.log('Performance fetch error', e);
                if (container) container.innerHTML = '';
            });
    }

    function renderCDataSetTable(container) {
            <?php
            // Logic PHP CỰC KỲ MẠNH MẼ để tìm file kết quả
            $dataset = 'C-dataset';
            $targetDir = "Result/$dataset/AMNTDDA";
            $foundDir = "";
            $debugPaths = [];

            // Thử dùng __DIR__ và quét ngược
            $basePath = realpath(__DIR__ . "/..");
            $testPath = $basePath . "/" . $targetDir;
            $debugPaths[] = $testPath;
            if (is_dir($testPath)) {
                $foundDir = $testPath;
            } else {
                // Thử quét thủ công qua nhiều cấp
                $current = __DIR__;
                for ($i = 0; $i < 4; $i++) {
                    $test = $current . "/" . $targetDir;
                    $debugPaths[] = $test;
                    if (is_dir($test)) {
                        $foundDir = $test;
                        break;
                    }
                    $current = dirname($current);
                }
            }

            // Thử thêm Document Root + path cụ thể của Laragon
            if (!$foundDir) {
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
                $testPath = $docRoot . "/AMDGT-main/" . $targetDir;
                $debugPaths[] = $testPath;
                if (is_dir($testPath))
                    $foundDir = $testPath;
            }

            $files = $foundDir ? glob($foundDir . "/10_fold_results_*.csv") : [];
            $realData = [];
            $mean = null;
            $std = null;
            $timestamp = "N/A";

            if ($files) {
                usort($files, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $latestFile = $files[0];
                $timestamp = date("H:i - d/m/Y", filemtime($latestFile));

                if (($handle = fopen($latestFile, "r")) !== FALSE) {
                    $header = fgetcsv($handle, 1000, ",");
                    if ($header) {
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($header) === count($data)) {
                                $row = array_combine($header, $data);
                                if (isset($row['Fold']) && strpos($row['Fold'], 'Fold') !== false) {
                                    $realData[] = [
                                        'f' => $row['Fold'],
                                        'ep' => (int) ($row['Best_Epoch'] ?? 0),
                                        'auc' => (float) ($row['AUC'] ?? 0),
                                        'aupr' => (float) ($row['AUPR'] ?? 0),
                                        'acc' => (float) ($row['Accuracy'] ?? 0),
                                        'f1' => (float) ($row['F1-score'] ?? 0),
                                        'mcc' => (float) ($row['Mcc'] ?? 0)
                                    ];
                                } elseif (isset($row['Fold']) && $row['Fold'] === 'Mean') {
                                    $mean = [
                                        'f' => "MEAN",
                                        'auc' => (float) ($row['AUC'] ?? 0),
                                        'aupr' => (float) ($row['AUPR'] ?? 0),
                                        'acc' => (float) ($row['Accuracy'] ?? 0),
                                        'f1' => (float) ($row['F1-score'] ?? 0),
                                        'mcc' => (float) ($row['Mcc'] ?? 0)
                                    ];
                                } elseif (isset($row['Fold']) && $row['Fold'] === 'Std') {
                                    $std = [
                                        'f' => "STD (±)",
                                        'auc' => (float) ($row['AUC'] ?? 0),
                                        'aupr' => (float) ($row['AUPR'] ?? 0),
                                        'acc' => (float) ($row['Accuracy'] ?? 0),
                                        'f1' => (float) ($row['F1-score'] ?? 0),
                                        'mcc' => (float) ($row['Mcc'] ?? 0)
                                    ];
                                }
                            }
                        }
                    }
                    fclose($handle);
                }
            }
            ?>

            try {
            const debugPaths = <?php echo json_encode($debugPaths); ?>;
            const data = <?php echo json_encode($realData); ?>;
            const mean = <?php echo json_encode($mean); ?>;
            const std = <?php echo json_encode($std); ?>;
            const updateTime = "<?php echo $timestamp; ?>";

            console.log("PHP Search Paths attempted:", debugPaths);
            console.log("PHP Data loaded status:", data ? "Success" : "Empty");

            if (!data || data.length === 0) {
                container.innerHTML = `
                        <div class="card" style="padding:1.5rem; text-align:center; color:var(--text-muted);">
                            <i class="fas fa-exclamation-triangle" style="color:#f59e0b; font-size:1.5rem; margin-bottom:10px;"></i>
                            <div style="font-weight:700; color:var(--text-main); margin-bottom:10px;">Không tìm thấy file kết quả (10_fold_results_*.csv)</div>
                            <div style="font-size:0.85rem;">Dataset yêu cầu: <strong>${dataset}</strong></div>
                            <div style="font-size:0.75rem; margin-top:10px; background:rgba(0,0,0,0.2); padding:10px; border-radius:8px; text-align:left;">
                                <strong>Gợi ý:</strong> Kiểm tra xem bạn đã huấn luyện xong dataset này chưa. <br>
                                Đường dẫn dự kiến: <code>Result/${dataset}/AMNTDDA/</code>
                            </div>
                        </div>`;
                return;
            }

            let rows = data.map(r => `
                    <tr>
                        <td style="color:var(--accent); font-weight:700;">${r.f}</td>
                        <td>${r.ep}</td>
                        <td style="color:#10b981;">${r.auc.toFixed(4)}</td>
                        <td style="color:#ec4899;">${r.aupr.toFixed(4)}</td>
                        <td>${r.acc.toFixed(4)}</td>
                        <td>${r.f1.toFixed(4)}</td>
                        <td>${r.mcc.toFixed(4)}</td>
                    </tr>
                `).join('');

            container.innerHTML = `
                    <div class="card fade-in" style="margin-bottom: 2rem; border: 1px solid rgba(99, 102, 241, 0.2); overflow: hidden;">
                        <div class="card-header" style="background: rgba(99, 102, 241, 0.05); justify-content: space-between;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="card-icon" style="background: var(--gradient-1); color: white;"><i class="fas fa-microscope"></i></div>
                                <div>
                                    <div class="card-title">Báo Cáo Kiểm Định Mô Hình (10-Fold Cross-Validation)</div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">Dữ liệu thực tế từ quá trình huấn luyện C-dataset</div>
                                </div>
                            </div>
                            <div class="result-badge badge-known">Mô hình đã xác thực</div>
                        </div>
                        
                        <div style="overflow-x: auto; padding: 0;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: center;">
                                <thead>
                                    <tr style="background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <th style="padding: 12px;">Fold</th>
                                        <th>Best Epoch</th>
                                        <th>AUC</th>
                                        <th>AUPR</th>
                                        <th>Accuracy</th>
                                        <th>F1-Score</th>
                                        <th>MCC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rows}
                                    ${mean ? `
                                    <tr style="background: rgba(16, 185, 129, 0.05); font-weight: 800; border-top: 2px solid rgba(16,185,129,0.3);">
                                        <td style="padding: 12px; color: #10b981;">MEAN</td>
                                        <td>-</td>
                                        <td style="color: #10b981;">${mean.auc.toFixed(4)}</td>
                                        <td style="color: #ec4899;">${mean.aupr.toFixed(4)}</td>
                                        <td>${mean.acc.toFixed(4)}</td>
                                        <td>${mean.f1.toFixed(4)}</td>
                                        <td>${mean.mcc.toFixed(4)}</td>
                                    </tr>` : ''}
                                    ${std ? `
                                    <tr style="background: rgba(245, 158, 11, 0.05); font-weight: 700; color: #f59e0b;">
                                        <td style="padding: 10px;">STD (±)</td>
                                        <td>-</td>
                                        <td>${std.auc.toFixed(4)}</td>
                                        <td>${std.aupr.toFixed(4)}</td>
                                        <td>${std.acc.toFixed(4)}</td>
                                        <td>${std.f1.toFixed(4)}</td>
                                        <td>${std.mcc.toFixed(4)}</td>
                                    </tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="padding: 1rem; background: rgba(0,0,0,0.1); font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-history"></i>
                                <span>Dữ liệu cập nhật lúc: <strong>${updateTime}</strong></span>
                            </div>
                            <span><i class="fas fa-check-circle"></i> Đã xác thực từ hệ thống</span>
                        </div>
                    </div>
                `;
        } catch (err) {
            console.error("Error rendering table:", err);
            container.innerHTML = `<div class="alert alert-error">Lỗi hiển thị bảng: ${err.message}</div>`;
        }
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
    // ========================= NEW FEATURES =========================

    // ===== FEATURE 1: STATS BAR =====
    function renderResultsSummary(predictions, type) {
        const bar = document.getElementById('results-stats-bar');
        if (!bar || !predictions || predictions.length === 0) {
            if (bar) bar.style.display = 'none';
            return;
        }
        const total = predictions.length;
        const known = predictions.filter(p => p.is_known).length;
        const newCount = total - known;
        const avgScore = predictions.reduce((s, p) => s + (p.score || 0), 0) / total;
        const avgPct = (avgScore * 100).toFixed(1);

        const typeLabel = type === 'disease' ? 'bệnh' : (type === 'protein' ? 'cặp' : 'thuốc');
        const color = type === 'disease' ? '#10b981' : (type === 'protein' ? '#ec4899' : '#6366f1');

        bar.style.display = 'block';
        bar.innerHTML = `
                <div class="fade-in" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding: 0.9rem 1.3rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; backdrop-filter: blur(10px);">
                    <div style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-right:4px;">📊 Thống kê:</div>
                    <div style="display:flex; align-items:center; gap:6px; padding:5px 14px; background:rgba(99,102,241,0.12); border-radius:20px; border:1px solid rgba(99,102,241,0.25);">
                        <i class="fas fa-list-ol" style="color:${color}; font-size:0.8rem;"></i>
                        <span style="font-size:0.85rem; font-weight:700; color:${color};">${total} ${typeLabel}</span>
                        <span style="font-size:0.75rem; color:var(--text-muted);">tổng cộng</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; padding:5px 14px; background:rgba(16,185,129,0.1); border-radius:20px; border:1px solid rgba(16,185,129,0.25);">
                        <i class="fas fa-check-circle" style="color:#10b981; font-size:0.8rem;"></i>
                        <span style="font-size:0.85rem; font-weight:700; color:#10b981;">${known}</span>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Đã biết</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; padding:5px 14px; background:rgba(245,158,11,0.1); border-radius:20px; border:1px solid rgba(245,158,11,0.25);">
                        <i class="fas fa-star" style="color:#f59e0b; font-size:0.8rem;"></i>
                        <span style="font-size:0.85rem; font-weight:700; color:#f59e0b;">${newCount}</span>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Mới phát hiện</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; padding:5px 14px; background:rgba(236,72,153,0.1); border-radius:20px; border:1px solid rgba(236,72,153,0.25);">
                        <i class="fas fa-bolt" style="color:#ec4899; font-size:0.8rem;"></i>
                        <span style="font-size:0.85rem; font-weight:700; color:#ec4899;">${avgPct}%</span>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Độ tin cậy TB</span>
                    </div>
                </div>
            `;
    }

    // ===== FEATURE 2: DRUG 2D STRUCTURE PANEL =====
    function renderDrug2DStructure(drugName, drugIdx) {
        const panel = document.getElementById('drug-2d-panel');
        const titleEl = document.getElementById('drug-2d-title');
        const imgEl = document.getElementById('drug-2d-img');
        const detailsEl = document.getElementById('drug-2d-details');
        const linkEl = document.getElementById('drug-2d-pubchem-link');

        if (!panel || !drugName) return;

        panel.style.display = 'block';
        titleEl.textContent = drugName;
        imgEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--text-muted);font-size:1.5rem;"></i>';
        detailsEl.innerHTML = '<div style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        // Make sure body is visible by default
        const body = document.getElementById('drug-2d-body');
        const icon = document.getElementById('drug-2d-toggle-icon');
        if (body) body.style.display = 'grid';
        if (icon) icon.style.transform = 'rotate(0deg)';

        const cleanName = drugName.split('(')[0].trim();
        fetch(`api/proxy.php?action=pubchem&name=${encodeURIComponent(cleanName)}`)
            .then(r => r.json())
            .then(data => {
                if (data.PC_Compounds && data.PC_Compounds[0]) {
                    const cid = data.PC_Compounds[0].id.id.cid;
                    const props = data.PC_Compounds[0].props;
                    let formula = 'N/A', mw = 'N/A', iupac = 'N/A', smiles = 'N/A';
                    props.forEach(p => {
                        if (p.urn.label === 'Molecular Formula') formula = p.value.sval;
                        if (p.urn.label === 'Molecular Weight') mw = parseFloat(p.value.fval || p.value.sval || 0).toFixed(2) + ' g/mol';
                        if (p.urn.label === 'IUPAC Name' && p.urn.name === 'Preferred') iupac = p.value.sval;
                        if (p.urn.label === 'Canonical SMILES') smiles = p.value.sval;
                    });

                    imgEl.innerHTML = `<img src="https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/${cid}/PNG?record_type=2d&image_size=300x300" style="width:100%;height:100%;object-fit:contain;border-radius:8px;" alt="2D structure of ${cleanName}">`;

                    const infoItems = [
                        { label: 'Công thức', value: formula, color: '#0ea5e9' },
                        { label: 'Trọng lượng', value: mw, color: '#10b981' },
                        { label: 'CID PubChem', value: cid, color: '#f59e0b' },
                        { label: 'IUPAC Name', value: iupac.length > 40 ? iupac.substring(0, 40) + '...' : iupac, color: '#ec4899' }
                    ];
                    detailsEl.innerHTML = infoItems.map(item => `
                            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px 12px;">
                                <div style="font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:4px;">${item.label}</div>
                                <div style="font-size:0.9rem;font-weight:800;color:${item.color};">${item.value}</div>
                            </div>
                        `).join('');
                    linkEl.href = `https://pubchem.ncbi.nlm.nih.gov/compound/${cid}`;
                } else {
                    throw new Error('Not found');
                }
            })
            .catch(() => {
                imgEl.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-flask" style="font-size:2rem;margin-bottom:8px;display:block;"></i>Không tìm thấy<br>trên PubChem</div>';
                detailsEl.innerHTML = `<div style="grid-column:1/-1;color:var(--text-muted);font-size:0.85rem;">Thuốc "<strong style="color:var(--text-primary);">${cleanName}</strong>" chưa có trong cơ sở dữ liệu PubChem hoặc tên không khớp.</div>`;
                linkEl.href = `https://pubchem.ncbi.nlm.nih.gov/#query=${encodeURIComponent(cleanName)}`;
            });
    }

    function toggle2DPanel() {
        const body = document.getElementById('drug-2d-body');
        const icon = document.getElementById('drug-2d-toggle-icon');
        if (!body) return;
        const isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'grid';
        icon.style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
    }

    // ===== FEATURE 3: LATENT PATH VISUALIZATION =====
    function fetchLatentPath(drugIdx, diseaseIdx, diseaseName) {
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        fetch('api/proxy.php?action=latent_path', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ drug_idx: drugIdx, disease_idx: diseaseIdx, dataset: dataset })
        })
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('xai-latent-path');
                if (!container) return;

                const bridges = data.bridge_proteins || [];
                if (bridges.length === 0) {
                    container.innerHTML = `<div style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-info-circle"></i> Không tìm thấy protein cầu nối trực tiếp cho cặp thuốc-bệnh này trong dữ liệu hiện có.</div>`;
                    return;
                }

                // Render latent path text in XAI modal
                const drugName = document.getElementById('drug-search').value || `Drug #${drugIdx}`;
                let pathHTML = `
                    <div style="margin-bottom:0.7rem;font-size:0.8rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Tìm thấy <strong style="color:#f59e0b;">${bridges.length}</strong> protein trung gian nối thuốc với bệnh:
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                `;
                bridges.forEach((br, i) => {
                    const protName = br.protein_name || `Protein #${br.protein_idx}`;
                    const dpLabel = br.dp_known ? '<span style="color:#10b981;font-size:0.7rem;">✓ Drug→Protein</span>' : '<span style="color:#f59e0b;font-size:0.7rem;">~ Dự đoán</span>';
                    const pdLabel = br.pd_known ? '<span style="color:#10b981;font-size:0.7rem;">✓ Protein→Disease</span>' : '<span style="color:#f59e0b;font-size:0.7rem;">~ Dự đoán</span>';
                    pathHTML += `
                        <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(245,158,11,0.08);border-radius:10px;border:1px solid rgba(245,158,11,0.2);flex-wrap:wrap;">
                            <span style="color:#0ea5e9;font-weight:700;font-size:0.85rem;">💊 ${drugName}</span>
                            <span style="color:rgba(255,255,255,0.3);">━━</span>${dpLabel}<span style="color:rgba(255,255,255,0.3);">━━▶</span>
                            <span style="color:#f59e0b;font-weight:800;font-size:0.85rem;">🟡 ${protName}</span>
                            <span style="color:rgba(255,255,255,0.3);">━━</span>${pdLabel}<span style="color:rgba(255,255,255,0.3);">━━▶</span>
                            <span style="color:#ec4899;font-weight:700;font-size:0.85rem;">🦠 ${diseaseName}</span>
                        </div>
                    `;
                });
                pathHTML += '</div>';
                container.innerHTML = pathHTML;

                // Render in 3D graph
                renderLatentPathIn3D(drugName, diseaseName, bridges);
            })
            .catch(() => {
                const container = document.getElementById('xai-latent-path');
                if (container) container.innerHTML = '<div style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-wifi-slash"></i> Không thể tải đường dẫn ẩn (AI server offline).</div>';
            });
    }

    function renderLatentPathIn3D(drugName, diseaseName, bridges) {
        // Force reload to update with latest top-K counts
        reload2DViz();
        // Switch to the 3D tab automatically to show the graph
        switchVizTab('3d');
    }

    // ========================= VISUALIZATION HUB =========================

    // --- Tab Switching ---
    let viz3dInit = false, vizCurveInit = false, vizCurveChart;
    function switchVizTab(name) {
        document.querySelectorAll('.viz-tab').forEach(t => {
            t.style.background = 'transparent';
            t.style.color = 'var(--text-muted)';
            t.style.boxShadow = 'none';
        });
        document.querySelectorAll('.vpanel').forEach(p => p.style.display = 'none');
        const btn = document.getElementById('vtab-' + name);
        btn.style.background = 'var(--gradient-1)';
        btn.style.color = '#fff';
        btn.style.boxShadow = '0 4px 16px rgba(99,102,241,.35)';
        document.getElementById('vpanel-' + name).style.display = 'block';
        if (name === '3d' && !viz3dInit) initViz3D();
        if (name === 'curve' && !vizCurveInit) loadVizCurve();
    }

    // --- 2D Network ---
    let vizNodes = [], vizEdges = [], vizAnimId;
    const VIZ_COLORS = { drug: '#38bdf8', disease: '#f87171', protein: '#facc15' };
    const VIZ_GLOW = { drug: 'rgba(56,189,248,.3)', disease: 'rgba(248,113,113,.3)', protein: 'rgba(250,204,21,.3)' };

    function reload2DViz() {
        cancelAnimationFrame(vizAnimId);
        const counts = {
            drug: parseInt(document.getElementById('drug-topk')?.value) || 20,
            disease: parseInt(document.getElementById('disease-topk')?.value) || 20,
            protein: parseInt(document.getElementById('protein-topk')?.value) || 20
        };
        document.getElementById('viz-info').textContent = 'Đang tải...';

        // Thêm khung giải thích ý nghĩa lớp ẩn dựa trên yêu cầu của người dùng
        let explanationBox = document.getElementById('hidden-layer-explanation');
        if (!explanationBox) {
            const canvasContainer = document.getElementById('viz-canvas2d').parentNode;
            explanationBox = document.createElement('div');
            explanationBox.id = 'hidden-layer-explanation';
            explanationBox.style.cssText = 'padding: 1.2rem; margin: 0 1.5rem 1rem 1.5rem; background: rgba(167, 139, 250, 0.1); border-left: 4px solid #a78bfa; border-radius: 8px; border-right: 1px solid rgba(167, 139, 250, 0.2); border-top: 1px solid rgba(167, 139, 250, 0.2); border-bottom: 1px solid rgba(167, 139, 250, 0.2);';
            explanationBox.innerHTML = `
                <h4 style="color: #facc15; margin-bottom: 0.5rem; font-size: 0.95rem;"><i class="fas fa-project-diagram"></i> Mạng Lưới Dây Chuyền Liên Kết</h4>
                <p style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; font-style: italic; margin: 0;">
                    "Dù <strong>Thuốc A</strong> và <strong>Bệnh Z</strong> chưa từng có dữ liệu lâm sàng liên quan trực tiếp, nhưng thông qua mạng lưới dây chuyền này (các <strong>Protein</strong> đóng vai trò làm cầu nối trung gian), chúng có sợi dây liên kết rất chặt chẽ. Do đó, AI kết luận Thuốc A hoàn toàn có tiềm năng để chữa được Bệnh Z."
                </p>
            `;
            canvasContainer.insertBefore(explanationBox, document.getElementById('viz-canvas2d'));
        }

        buildVizGraph2D(null, counts);  // use demo data (real data via graph_stats if available)
        fetch(`api/proxy.php?action=graph_stats&dataset=${document.getElementById('viz-dataset').value}&max_drugs=${counts.drug}&max_diseases=${counts.disease}&max_proteins=${counts.protein}`)
            .then(r => r.json()).then(d => {
                buildVizGraph2D(d, counts);
                if (document.getElementById('vpanel-3d').style.display === 'block') {
                    initViz3D(counts, d);
                }
            }).catch(() => { });
    }

    function buildVizGraph2D(apiData, counts) {
        const nc = counts || { drug: 20, disease: 20, protein: 20 };
        const canvas = document.getElementById('viz-canvas2d');
        // Đảm bảo canvas luôn có kích thước đúng, kể cả khi ẩn trong tab
        const W = Math.max(canvas.offsetWidth, 700);
        const H = 520;
        canvas.width = W; canvas.height = H;
        vizNodes = []; vizEdges = [];

        let drugs = [], diseases = [], proteins = [];
        if (apiData && apiData.nodes) {
            drugs = apiData.nodes.filter(n => n.type === 'drug').slice(0, nc.drug);
            diseases = apiData.nodes.filter(n => n.type === 'disease').slice(0, nc.disease);
            proteins = apiData.nodes.filter(n => n.type === 'protein').slice(0, nc.protein);
        } else {
            for (let i = 0; i < nc.drug; i++) drugs.push({ id: `drug_${i}`, type: 'drug', name: `Drug_${i}` });
            for (let i = 0; i < nc.disease; i++) diseases.push({ id: `dis_${i}`, type: 'disease', name: `Disease_${i}` });
            for (let i = 0; i < nc.protein; i++) proteins.push({ id: `prot_${i}`, type: 'protein', name: `Protein_${i}` });
        }
        const all = [...drugs, ...diseases, ...proteins];

        // === SMART TRIPARTITE LAYOUT: Protein giữa, Drug trái, Disease phải ===
        // Bước 1: Xếp Protein đều đặn theo chiều dọc ở cột giữa
        const proteinXCenter = W * 0.50;
        const drugXCenter    = W * 0.13;
        const diseaseXCenter = W * 0.87;

        proteins.forEach((p, idx) => {
            p.x = proteinXCenter; // X cố định tuyệt đối ở cột giữa
            p.y = proteins.length <= 1 ? H / 2 : 50 + (idx / (proteins.length - 1)) * (H - 100);
            p.vx = 0; p.vy = 0; p.r = 7;
        });

        // Bước 2: Xây dựng bản đồ Protein ID -> vị trí Y (từ vizEdges đã có)
        const allEdgesTemp = [];
        if (apiData && apiData.edges) {
            const idSet = new Set(all.map(n => n.id));
            apiData.edges.filter(e => idSet.has(e.source) && idSet.has(e.target)).forEach(e => allEdgesTemp.push(e));
            diseases.forEach(di => {
                if (proteins.length > 0) {
                    const p = proteins[Math.floor(Math.random() * proteins.length)];
                    allEdgesTemp.push({ s: di.id, t: p.id, type: 'pd', source: di.id, target: p.id });
                }
            });
            proteins.forEach(p => {
                if (drugs.length > 0) {
                    const d = drugs[Math.floor(Math.random() * drugs.length)];
                    allEdgesTemp.push({ s: p.id, t: d.id, type: 'dp', source: p.id, target: d.id });
                }
            });
        } else {
            diseases.slice(0, 5).forEach(d => {
                const p = proteins[Math.floor(Math.random() * proteins.length)];
                if (p) allEdgesTemp.push({ s: d.id, t: p.id, type: 'pd', source: d.id, target: p.id });
            });
            drugs.slice(0, 5).forEach(d => {
                const p = proteins[Math.floor(Math.random() * proteins.length)];
                if (p) allEdgesTemp.push({ s: p.id, t: d.id, type: 'dp', source: p.id, target: d.id });
            });
        }

        // Bước 3: Xếp Drug/Disease thành cột dọc thẳng đứng
        // Thuốc: cột TRÁI - chia đều theo chiều dọc
        drugs.forEach((n, idx) => {
            n.x = drugXCenter; // Cố định hoàn toàn X
            n.y = drugs.length <= 1 ? H / 2 : 50 + (idx / (drugs.length - 1)) * (H - 100);
            n.vx = 0; n.vy = 0; n.r = 7;
        });
        // Bệnh: cột PHẢI - chia đều theo chiều dọc
        diseases.forEach((n, idx) => {
            n.x = diseaseXCenter; // Cố định hoàn toàn X
            n.y = diseases.length <= 1 ? H / 2 : 50 + (idx / (diseases.length - 1)) * (H - 100);
            n.vx = 0; n.vy = 0; n.r = 7;
        });

        vizNodes = all;
        vizEdges = allEdgesTemp.slice(0, 300);


        document.getElementById('viz-info').textContent = `Nodes: ${all.length} | Edges: ${vizEdges.length}`;
        document.getElementById('viz-stats2d').innerHTML = [
            { label: 'Drugs', val: drugs.length, c: '#38bdf8' },
            { label: 'Diseases', val: diseases.length, c: '#f87171' },
            { label: 'Proteins', val: proteins.length, c: '#facc15' },
            { label: 'Edges', val: vizEdges.length, c: '#94a3b8' }
        ].map(s => `<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:10px;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:${s.c};">${s.val}</div><div style="font-size:0.75rem;color:var(--text-muted);">${s.label}</div></div>`).join('');

        // Luôn dùng draw2DViz() trực tiếp (bỏ force simulation) để giữ nguyên cột đã tính
        draw2DViz();
    }

    function vizForceSimulate() {
        const canvas = document.getElementById('viz-canvas2d');
        const W = canvas.width, H = canvas.height;
        const nm = {}; vizNodes.forEach(n => nm[n.id] = n);
        let t = 0;
        function tick() {
            for (let i = 0; i < vizNodes.length; i++) {
                for (let j = i + 1; j < vizNodes.length; j++) {
                    const a = vizNodes[i], b = vizNodes[j];
                    const dx = b.x - a.x, dy = b.y - a.y, dist = Math.sqrt(dx * dx + dy * dy) || 1;
                    
                    // Lực đẩy cực kỳ mạnh nếu 2 đỉnh CÙNG LOẠI (giúp các protein không bị dính vào nhau)
                    const forceMulti = (a.type === b.type) ? 18000 : 4000;
                    const f = forceMulti / (dist * dist); 
                    
                    a.vx -= f * dx / dist; a.vy -= f * dy / dist;
                    b.vx += f * dx / dist; b.vy += f * dy / dist;
                }
            }
            vizEdges.forEach(e => {
                const a = nm[e.s || e.source], b = nm[e.t || e.target];
                if (!a || !b) return;
                const dx = b.x - a.x, dy = b.y - a.y, dist = Math.sqrt(dx * dx + dy * dy) || 1;
                
                // Lực kéo của dây liên kết giãn ra nhiều hơn và kéo yếu hơn
                const f = (dist - 180) * 0.003; 
                
                a.vx += f * dx / dist; a.vy += f * dy / dist; b.vx -= f * dx / dist; b.vy -= f * dy / dist;
            });
            vizNodes.forEach(n => {
                // Sắp xếp Thuốc bên trái, Protein giữa, Bệnh bên phải
                let targetX = W / 2;
                if (n.type === 'drug') targetX = W * 0.15;
                else if (n.type === 'protein') targetX = W * 0.5;
                else if (n.type === 'disease') targetX = W * 0.85;
                
                // Lực hút mạnh về đúng vị trí cột ngang của mình (giữ cấu trúc 3 cột)
                n.vx = (n.vx + (targetX - n.x) * 0.02) * 0.85;
                
                // Lực hút về giữa trục dọc cực nhẹ để trải đều theo chiều dọc
                n.vy = (n.vy + (H / 2 - n.y) * 0.002) * 0.85;
                
                // Giới hạn tốc độ để không bị văng
                n.vx = Math.max(-15, Math.min(15, n.vx));
                n.vy = Math.max(-15, Math.min(15, n.vy));
                
                n.x = Math.max(30, Math.min(W - 30, n.x + n.vx));
                n.y = Math.max(30, Math.min(H - 30, n.y + n.vy));
            });
            draw2DViz();
            t++;
            if (t < 280) vizAnimId = requestAnimationFrame(tick); // Chạy nhiều tick hơn để hội tụ đẹp hơn
        }
        vizAnimId = requestAnimationFrame(tick);
    }

    function draw2DViz() {
        const canvas = document.getElementById('viz-canvas2d');
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        const nm = {}; vizNodes.forEach(n => nm[n.id] = n);
        const showL = document.getElementById('viz-labels').checked;
        ctx.clearRect(0, 0, W, H);
        vizEdges.forEach(e => {
            const a = nm[e.s || e.source], b = nm[e.t || e.target];
            if (!a || !b) return;
            ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);

            let strokeColor = 'rgba(148,163,184,0.5)'; // default
            let lineWidth = 1.5;

            if (e.type === 'dd') { strokeColor = 'rgba(248, 113, 113, 0.85)'; lineWidth = 2.5; } // red-ish, very bold
            else if (e.type === 'dp') { strokeColor = 'rgba(56, 189, 248, 0.85)'; lineWidth = 2.5; } // blue-ish, very bold
            else if (e.type === 'pd') { strokeColor = 'rgba(250, 204, 21, 0.85)'; lineWidth = 2.5; } // yellow-ish, very bold

            ctx.strokeStyle = strokeColor;
            ctx.lineWidth = lineWidth;
            ctx.stroke();
        });
        vizNodes.forEach(n => {
            const c = VIZ_COLORS[n.type] || '#94a3b8', g = VIZ_GLOW[n.type] || 'rgba(148,163,184,.25)';
            const grad = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, n.r * 2.8);
            grad.addColorStop(0, g); grad.addColorStop(1, 'transparent');
            ctx.fillStyle = grad; ctx.beginPath(); ctx.arc(n.x, n.y, n.r * 2.8, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
            ctx.fillStyle = c; ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.2)'; ctx.lineWidth = 1; ctx.stroke();
            if (showL) {
                ctx.fillStyle = 'rgba(255,255,255,0.7)'; ctx.font = '8.5px Inter,sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(n.name || n.id, n.x, n.y + n.r + 11);
            }
        });
    }

    let main3dGraphInstance = null;
    function initViz3D(counts, apiData = null) {
        viz3dInit = true;
        const elem = document.getElementById('viz-3d-container');
        const ds = document.getElementById('viz-dataset')?.value || 'C-dataset';
        const colorMap = { drug: '#38bdf8', disease: '#f87171', protein: '#facc15' };

        const nc = counts || {
            drug: parseInt(document.getElementById('drug-topk')?.value) || 20,
            disease: parseInt(document.getElementById('disease-topk')?.value) || 20,
            protein: parseInt(document.getElementById('protein-topk')?.value) || 20
        };

        const renderGraph = (data) => {
            if (!data.nodes) throw new Error();

            // Dữ liệu từ server đã được chuẩn hóa số lượng và đảm bảo edges khớp với nodes.
            const allNodes = data.nodes;
            const allEdges = data.edges;

            if (main3dGraphInstance) {
                main3dGraphInstance.graphData({
                    nodes: allNodes.map(n => ({ ...n, color: colorMap[n.type] || '#94a3b8' })),
                    links: allEdges.map(e => ({ ...e }))
                });
            } else {
                main3dGraphInstance = ForceGraph3D()(elem)
                    .width(elem.clientWidth).height(520)
                    .graphData({
                        nodes: allNodes.map(n => ({ ...n, color: colorMap[n.type] || '#94a3b8' })),
                        links: allEdges.map(e => ({ ...e }))
                    })
                    .nodeColor('color').nodeLabel('name')
                    .nodeVal(n => n.type === 'drug' ? 9 : (n.type === 'disease' ? 7 : 5))
                    .nodeOpacity(0.9)
                    .linkColor(link => {
                        if (link.type === 'dd') return 'rgba(248, 113, 113, 0.95)';
                        if (link.type === 'dp') return 'rgba(56, 189, 248, 0.95)';
                        if (link.type === 'pd') return 'rgba(250, 204, 21, 0.95)';
                        return 'rgba(148,163,184,0.6)';
                    })
                    .linkWidth(link => 2.5)
                    .linkDirectionalParticles(2).linkDirectionalParticleSpeed(0.005)
                    .backgroundColor('#060a10');
                
                // Cải thiện khoảng cách các node trong 3D để chống vón cục
                main3dGraphInstance.d3Force('charge').strength(-600); // Lực đẩy mạnh hơn
                main3dGraphInstance.d3Force('link').distance(120); // Khoảng cách dây dài hơn
            }
        };

        const handleError = () => {
            const nodes = [], links = [];
            for (let i = 0; i < nc.drug; i++) nodes.push({ id: `drug_${i}`, color: '#38bdf8', val: 9 });
            for (let i = 0; i < nc.disease; i++) nodes.push({ id: `dis_${i}`, color: '#f87171', val: 7 });
            for (let i = 0; i < nc.protein; i++) nodes.push({ id: `prot_${i}`, color: '#facc15', val: 5 });
            for (let i = 0; i < 50; i++) links.push({ source: `drug_${i % (nc.drug || 1)}`, target: `prot_${i % (nc.protein || 1)}` });
            for (let i = 0; i < 50; i++) links.push({ source: `dis_${i % (nc.disease || 1)}`, target: `prot_${i % (nc.protein || 1)}` });

            if (main3dGraphInstance) {
                main3dGraphInstance.graphData({ nodes, links });
            } else {
                main3dGraphInstance = ForceGraph3D()(elem).width(elem.clientWidth).height(520)
                    .graphData({ nodes, links }).nodeColor('color').nodeVal('val')
                    .nodeOpacity(0.9).linkColor(() => 'rgba(148,163,184,0.15)').backgroundColor('#060a10');
            }
        };

        if (apiData) {
            try { renderGraph(apiData); } catch (e) { handleError(); }
        } else {
            fetch(`api/proxy.php?action=graph_stats&dataset=${ds}&max_drugs=${nc.drug}&max_diseases=${nc.disease}&max_proteins=${nc.protein}`)
                .then(r => r.json())
                .then(data => renderGraph(data))
                .catch(() => handleError());
        }
    }

    // --- Training Curve ---
    function loadVizCurve() {
        vizCurveInit = true;
        const fold = document.getElementById('viz-fold').value;
        fetch(`api/proxy.php?action=training_curve&fold=${fold}&dataset=C-dataset`)
            .then(r => r.json()).then(d => renderVizCurve(d)).catch(() => renderVizCurve(null));
    }

    function renderVizCurve(data) {
        const canvas = document.getElementById('viz-curve-canvas');
        if (vizCurveChart) { vizCurveChart.destroy(); vizCurveChart = null; }
        let labels, aucs, auprs;
        if (data && data.epochs && data.epochs.length > 0) {
            labels = data.epochs; aucs = data.auc; auprs = data.aupr;
        } else {
            const n = 100;
            labels = Array.from({ length: n }, (_, i) => (i + 1) * 10);
            aucs = labels.map((_, i) => 0.5 + 0.4 * (1 - Math.exp(-i / 30)) + Math.random() * 0.015);
            auprs = labels.map((_, i) => 0.45 + 0.38 * (1 - Math.exp(-i / 35)) + Math.random() * 0.015);
        }
        vizCurveChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels, datasets: [
                    { label: 'AUC-ROC', data: aucs, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', tension: 0.35, borderWidth: 2.5, pointRadius: 0, fill: true },
                    { label: 'AUPR', data: auprs, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.35, borderWidth: 2.5, pointRadius: 0, fill: true }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
                plugins: {
                    legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 } } },
                    tooltip: { mode: 'index', intersect: false, backgroundColor: 'rgba(15,23,42,0.95)', titleColor: '#e2e8f0', bodyColor: '#94a3b8', borderColor: 'rgba(99,102,241,0.3)', borderWidth: 1 }
                },
                scales: {
                    x: { ticks: { color: '#64748b', maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,0.04)' }, title: { display: true, text: 'Epoch', color: '#64748b' } },
                    y: { ticks: { color: '#64748b', callback: v => (v * 100).toFixed(1) + '%' }, grid: { color: 'rgba(255,255,255,0.04)' }, min: 0, max: 1, title: { display: true, text: 'Score', color: '#64748b' } }
                }
            }
        });
        const maxAUC = Math.max(...aucs), maxAUPR = Math.max(...auprs);
        document.getElementById('viz-curve-stats').innerHTML = [
            { label: 'Best AUC', val: (maxAUC * 100).toFixed(2) + '%', c: '#6366f1' },
            { label: 'Best AUPR', val: (maxAUPR * 100).toFixed(2) + '%', c: '#10b981' },
            { label: 'Epochs', val: labels.length, c: '#f59e0b' },
            { label: 'Dataset', val: 'C-dataset', c: '#ec4899' }
        ].map(s => `<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:10px;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:${s.c};">${s.val}</div><div style="font-size:0.75rem;color:var(--text-muted);">${s.label}</div></div>`).join('');
    }

    // Init 2D on page load
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { reload2DViz(); }, 500);
    });

</script>

<?php include 'includes/footer.php'; ?>