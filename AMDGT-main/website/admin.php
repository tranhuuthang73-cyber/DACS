<?php
require_once 'includes/config.php';
$pageTitle = 'Quản trị (Admin)';
include 'includes/header.php';

if (!isAdmin()) {
    echo '<div class="auth-container"><div class="alert alert-error"><i class="fas fa-lock"></i> Bạn không có quyền truy cập trang này.</div></div>';
    include 'includes/footer.php';
    exit;
}

$db = getDB();
$stats = [
    'drugs' => $db->query("SELECT COUNT(*) FROM drugs")->fetchColumn(),
    'diseases' => $db->query("SELECT COUNT(*) FROM diseases")->fetchColumn(),
    'proteins' => $db->query("SELECT COUNT(*) FROM proteins")->fetchColumn(),
    'associations' => $db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn(),
    'predictions' => $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem 1rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <h1 class="section-title fade-in" style="font-size: 2.5rem; background: linear-gradient(135deg, #f43f5e, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><i class="fas fa-user-shield"></i> Bảng Điều Khiển Admin</h1>
        <p class="section-subtitle fade-in" style="font-size: 1.1rem; color: var(--text-secondary);">Quản lý cơ sở dữ liệu cốt lõi và lịch sử truy cập hệ thống</p>
    </div>

    <!-- Stats Grid with Glassmorphism -->
    <div class="stats-grid fade-in" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(99,102,241,0.2);">
            <div class="stat-value" id="stat-drugs" style="font-size: 2.5rem; font-weight: 900; color: #818cf8;"><?= $stats['drugs'] ?></div>
            <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-pills"></i> Thuốc (Drugs)</div>
        </div>
        <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(16,185,129,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(16,185,129,0.2);">
            <div class="stat-value" id="stat-diseases" style="font-size: 2.5rem; font-weight: 900; color: #34d399;"><?= $stats['diseases'] ?></div>
            <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-virus"></i> Bệnh (Diseases)</div>
        </div>
        <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(244,63,94,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(244,63,94,0.2);">
            <div class="stat-value" id="stat-proteins" style="font-size: 2.5rem; font-weight: 900; color: #fb7185;"><?= $stats['proteins'] ?></div>
            <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-dna"></i> Protein</div>
        </div>
        <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(245,158,11,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(245,158,11,0.2);">
            <div class="stat-value" id="stat-associations" style="font-size: 2.5rem; font-weight: 900; color: #fbbf24;"><?= $stats['associations'] ?></div>
            <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-link"></i> Liên Kết (DDA)</div>
        </div>
        <div class="stat-card" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid rgba(56,189,248,0.3); border-radius: 20px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px -10px rgba(56,189,248,0.2);">
            <div class="stat-value" id="stat-predictions" style="font-size: 2.5rem; font-weight: 900; color: #38bdf8;"><?= $stats['predictions'] ?></div>
            <div class="stat-label" style="color: var(--text-muted); font-weight: 600; margin-top: 0.5rem;"><i class="fas fa-search"></i> Lượt Dự Đoán</div>
        </div>
    </div>

    <!-- Tabs (Modern Style) -->
    <div class="tabs" style="display: flex; gap: 10px; margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 16px; width: fit-content; margin-left: auto; margin-right: auto; border: 1px solid var(--border);">
        <button class="tab active" onclick="adminTab('drugs')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-pills"></i> Thuốc</button>
        <button class="tab" onclick="adminTab('diseases')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-virus"></i> Bệnh</button>
        <button class="tab" onclick="adminTab('proteins')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-dna"></i> Protein</button>
        <button class="tab" onclick="adminTab('assoc')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-project-diagram"></i> Mạng Liên Kết</button>
        <button class="tab" onclick="adminTab('logs')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-history"></i> Lịch Sử Toàn Hệ Thống</button>
        <button class="tab" onclick="adminTab('users')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-users"></i> Quản Lý Người Dùng</button>
        <button class="tab" onclick="adminTab('dataset_stats')" style="border-radius: 10px; padding: 10px 20px; font-weight: bold; transition: 0.3s;"><i class="fas fa-database"></i> Thống Kê Dataset</button>
    </div>

    <div id="admin-content" style="background: var(--bg-glass); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden; padding: 20px;"></div>
</div>

<!-- Modal xem chi tiết -->
<div id="detailsModal" class="modal-overlay" style="position: fixed; inset: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; background: rgba(10, 14, 23, 0.85); backdrop-filter: blur(8px); z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
    <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-height: 85vh; width: 90%; max-width: 700px; display: flex; flex-direction: column; overflow: hidden; transform: translateY(20px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
        <!-- Modal Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.1);">
            <div id="modalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 15px; flex: 1; min-width: 0;">
                Chi tiết dự đoán
            </div>
            <button class="modal-close" onclick="closeModal()" style="position: static; margin-left: 15px; flex-shrink: 0; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); transition: 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <!-- Modal Body -->
        <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 1.5rem; background: var(--bg-color);">
            <div class="results-list" id="modalContent" style="margin-top: 0; display: flex; flex-direction: column; gap: 12px;"></div>
        </div>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div id="confirmModal" style="position: fixed; inset: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; background: rgba(10, 14, 23, 0.9); backdrop-filter: blur(8px); z-index: 99999; opacity: 0; visibility: hidden; transition: all 0.25s ease;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.5rem 2rem; max-width: 420px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); text-align: center; transform: scale(0.9); transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);" id="confirmBox">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);">
            <i class="fas fa-exclamation-triangle" style="font-size: 1.8rem; color: #ef4444;"></i>
        </div>
        <h3 style="margin: 0 0 0.8rem; color: var(--text-primary); font-size: 1.4rem; font-weight: 800;">Xác nhận thao tác</h3>
        <p id="confirmMessage" style="margin: 0 0 2rem; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6;">Hành động này <strong style="color: #ef4444;">không thể hoàn tác</strong>.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="confirmCancel()" class="btn" style="padding: 12px 24px; background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.2s;">
                Hủy bỏ
            </button>
            <button onclick="confirmOk()" class="btn btn-danger" style="padding: 12px 24px; background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); transition: 0.2s;">
                Đồng ý
            </button>
        </div>
    </div>
</div>

<style>
#modalContent::-webkit-scrollbar { width: 6px; }
#modalContent::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb { background: var(--accent-dark); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb:hover { background: var(--accent-light); }

#detailsModal.active .modal-content { transform: translateY(0); }
#confirmModal.show { opacity: 1 !important; visibility: visible !important; }
#confirmModal.show #confirmBox { transform: scale(1) !important; }

.modern-result-item {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 1.2rem;
    transition: all 0.2s ease;
}
.modern-result-item:hover {
    background: rgba(255,255,255,0.03);
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateX(5px);
}
.modern-result-rank {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #818cf8); color: white;
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;
}
.modern-result-info { flex: 1; min-width: 0; }
.modern-result-name { font-weight: 700; color: var(--text-primary); font-size: 1.05rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.modern-result-id { font-family: monospace; color: var(--text-muted); font-size: 0.8rem; }
.modern-result-score { display: flex; align-items: center; gap: 12px; width: 180px; }
.modern-score-bar-bg { flex: 1; height: 6px; background: rgba(0,0,0,0.2); border-radius: 3px; overflow: hidden; }
.modern-score-fill { height: 100%; border-radius: 3px; }

/* Admin Inputs */
.admin-input-row {
    margin-bottom: 1.5rem; display: flex; gap: 10px; align-items: center; 
    background: rgba(0,0,0,0.15); padding: 1.2rem; border-radius: 16px; border: 1px solid var(--border);
}
table th { font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem; padding: 1.2rem; }
table td { padding: 1rem 1.2rem; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.05); }
table tbody tr:hover { background: rgba(255,255,255,0.02); }
</style>

<script>
let currentPage = {drugs: 1, diseases: 1, proteins: 1, assoc: 1, logs: 1, users: 1};

function refreshStats() {
    fetch('api/admin.php?action=stats&_t=' + Date.now())
        .then(r => r.json())
        .then(s => {
            document.getElementById('stat-drugs').textContent = s.drugs || 0;
            document.getElementById('stat-diseases').textContent = s.diseases || 0;
            document.getElementById('stat-proteins').textContent = s.proteins || 0;
            document.getElementById('stat-associations').textContent = s.associations || 0;
            document.getElementById('stat-predictions').textContent = s.predictions || 0;
        }).catch(() => {});
}

function adminTab(tab) {
    document.querySelectorAll('.tabs .tab').forEach((t, i) => {
        t.classList.toggle('active', ['drugs','diseases','proteins','assoc','logs','users','dataset_stats'][i] === tab);
    });
    if (tab === 'drugs') loadDrugs(1);
    else if (tab === 'diseases') loadDiseases(1);
    else if (tab === 'proteins') loadProteins(1);
    else if (tab === 'assoc') loadAssociations(1);
    else if (tab === 'logs') loadLogs(1);
    else if (tab === 'users') loadUsers(1);
    else if (tab === 'dataset_stats') loadDatasetStats();
}

function attachAutocomplete(inputId, otherInputId, type, isNameField) {
    const input = document.getElementById(inputId);
    if (!input || input.hasAttribute('data-ac-init')) return;
    input.setAttribute('data-ac-init', '1');
    
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    const flexMatch = input.getAttribute('style')?.match(/flex:\s*([^;]+)/);
    wrapper.style.flex = flexMatch ? flexMatch[1] : '1';
    input.style.flex = '1';
    input.style.width = '100%';
    
    const parent = input.parentNode;
    parent.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    
    const dropdown = document.createElement('div');
    dropdown.style.position = 'absolute';
    dropdown.style.top = '100%';
    dropdown.style.left = '0';
    dropdown.style.right = '0';
    dropdown.style.background = 'var(--bg-card)';
    dropdown.style.border = '1px solid var(--border)';
    dropdown.style.borderRadius = '8px';
    dropdown.style.zIndex = '1000';
    dropdown.style.maxHeight = '200px';
    dropdown.style.overflowY = 'auto';
    dropdown.style.display = 'none';
    dropdown.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.5)';
    wrapper.appendChild(dropdown);
    
    let timer;
    input.addEventListener('input', function() {
        const val = this.value.trim();
        dropdown.style.display = 'none';
        if (!val) return;
        
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch(`api/search.php?type=${type}&q=${encodeURIComponent(val)}&dataset=ALL`)
                .then(r => r.json())
                .then(data => {
                    if (!data || data.length === 0 || data.error) return;
                    dropdown.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.padding = '8px 12px';
                        div.style.cursor = 'pointer';
                        div.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                        
                        let itemId = item[`${type}_id`];
                        div.innerHTML = `<strong style="color:var(--text-primary)">${itemId}</strong> - <span style="color:var(--text-muted)">${item.name}</span>`;
                        
                        div.onmousedown = function(e) {
                            e.preventDefault();
                            if (isNameField) {
                                input.value = item.name;
                                document.getElementById(otherInputId).value = itemId;
                            } else {
                                input.value = itemId;
                                document.getElementById(otherInputId).value = item.name;
                            }
                            dropdown.style.display = 'none';
                            if (window.checkDrugInput) window.checkDrugInput();
                            if (window.checkDiseaseInput) window.checkDiseaseInput();
                            if (window.checkProteinInput) window.checkProteinInput();
                        };
                        div.onmouseenter = function() { this.style.background = 'rgba(255,255,255,0.1)'; };
                        div.onmouseleave = function() { this.style.background = 'transparent'; };
                        dropdown.appendChild(div);
                    });
                    dropdown.style.display = 'block';
                });
        }, 300);
    });
    
    input.addEventListener('blur', () => {
        dropdown.style.display = 'none';
    });
}

function loadDrugs(page) {
    currentPage.drugs = page || 1;
    fetch(`api/admin.php?action=drugs&page=${currentPage.drugs}`)
        .then(r => r.json())
        .then(data => {
            let html = `
            <div class="admin-input-row" style="flex-direction: column; align-items: stretch; gap: 15px;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-weight: bold; color: #818cf8; min-width: 140px;"><i class="fas fa-pills"></i> Thông tin Thuốc:</span>
                    <select id="new-drug-dataset" class="form-input" style="flex: 0.5; border-radius: 10px; max-width: 110px;">
                        <option value="C-dataset">C-dataset</option>
                        <option value="B-dataset">B-dataset</option>
                        <option value="F-dataset">F-dataset</option>
                    </select>
                    <input type="text" id="new-drug-id" class="form-input" placeholder="Mã thuốc (VD: DB00001)" style="flex: 1; border-radius: 10px;" oninput="checkDrugInput()">
                    <input type="text" id="new-drug-name" class="form-input" placeholder="Tên thuốc" style="flex: 2; border-radius: 10px;" oninput="checkDrugInput()">
                    <button id="btn-add-drug-only" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px;" onclick="addDrug()"><i class="fas fa-plus"></i> Thêm Thuốc</button>
                </div>
                
                <div id="link-inputs-container" style="display: none; flex-direction: column; gap: 15px; margin-top: 5px; padding-top: 15px; border-top: 1px dashed rgba(255,255,255,0.1);">
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: -5px;"><i class="fas fa-info-circle"></i> Bạn có thể thêm luôn các liên kết cho thuốc này (Tùy chọn):</div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #fb7185; min-width: 140px;"><i class="fas fa-dna"></i> Protein liên kết:</span>
                        <input type="text" id="new-protein-id-link" class="form-input" placeholder="Mã Protein" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-protein-name-link" class="form-input" placeholder="Tên Protein" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #34d399; min-width: 140px;"><i class="fas fa-virus"></i> Bệnh chữa được:</span>
                        <input type="text" id="new-disease-id-link" class="form-input" placeholder="Mã Bệnh" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-disease-name-link" class="form-input" placeholder="Tên Bệnh" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button class="btn" style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px; padding: 10px 30px; font-weight: bold;" onclick="addDrug()"><i class="fas fa-save"></i> Lưu Tất Cả (Thuốc + Liên Kết)</button>
                    </div>
                </div>
            </div>`;
            html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>IDX</th><th>Mã Thuốc</th><th>Tên Thuốc</th><th style="text-align: right;">Thao Tác</th></tr></thead><tbody>';
            data.drugs.forEach(d => {
                html += `<tr>
                    <td style="color: var(--text-muted); font-weight: bold;">${d.idx}</td>
                    <td style="font-family: monospace; color: #818cf8;">${d.drug_id}</td>
                    <td><input class="form-input" style="padding:6px 12px; font-size:0.9rem; border-radius: 8px;" value="${d.name || ''}" id="dn-${d.id}"></td>
                    <td style="text-align: right;">
                        <button class="btn btn-sm" style="background: rgba(99,102,241,0.2); color: #818cf8; border: 1px solid rgba(99,102,241,0.4); border-radius: 8px;" onclick="saveDrug(${d.id})"><i class="fas fa-save"></i></button>
                        <button class="btn btn-sm" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); border-radius: 8px;" onclick="deleteDrug(${d.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, currentPage.drugs, 20, 'loadDrugs');
            document.getElementById('admin-content').innerHTML = html;
            setTimeout(() => {
                attachAutocomplete('new-drug-id', 'new-drug-name', 'drug', false);
                attachAutocomplete('new-drug-name', 'new-drug-id', 'drug', true);
                attachAutocomplete('new-protein-id-link', 'new-protein-name-link', 'protein', false);
                attachAutocomplete('new-protein-name-link', 'new-protein-id-link', 'protein', true);
                attachAutocomplete('new-disease-id-link', 'new-disease-name-link', 'disease', false);
                attachAutocomplete('new-disease-name-link', 'new-disease-id-link', 'disease', true);
            }, 100);
        });
}

function loadDiseases(page) {
    currentPage.diseases = page || 1;
    fetch(`api/admin.php?action=diseases&page=${currentPage.diseases}`)
        .then(r => r.json())
        .then(data => {
            let html = `
            <div class="admin-input-row" style="flex-direction: column; align-items: stretch; gap: 15px;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-weight: bold; color: #34d399; min-width: 140px;"><i class="fas fa-virus"></i> Thêm Bệnh:</span>
                    <select id="new-disease-dataset" class="form-input" style="flex: 0.5; border-radius: 10px; max-width: 110px;">
                        <option value="C-dataset">C-dataset</option>
                        <option value="B-dataset">B-dataset</option>
                        <option value="F-dataset">F-dataset</option>
                    </select>
                    <input type="text" id="new-disease-id" class="form-input" placeholder="Mã bệnh (VD: DOID:1234)" style="flex: 1; border-radius: 10px;" oninput="checkDiseaseInput()">
                    <input type="text" id="new-disease-name" class="form-input" placeholder="Tên bệnh" style="flex: 2; border-radius: 10px;" oninput="checkDiseaseInput()">
                    <button id="btn-add-disease-only" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px;" onclick="addDisease()"><i class="fas fa-plus"></i> Thêm</button>
                </div>
                
                <div id="disease-link-inputs-container" style="display: none; flex-direction: column; gap: 15px; margin-top: 5px; padding-top: 15px; border-top: 1px dashed rgba(255,255,255,0.1);">
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: -5px;"><i class="fas fa-info-circle"></i> Bạn có thể thêm luôn các liên kết cho bệnh này (Tùy chọn):</div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #fb7185; min-width: 140px;"><i class="fas fa-dna"></i> Protein liên kết:</span>
                        <input type="text" id="new-protein-id-disease-link" class="form-input" placeholder="Mã Protein" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-protein-name-disease-link" class="form-input" placeholder="Tên Protein" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #818cf8; min-width: 140px;"><i class="fas fa-pills"></i> Thuốc điều trị:</span>
                        <input type="text" id="new-drug-id-disease-link" class="form-input" placeholder="Mã Thuốc" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-drug-name-disease-link" class="form-input" placeholder="Tên Thuốc" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; padding: 10px 30px; font-weight: bold;" onclick="addDisease()"><i class="fas fa-save"></i> Lưu Tất Cả (Bệnh + Liên Kết)</button>
                    </div>
                </div>
            </div>`;
            html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>IDX</th><th>Mã Bệnh</th><th>Tên Bệnh</th><th style="text-align: right;">Thao Tác</th></tr></thead><tbody>';
            data.diseases.forEach(d => {
                html += `<tr>
                    <td style="color: var(--text-muted); font-weight: bold;">${d.idx}</td>
                    <td style="font-family: monospace; color: #34d399;">${d.disease_id}</td>
                    <td><input class="form-input" style="padding:6px 12px; font-size:0.9rem; border-radius: 8px;" value="${d.name || ''}" id="din-${d.id}"></td>
                    <td style="text-align: right;">
                        <button class="btn btn-sm" style="background: rgba(99,102,241,0.2); color: #818cf8; border: 1px solid rgba(99,102,241,0.4); border-radius: 8px;" onclick="saveDisease(${d.id})"><i class="fas fa-save"></i></button>
                        <button class="btn btn-sm" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); border-radius: 8px;" onclick="deleteDisease(${d.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, currentPage.diseases, 20, 'loadDiseases');
            document.getElementById('admin-content').innerHTML = html;
            setTimeout(() => {
                attachAutocomplete('new-disease-id', 'new-disease-name', 'disease', false);
                attachAutocomplete('new-disease-name', 'new-disease-id', 'disease', true);
                attachAutocomplete('new-protein-id-disease-link', 'new-protein-name-disease-link', 'protein', false);
                attachAutocomplete('new-protein-name-disease-link', 'new-protein-id-disease-link', 'protein', true);
                attachAutocomplete('new-drug-id-disease-link', 'new-drug-name-disease-link', 'drug', false);
                attachAutocomplete('new-drug-name-disease-link', 'new-drug-id-disease-link', 'drug', true);
            }, 100);
        });
}

function loadProteins(page) {
    currentPage.proteins = page || 1;
    fetch(`api/admin.php?action=proteins&page=${currentPage.proteins}`)
        .then(r => r.json())
        .then(data => {
            let html = `
            <div class="admin-input-row" style="flex-direction: column; align-items: stretch; gap: 15px;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-weight: bold; color: #fb7185; min-width: 140px;"><i class="fas fa-dna"></i> Thông tin Protein:</span>
                    <select id="new-protein-dataset" class="form-input" style="flex: 0.5; border-radius: 10px; max-width: 110px;">
                        <option value="C-dataset">C-dataset</option>
                        <option value="B-dataset">B-dataset</option>
                        <option value="F-dataset">F-dataset</option>
                    </select>
                    <input type="text" id="new-protein-id" class="form-input" placeholder="Mã protein (VD: P12345)" style="flex: 1; border-radius: 10px;" oninput="checkProteinInput()">
                    <input type="text" id="new-protein-name" class="form-input" placeholder="Tên protein" style="flex: 2; border-radius: 10px;" oninput="checkProteinInput()">
                    <button id="btn-add-protein-only" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px;" onclick="addProtein()"><i class="fas fa-plus"></i> Thêm Protein</button>
                </div>
                
                <div id="protein-link-inputs-container" style="display: none; flex-direction: column; gap: 15px; margin-top: 5px; padding-top: 15px; border-top: 1px dashed rgba(255,255,255,0.1);">
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: -5px;"><i class="fas fa-info-circle"></i> Bạn có thể thêm luôn các liên kết cho protein này (Tùy chọn):</div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #818cf8; min-width: 140px;"><i class="fas fa-pills"></i> Thuốc liên kết:</span>
                        <input type="text" id="new-drug-id-protein-link" class="form-input" placeholder="Mã Thuốc" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-drug-name-protein-link" class="form-input" placeholder="Tên Thuốc" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-weight: bold; color: #34d399; min-width: 140px;"><i class="fas fa-virus"></i> Bệnh liên kết:</span>
                        <input type="text" id="new-disease-id-protein-link" class="form-input" placeholder="Mã Bệnh" style="flex: 1; border-radius: 10px;">
                        <input type="text" id="new-disease-name-protein-link" class="form-input" placeholder="Tên Bệnh" style="flex: 2; border-radius: 10px;">
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button class="btn" style="background: linear-gradient(135deg, #f43f5e, #e11d48); color: white; border: none; border-radius: 10px; padding: 10px 30px; font-weight: bold;" onclick="addProtein()"><i class="fas fa-save"></i> Lưu Tất Cả (Protein + Liên Kết)</button>
                    </div>
                </div>
            </div>`;
            html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>IDX</th><th>Mã Protein</th><th>Tên Protein</th><th style="text-align: right;">Thao Tác</th></tr></thead><tbody>';
            data.proteins.forEach(p => {
                html += `<tr>
                    <td style="color: var(--text-muted); font-weight: bold;">${p.idx}</td>
                    <td style="font-family: monospace; color: #fb7185;">${p.protein_id}</td>
                    <td><input class="form-input" style="padding:6px 12px; font-size:0.9rem; border-radius: 8px;" value="${p.name || ''}" id="pn-${p.id}"></td>
                    <td style="text-align: right;">
                        <button class="btn btn-sm" style="background: rgba(99,102,241,0.2); color: #818cf8; border: 1px solid rgba(99,102,241,0.4); border-radius: 8px;" onclick="saveProtein(${p.id})"><i class="fas fa-save"></i></button>
                        <button class="btn btn-sm" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); border-radius: 8px;" onclick="deleteProtein(${p.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, currentPage.proteins, 20, 'loadProteins');
            document.getElementById('admin-content').innerHTML = html;
            setTimeout(() => {
                attachAutocomplete('new-protein-id', 'new-protein-name', 'protein', false);
                attachAutocomplete('new-protein-name', 'new-protein-id', 'protein', true);
                attachAutocomplete('new-drug-id-protein-link', 'new-drug-name-protein-link', 'drug', false);
                attachAutocomplete('new-drug-name-protein-link', 'new-drug-id-protein-link', 'drug', true);
                attachAutocomplete('new-disease-id-protein-link', 'new-disease-name-protein-link', 'disease', false);
                attachAutocomplete('new-disease-name-protein-link', 'new-disease-id-protein-link', 'disease', true);
            }, 100);
        });
}

function loadAssociations(page) {
    currentPage.assoc = page || 1;
    fetch(`api/admin.php?action=associations&page=${currentPage.assoc}`)
        .then(r => r.json())
        .then(data => {
            let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>Drug IDX</th><th>Thuốc</th><th>Disease IDX</th><th>Bệnh</th></tr></thead><tbody>';
            data.associations.forEach(a => {
                html += `<tr><td style="color:var(--text-muted);font-weight:bold;">${a.drug_idx}</td><td style="color:#818cf8;font-weight:bold;">${a.drug_name||'-'}</td><td style="color:var(--text-muted);font-weight:bold;">${a.disease_idx}</td><td style="color:#34d399;font-weight:bold;">${a.disease_name||'-'}</td></tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, currentPage.assoc, 20, 'loadAssociations');
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadLogs() {
    fetch('api/admin.php?action=logs&_t=' + new Date().getTime(), { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>User</th><th>Loại</th><th>Truy vấn</th><th>KQ</th><th>Thời gian</th><th style="text-align:right;">Thao tác</th></tr></thead><tbody>';
            data.logs.forEach(l => {
                let resultsData = [];
                try { resultsData = JSON.parse(l.results) || []; } catch(e) {}
                let count = resultsData.length;
                let btnDetails = count > 0 ? `<button class="btn btn-sm" style="margin-right: 5px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:8px;" onclick='viewDetails(${JSON.stringify(l.query_value)}, ${JSON.stringify(l.query_type)}, ${JSON.stringify(resultsData).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i></button>` : '';
                let btnDelete = `<button class="btn btn-sm" style="background:rgba(239,68,68,0.2); color:#f87171; border:1px solid rgba(239,68,68,0.4); border-radius:8px;" onclick="deleteLog(event, '${l.id}')"><i class="fas fa-trash"></i></button>`;

                let typeBadge = l.query_type === 'drug_to_disease' 
                    ? `<span style="padding:4px 8px; border-radius:6px; background:rgba(129,140,248,0.2); color:#818cf8; font-size:0.8rem; font-weight:bold;"><i class="fas fa-pills"></i> T->B</span>` 
                    : `<span style="padding:4px 8px; border-radius:6px; background:rgba(52,211,153,0.2); color:#34d399; font-size:0.8rem; font-weight:bold;"><i class="fas fa-virus"></i> B->T</span>`;

                html += `<tr>
                <td style="font-weight:bold;"><i class="fas fa-user-circle" style="color:var(--text-muted)"></i> ${l.username}</td>
                <td>${typeBadge}</td>
                <td style="font-weight:bold; color:var(--text-primary);">${l.query_value}</td>
                <td>${count} <i class="fas fa-list" style="color:var(--text-muted);font-size:0.8em"></i></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${l.created_at}</td>
                <td style="text-align:right; white-space: nowrap;">${btnDetails}${btnDelete}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadUsers(page) {
    currentPage.users = page || 1;
    fetch(`api/admin.php?action=users&page=${currentPage.users}`)
        .then(r => r.json())
        .then(data => {
            let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>ID</th><th>Tên đăng nhập</th><th>Vai trò</th><th>Ngày đăng ký</th><th style="text-align: right;">Thao Tác</th></tr></thead><tbody>';
            data.users.forEach(u => {
                let roleBadge = u.role === 'admin' 
                    ? `<span style="padding:4px 8px; border-radius:6px; background:rgba(244,63,94,0.2); color:#fb7185; font-size:0.8rem; font-weight:bold;"><i class="fas fa-user-shield"></i> Admin</span>` 
                    : `<span style="padding:4px 8px; border-radius:6px; background:rgba(56,189,248,0.2); color:#38bdf8; font-size:0.8rem; font-weight:bold;"><i class="fas fa-user"></i> User</span>`;
                html += `<tr>
                    <td style="color: var(--text-muted); font-weight: bold;">${u.id}</td>
                    <td style="font-weight:bold; color:var(--text-primary);"><i class="fas fa-user-circle" style="color:var(--text-muted)"></i> ${u.username}</td>
                    <td>${roleBadge}</td>
                    <td style="font-size:0.85rem; color:var(--text-muted);">${u.created_at}</td>
                    <td style="text-align: right;">
                        ${u.role !== 'admin' ? `<button class="btn btn-sm" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); border-radius: 8px;" onclick="deleteUser(${u.id})"><i class="fas fa-trash"></i></button>` : ''}
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadDatasetStats() {
    fetch('api/admin.php?action=dataset_stats')
        .then(r => r.json())
        .then(data => {
            let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>Dataset</th><th>Thuốc</th><th>Bệnh</th><th>Protein</th><th>Thuốc-Bệnh</th><th>Thuốc-Protein</th><th>Bệnh-Protein</th></tr></thead><tbody>';
            data.stats.forEach(s => {
                html += `<tr>
                    <td style="color: var(--text-primary); font-weight: bold; font-size: 1.1rem;"><i class="fas fa-database" style="color: #6366f1;"></i> ${s.dataset}</td>
                    <td style="color: #818cf8; font-weight: bold;">${s.drugs.toLocaleString()}</td>
                    <td style="color: #34d399; font-weight: bold;">${s.diseases.toLocaleString()}</td>
                    <td style="color: #fb7185; font-weight: bold;">${s.proteins.toLocaleString()}</td>
                    <td style="color: #fbbf24; font-weight: bold;">${s.dd.toLocaleString()}</td>
                    <td style="color: #fbbf24; font-weight: bold;">${s.dp.toLocaleString()}</td>
                    <td style="color: #fbbf24; font-weight: bold;">${s.pd.toLocaleString()}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('admin-content').innerHTML = html;
        });
}

function saveDrug(id) {
    const name = document.getElementById('dn-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_drug', id, name})
    }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã lưu Thuốc', 'success'); loadDrugs(currentPage.drugs); refreshStats(); } });
}

function saveDisease(id) {
    const name = document.getElementById('din-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_disease', id, name})
    }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã lưu Bệnh', 'success'); loadDiseases(currentPage.diseases); refreshStats(); } });
}

function saveProtein(id) {
    const name = document.getElementById('pn-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_protein', id, name})
    }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã lưu Protein', 'success'); loadProteins(currentPage.proteins); refreshStats(); } });
}

function addDrug() {
    const dataset = document.getElementById('new-drug-dataset').value;
    const drug_id = document.getElementById('new-drug-id').value.trim();
    const name = document.getElementById('new-drug-name').value.trim();
    
    // Only get these values if the container is visible
    const container = document.getElementById('link-inputs-container');
    let protein_id = '', protein_name = '', disease_id = '', disease_name = '';
    
    if (container && container.style.display !== 'none') {
        protein_id = document.getElementById('new-protein-id-link').value.trim();
        protein_name = document.getElementById('new-protein-name-link').value.trim();
        disease_id = document.getElementById('new-disease-id-link').value.trim();
        disease_name = document.getElementById('new-disease-name-link').value.trim();
    }

    if (!drug_id || !name) return alert('Vui lòng nhập đủ mã thuốc và tên thuốc');
    
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action: 'add_drug', 
            dataset, drug_id, name, 
            protein_id, protein_name,
            disease_id, disease_name
        })
    }).then(r => r.json()).then(d => {
        if(d.success) { 
            showToast('Đã lưu thành công', 'success'); 
            loadDrugs(1); 
            refreshStats(); 
        } else { 
            alert('Lỗi: ' + (d.error || '')); 
        }
    });
}

function checkDrugInput() {
    const id = document.getElementById('new-drug-id').value.trim();
    const name = document.getElementById('new-drug-name').value.trim();
    const container = document.getElementById('link-inputs-container');
    const btnOnly = document.getElementById('btn-add-drug-only');
    
    if (container && btnOnly) {
        if (id && name) {
            container.style.display = 'flex';
            btnOnly.style.display = 'none';
        } else {
            container.style.display = 'none';
            btnOnly.style.display = 'block';
        }
    }
}

function checkDiseaseInput() {
    const id = document.getElementById('new-disease-id').value.trim();
    const name = document.getElementById('new-disease-name').value.trim();
    const container = document.getElementById('disease-link-inputs-container');
    const btnOnly = document.getElementById('btn-add-disease-only');
    
    if (container && btnOnly) {
        if (id && name) {
            container.style.display = 'flex';
            btnOnly.style.display = 'none';
        } else {
            container.style.display = 'none';
            btnOnly.style.display = 'block';
        }
    }
}

function addDisease() {
    const dataset = document.getElementById('new-disease-dataset').value;
    const disease_id = document.getElementById('new-disease-id').value.trim();
    const name = document.getElementById('new-disease-name').value.trim();
    
    const container = document.getElementById('disease-link-inputs-container');
    let protein_id = '', protein_name = '', drug_id = '', drug_name = '';
    
    if (container && container.style.display !== 'none') {
        protein_id = document.getElementById('new-protein-id-disease-link').value.trim();
        protein_name = document.getElementById('new-protein-name-disease-link').value.trim();
        drug_id = document.getElementById('new-drug-id-disease-link').value.trim();
        drug_name = document.getElementById('new-drug-name-disease-link').value.trim();
    }

    if (!disease_id || !name) return alert('Vui lòng nhập đủ mã bệnh và tên bệnh');
    
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'add_disease', 
            dataset, disease_id, name,
            protein_id, protein_name,
            drug_id, drug_name
        })
    }).then(r => r.json()).then(d => {
        if(d.success) { 
            showToast('Đã lưu thành công', 'success'); 
            loadDiseases(1); 
            refreshStats(); 
        } else { 
            alert('Lỗi: ' + (d.error || '')); 
        }
    });
}

function addProtein() {
    const dataset = document.getElementById('new-protein-dataset').value;
    const protein_id = document.getElementById('new-protein-id').value.trim();
    const name = document.getElementById('new-protein-name').value.trim();
    
    // Only get these values if the container is visible
    const container = document.getElementById('protein-link-inputs-container');
    let drug_id = '', drug_name = '', disease_id = '', disease_name = '';
    
    if (container && container.style.display !== 'none') {
        drug_id = document.getElementById('new-drug-id-protein-link').value.trim();
        drug_name = document.getElementById('new-drug-name-protein-link').value.trim();
        disease_id = document.getElementById('new-disease-id-protein-link').value.trim();
        disease_name = document.getElementById('new-disease-name-protein-link').value.trim();
    }

    if (!protein_id || !name) return alert('Vui lòng nhập đủ mã protein và tên protein');
    
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action: 'add_protein', 
            dataset, protein_id, name,
            drug_id, drug_name,
            disease_id, disease_name
        })
    }).then(r => r.json()).then(d => {
        if(d.success) { 
            showToast('Đã lưu thành công', 'success'); 
            loadProteins(1); 
            refreshStats(); 
        } else { 
            alert('Lỗi: ' + (d.error || '')); 
        }
    });
}

function checkProteinInput() {
    const id = document.getElementById('new-protein-id').value.trim();
    const name = document.getElementById('new-protein-name').value.trim();
    const container = document.getElementById('protein-link-inputs-container');
    const btnOnly = document.getElementById('btn-add-protein-only');
    
    if (container && btnOnly) {
        if (id && name) {
            container.style.display = 'flex';
            btnOnly.style.display = 'none';
        } else {
            container.style.display = 'none';
            btnOnly.style.display = 'block';
        }
    }
}

function deleteDrug(id) {
    showConfirm('Bạn có chắc chắn muốn xóa bản ghi thuốc này?').then(ok => {
        if(!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_drug', id})
        }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã xóa', 'success'); loadDrugs(currentPage.drugs); refreshStats(); } });
    });
}

function deleteDisease(id) {
    showConfirm('Bạn có chắc chắn muốn xóa bản ghi bệnh này?').then(ok => {
        if(!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_disease', id})
        }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã xóa', 'success'); loadDiseases(currentPage.diseases); refreshStats(); } });
    });
}

function deleteProtein(id) {
    showConfirm('Bạn có chắc chắn muốn xóa bản ghi protein này?').then(ok => {
        if(!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_protein', id})
        }).then(r => r.json()).then(d => { if(d.success) { showToast('Đã xóa', 'success'); loadProteins(currentPage.proteins); refreshStats(); } });
    });
}

function deleteUser(id) {
    showConfirm('Bạn có chắc chắn muốn xóa tài khoản người dùng này? Mọi dữ liệu lịch sử dự đoán của người dùng này cũng sẽ bị xóa vĩnh viễn.').then(ok => {
        if(!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_user', id})
        }).then(r => r.json()).then(d => { 
            if(d.success) { showToast('Đã xóa người dùng', 'success'); loadUsers(currentPage.users); refreshStats(); }
            else { showToast(d.error || 'Lỗi', 'error'); }
        });
    });
}

// === Custom Confirm Modal System ===
let _confirmResolve = null;
function showConfirm(message) {
    return new Promise(resolve => {
        _confirmResolve = resolve;
        document.getElementById('confirmMessage').innerHTML = message;
        const modal = document.getElementById('confirmModal');
        const box = document.getElementById('confirmBox');
        modal.classList.add('show');
    });
}
function confirmOk() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
    if (_confirmResolve) _confirmResolve(true);
    _confirmResolve = null;
}
function confirmCancel() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
    if (_confirmResolve) _confirmResolve(false);
    _confirmResolve = null;
}

function deleteLog(e, id) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    showConfirm('Xóa vĩnh viễn lượt dự đoán này của người dùng?').then(ok => {
        if (!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_log', id: parseInt(id)})
        }).then(r => r.json()).then(d => { 
            if(d.success) { showToast('Đã xóa lịch sử', 'success'); loadLogs(); refreshStats(); }
        });
    });
}

function renderPagination(total, current, perPage, fn) {
    const pages = Math.ceil(total / perPage);
    if (pages <= 1) return '';
    let html = '<div class="pagination" style="margin-top: 1.5rem;">';
    if (current > 1) html += `<button class="page-btn" onclick="${fn}(${current - 1})"><i class="fas fa-chevron-left"></i></button>`;
    
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(pages, current + 2);
    if (current <= 3) endPage = Math.min(5, pages);
    if (current >= pages - 2) startPage = Math.max(1, pages - 4);
    
    if (startPage > 1) { html += `<button class="page-btn" onclick="${fn}(1)">1</button>`; if (startPage > 2) html += `<span style="color:var(--text-muted);padding:8px">...</span>`; }
    for (let i = startPage; i <= endPage; i++) html += `<button class="page-btn ${i===current?'active':''}" onclick="${fn}(${i})">${i}</button>`;
    if (endPage < pages) { if (endPage < pages - 1) html += `<span style="color:var(--text-muted);padding:8px">...</span>`; html += `<button class="page-btn" onclick="${fn}(${pages})">${pages}</button>`; }
    if (current < pages) html += `<button class="page-btn" onclick="${fn}(${current + 1})"><i class="fas fa-chevron-right"></i></button>`;
    
    return html + '</div>';
}

function viewDetails(queryValue, queryType, results) {
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const modal = document.getElementById('detailsModal');
    
    if (queryType === 'drug_to_disease') {
        title.innerHTML = `
            <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(142, 45, 226, 0.15); border: 1px solid rgba(142,45,226,0.3); color: #c084fc; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fas fa-pills"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Thuốc -> Bệnh</div>
                <div style="color: var(--text-primary); font-size: 1.2rem; font-weight: 800;">${queryValue}</div>
            </div>`;
    } else {
        title.innerHTML = `
            <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fas fa-virus"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Bệnh -> Thuốc</div>
                <div style="color: var(--text-primary); font-size: 1.2rem; font-weight: 800;">${queryValue}</div>
            </div>`;
    }
    
    let html = '';
    const type = queryType === 'drug_to_disease' ? 'disease' : 'drug';
    results.forEach(p => {
        const name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
        const id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        const scorePct = Math.min(p.score * 100, 100).toFixed(1);
        const badge = p.is_known 
            ? '<span style="background: rgba(16,185,129,0.15); color: #34d399; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(16,185,129,0.3);">Đã biết</span>' 
            : '<span style="background: rgba(245,158,11,0.15); color: #fbbf24; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(245,158,11,0.3); box-shadow: 0 0 10px rgba(245,158,11,0.2);">Mới</span>';
        
        let scoreClass, valueColor;
        const score = p.score * 100;
        if (score >= 70) { scoreClass = 'background: linear-gradient(90deg, #10b981, #34d399);'; valueColor = '#34d399'; }
        else if (score >= 40) { scoreClass = 'background: linear-gradient(90deg, #f59e0b, #fbbf24);'; valueColor = '#fbbf24'; }
        else { scoreClass = 'background: linear-gradient(90deg, #ef4444, #f87171);'; valueColor = '#f87171'; }

        html += `
            <div class="modern-result-item">
                <div class="modern-result-rank">${p.rank}</div>
                <div class="modern-result-info">
                    <div class="modern-result-name">${name}</div>
                    <div class="modern-result-id">${id}</div>
                </div>
                <div class="modern-result-score">
                    <div class="modern-score-bar-bg"><div class="modern-score-fill" style="${scoreClass} width: ${scorePct}%"></div></div>
                    <div style="font-weight: 800; font-size: 1.1rem; color: ${valueColor}; width: 50px; text-align: right;">${scorePct}%</div>
                </div>
                ${badge}
            </div>
        `;
    });
    content.innerHTML = html;
    modal.classList.add('active');
}
function closeModal() { document.getElementById('detailsModal').classList.remove('active'); }
window.onclick = function(e) { if(e.target == document.getElementById('detailsModal')) closeModal(); }

// Init
adminTab('drugs');
</script>

<?php include 'includes/footer.php'; ?>