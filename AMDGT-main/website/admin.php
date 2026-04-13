<?php
require_once 'includes/config.php';
$pageTitle = 'Quản trị';
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
    'associations' => $db->query("SELECT COUNT(*) FROM known_associations")->fetchColumn(),
    'predictions' => $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <h1 class="section-title fade-in"><i class="fas fa-cog"></i> Bảng điều khiển Admin</h1>
    <p class="section-subtitle fade-in">Quản lý dữ liệu thuốc, bệnh và xem thống kê hệ thống</p>

    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-value" id="stat-drugs"><?= $stats['drugs'] ?></div>
            <div class="stat-label"><i class="fas fa-pills"></i> Thuốc</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-diseases"><?= $stats['diseases'] ?></div>
            <div class="stat-label"><i class="fas fa-virus"></i> Bệnh</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-associations"><?= $stats['associations'] ?></div>
            <div class="stat-label"><i class="fas fa-link"></i> Liên kết</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-predictions"><?= $stats['predictions'] ?></div>
            <div class="stat-label"><i class="fas fa-search"></i> Dự đoán</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" style="margin-top: 2rem;">
        <button class="tab active" onclick="adminTab('drugs')">Thuốc</button>
        <button class="tab" onclick="adminTab('diseases')">Bệnh</button>
        <button class="tab" onclick="adminTab('assoc')">Liên kết</button>
        <button class="tab" onclick="adminTab('logs')">Lịch sử</button>
    </div>

    <div id="admin-content"></div>
</div>

<!-- Modal xem chi tiết -->
<div id="detailsModal" class="modal-overlay" style="position: fixed; inset: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; background: rgba(10, 14, 23, 0.85); backdrop-filter: blur(8px); z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
    <div class="modal-content" style="max-height: 85vh; width: 90%; max-width: 700px; display: flex; flex-direction: column; overflow: hidden;">
        
        <!-- Modal Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1rem;">
            <div id="modalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                Chi tiết dự đoán
            </div>
            <button class="modal-close" onclick="closeModal()" style="position: static; margin-left: 15px; flex-shrink: 0;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div style="flex: 1; overflow-y: auto; overflow-x: hidden;">
            <div class="results-list" id="modalContent" style="margin-top: 0; padding-right: 8px;">
                <!-- Chi tiết AJAX -->
            </div>
        </div>
        
    </div>
</div>

<!-- Modal xác nhận xóa (Custom - không bị trình duyệt chặn) -->
<div id="confirmModal" style="position: fixed; inset: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; background: rgba(10, 14, 23, 0.9); backdrop-filter: blur(8px); z-index: 99999; opacity: 0; visibility: hidden; transition: all 0.25s ease;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; max-width: 420px; width: 90%; box-shadow: var(--shadow-lg); text-align: center; transform: scale(0.9); transition: transform 0.25s ease;" id="confirmBox">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
            <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; color: #ef4444;"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem; color: var(--text-primary); font-size: 1.1rem;">Xác nhận xóa</h3>
        <p id="confirmMessage" style="margin: 0 0 1.5rem; color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5;">Bạn có chắc chắn muốn xóa lịch sử dự đoán này?<br>Hành động này không thể hoàn tác.</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button onclick="confirmCancel()" class="btn" style="padding: 10px 24px; background: var(--bg-section); color: var(--text-secondary); border: 1px solid var(--border); border-radius: var(--radius); font-size: 0.9rem; cursor: pointer;">
                <i class="fas fa-times"></i> Hủy
            </button>
            <button onclick="confirmOk()" class="btn btn-danger" style="padding: 10px 24px; background: #ef4444; color: white; border: none; border-radius: var(--radius); font-size: 0.9rem; cursor: pointer;">
                <i class="fas fa-trash"></i> Xóa
            </button>
        </div>
    </div>
</div>

<style>
/* Custom scrollbar for modal */
#modalContent::-webkit-scrollbar { width: 6px; }
#modalContent::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb { background: var(--accent-dark); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb:hover { background: var(--accent-light); }
#confirmModal.show { opacity: 1; visibility: visible; }
#confirmModal.show #confirmBox { transform: scale(1); }
</style>

<script>
let currentPage = {drugs: 1, diseases: 1, assoc: 1};

// === Live Stats Refresh ===
function refreshStats() {
    fetch('api/admin.php?action=stats&_t=' + Date.now())
        .then(r => r.json())
        .then(s => {
            document.getElementById('stat-drugs').textContent = s.drugs;
            document.getElementById('stat-diseases').textContent = s.diseases;
            document.getElementById('stat-associations').textContent = s.associations;
            document.getElementById('stat-predictions').textContent = s.predictions;
        }).catch(() => {});
}

function adminTab(tab) {
    document.querySelectorAll('.tabs .tab').forEach((t, i) => {
        t.classList.toggle('active', ['drugs','diseases','assoc','logs'][i] === tab);
    });
    if (tab === 'drugs') loadDrugs();
    else if (tab === 'diseases') loadDiseases();
    else if (tab === 'assoc') loadAssociations();
    else if (tab === 'logs') loadLogs();
}

function loadDrugs(page) {
    page = page || 1;
    page = page || 1;
    fetch(`api/admin.php?action=drugs&page=${page}`)
        .then(r => r.json())
        .then(data => {
            let html = `
            <div style="margin-bottom: 1rem; display: flex; gap: 10px; align-items: center; background: var(--bg-card); padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border);">
                <span style="font-weight: bold; color: var(--accent-light);"><i class="fas fa-plus-circle"></i> Thêm mới:</span>
                <input type="text" id="new-drug-id" class="form-input" placeholder="Mã thuốc (VD: DB00001)" style="flex: 1;">
                <input type="text" id="new-drug-name" class="form-input" placeholder="Tên thuốc" style="flex: 2;">
                <button class="btn btn-success" onclick="addDrug()"><i class="fas fa-plus"></i> Thêm</button>
            </div>`;
            html += '<div class="table-container"><table><thead><tr><th>IDX</th><th>Drug ID</th><th>Tên</th><th>Actions</th></tr></thead><tbody>';
            data.drugs.forEach(d => {
                html += `<tr>
                    <td>${d.idx}</td><td>${d.drug_id}</td>
                    <td><input class="form-input" style="padding:4px 8px;font-size:0.8rem;" value="${d.name || ''}" id="dn-${d.id}"></td>
                    <td><button class="btn btn-sm btn-primary" onclick="saveDrug(${d.id})"><i class="fas fa-save"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="deleteDrug(${d.id})"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, page, 20, 'loadDrugs');
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadDiseases(page) {
    page = page || 1;
    page = page || 1;
    fetch(`api/admin.php?action=diseases&page=${page}`)
        .then(r => r.json())
        .then(data => {
            let html = `
            <div style="margin-bottom: 1rem; display: flex; gap: 10px; align-items: center; background: var(--bg-card); padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border);">
                <span style="font-weight: bold; color: var(--accent-light);"><i class="fas fa-plus-circle"></i> Thêm mới:</span>
                <input type="text" id="new-disease-id" class="form-input" placeholder="Mã bệnh (VD: D00001)" style="flex: 1;">
                <input type="text" id="new-disease-name" class="form-input" placeholder="Tên bệnh" style="flex: 2;">
                <button class="btn btn-success" onclick="addDisease()"><i class="fas fa-plus"></i> Thêm</button>
            </div>`;
            html += '<div class="table-container"><table><thead><tr><th>IDX</th><th>Disease ID</th><th>Tên</th><th>Actions</th></tr></thead><tbody>';
            data.diseases.forEach(d => {
                html += `<tr>
                    <td>${d.idx}</td><td>${d.disease_id}</td>
                    <td><input class="form-input" style="padding:4px 8px;font-size:0.8rem;" value="${d.name || ''}" id="din-${d.id}"></td>
                    <td><button class="btn btn-sm btn-primary" onclick="saveDisease(${d.id})"><i class="fas fa-save"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="deleteDisease(${d.id})"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, page, 20, 'loadDiseases');
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadAssociations(page) {
    page = page || 1;
    fetch(`api/admin.php?action=associations&page=${page}`)
        .then(r => r.json())
        .then(data => {
            let html = '<div class="table-container"><table><thead><tr><th>Drug IDX</th><th>Thuốc</th><th>Disease IDX</th><th>Bệnh</th></tr></thead><tbody>';
            data.associations.forEach(a => {
                html += `<tr><td>${a.drug_idx}</td><td>${a.drug_name||'-'}</td><td>${a.disease_idx}</td><td>${a.disease_name||'-'}</td></tr>`;
            });
            html += '</tbody></table></div>';
            html += renderPagination(data.total, page, 20, 'loadAssociations');
            document.getElementById('admin-content').innerHTML = html;
        });
}

function loadLogs() {
    fetch('api/admin.php?action=logs&_t=' + new Date().getTime(), { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            let html = '<div class="table-container"><table><thead><tr><th>User</th><th>Loại</th><th>Truy vấn</th><th>Kết quả</th><th>Thời gian</th><th>Thao tác</th></tr></thead><tbody>';
            data.logs.forEach(l => {
                let resultsData = [];
                try {
                    resultsData = JSON.parse(l.results) || [];
                } catch(e) {}
                let count = resultsData.length;
                let btnDetails = count > 0 ? `<button class="btn btn-sm btn-outline" style="margin-right: 5px;" onclick='viewDetails(${JSON.stringify(l.query_value)}, ${JSON.stringify(l.query_type)}, ${JSON.stringify(resultsData).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i> Chi tiết</button>` : '';
                let btnDelete = `<button class="btn btn-sm btn-danger" onclick="deleteLog(event, '${l.id}')"><i class="fas fa-trash"></i></button>`;

                html += `<tr>
                <td>${l.username}</td>
                <td><span style="color: ${l.query_type === 'drug_to_disease' ? 'var(--accent-light)' : 'var(--info)'};"><i class="fas ${l.query_type === 'drug_to_disease' ? 'fa-pills' : 'fa-virus'}"></i> ${l.query_type}</span></td>
                <td><strong>${l.query_value}</strong></td>
                <td>${count} kết quả</td>
                <td style="font-size:0.8rem">${l.created_at}</td>
                <td style="white-space: nowrap;">${btnDetails}${btnDelete}</td>
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
    }).then(r => r.json()).then(d => { if(d.success) { loadDrugs(); refreshStats(); } });
}

function saveDisease(id) {
    const name = document.getElementById('din-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_disease', id, name})
    }).then(r => r.json()).then(d => { if(d.success) { loadDiseases(); refreshStats(); } });
}

function addDrug() {
    const drug_id = document.getElementById('new-drug-id').value.trim();
    const name = document.getElementById('new-drug-name').value.trim();
    if (!drug_id || !name) return alert('Vui lòng nhập đủ mã thuốc và tên thuốc');
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'add_drug', drug_id, name})
    }).then(r => r.json()).then(d => {
        if(d.success) {
            loadDrugs();
            refreshStats();
        } else {
            alert('Lỗi: ' + (d.error || 'Mã thuốc có thể đã tồn tại.'));
        }
    });
}

function addDisease() {
    const disease_id = document.getElementById('new-disease-id').value.trim();
    const name = document.getElementById('new-disease-name').value.trim();
    if (!disease_id || !name) return alert('Vui lòng nhập đủ mã bệnh và tên bệnh');
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'add_disease', disease_id, name})
    }).then(r => r.json()).then(d => {
        if(d.success) {
            loadDiseases();
            refreshStats();
        } else {
            alert('Lỗi: ' + (d.error || 'Mã bệnh có thể đã tồn tại.'));
        }
    });
}

function deleteDrug(id) {
    if(!confirm('Xóa thuốc này?')) return;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'delete_drug', id})
    }).then(r => r.json()).then(d => { if(d.success) { loadDrugs(); refreshStats(); } });
}

function deleteDisease(id) {
    if(!confirm('Xóa bệnh này?')) return;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'delete_disease', id})
    }).then(r => r.json()).then(d => { if(d.success) { loadDiseases(); refreshStats(); } });
}

// === Custom Confirm Modal System ===
let _confirmResolve = null;
function showConfirm(message) {
    return new Promise(resolve => {
        _confirmResolve = resolve;
        document.getElementById('confirmMessage').innerHTML = message;
        const modal = document.getElementById('confirmModal');
        const box = document.getElementById('confirmBox');
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        box.style.transform = 'scale(1)';
    });
}
function confirmOk() {
    const modal = document.getElementById('confirmModal');
    const box = document.getElementById('confirmBox');
    modal.style.opacity = '0';
    box.style.transform = 'scale(0.9)';
    setTimeout(() => { modal.style.visibility = 'hidden'; }, 250);
    if (_confirmResolve) _confirmResolve(true);
    _confirmResolve = null;
}
function confirmCancel() {
    const modal = document.getElementById('confirmModal');
    const box = document.getElementById('confirmBox');
    modal.style.opacity = '0';
    box.style.transform = 'scale(0.9)';
    setTimeout(() => { modal.style.visibility = 'hidden'; }, 250);
    if (_confirmResolve) _confirmResolve(false);
    _confirmResolve = null;
}

function deleteLog(e, id) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    showConfirm('Bạn có chắc chắn muốn xóa lịch sử dự đoán này?<br>Hành động này <strong>không thể hoàn tác</strong>.').then(ok => {
        if (!ok) return;
        fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_log', id: parseInt(id)})
        }).then(r => r.json()).then(d => { 
            if(d.success) {
                loadLogs();
                refreshStats();
            } else {
                showConfirm('Có lỗi xảy ra khi xóa!');
            }
        }).catch(err => {
            console.error(err);
            showConfirm('Có lỗi mạng hoặc server!');
        });
    });
}

function renderPagination(total, current, perPage, fn) {
    const pages = Math.ceil(total / perPage);
    if (pages <= 1) return '';
    
    let html = '<div class="pagination">';
    
    // Nút Previous
    if (current > 1) {
        html += `<button class="page-btn" onclick="${fn}(${current - 1})"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    // Hiển thị tối đa 5 trang xung quanh trang hiện tại
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(pages, current + 2);
    
    // Điều chỉnh nếu dính lề
    if (current <= 3) endPage = Math.min(5, pages);
    if (current >= pages - 2) startPage = Math.max(1, pages - 4);
    
    // Nút trang 1 và dấu ...
    if (startPage > 1) {
        html += `<button class="page-btn" onclick="${fn}(1)">1</button>`;
        if (startPage > 2) html += `<span style="color:var(--text-muted);padding:8px">...</span>`;
    }
    
    // Nút các trang
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i===current?'active':''}" onclick="${fn}(${i})">${i}</button>`;
    }
    
    // Dấu ... và trang cuối
    if (endPage < pages) {
        if (endPage < pages - 1) html += `<span style="color:var(--text-muted);padding:8px">...</span>`;
        html += `<button class="page-btn" onclick="${fn}(${pages})">${pages}</button>`;
    }
    
    // Nút Next
    if (current < pages) {
        html += `<button class="page-btn" onclick="${fn}(${current + 1})"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    html += '</div>';
    return html;
}

// Load drugs by default
adminTab('drugs');

function viewDetails(queryValue, queryType, results) {
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const modal = document.getElementById('detailsModal');
    
    // Set title with modern styling
    if (queryType === 'drug_to_disease') {
        title.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(142, 45, 226, 0.2); color: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="fas fa-pills"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: normal; margin-bottom: 2px;">Dự đoán bệnh tiềm năng cho thuốc</div>
                <div style="color: var(--text-primary); font-size: 1.1rem;">${queryValue}</div>
            </div>
            <div>
                <a href="predict.php?q=${encodeURIComponent(queryValue)}&type=drug" class="btn btn-sm btn-primary" style="text-decoration:none;"><i class="fas fa-cube"></i> Tái tạo 3D VIP</a>
            </div>`;
    } else {
        title.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(74, 0, 224, 0.2); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="fas fa-virus"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: normal; margin-bottom: 2px;">Dự đoán thuốc điều trị cho bệnh</div>
                <div style="color: var(--text-primary); font-size: 1.1rem;">${queryValue}</div>
            </div>
            <div>
                <a href="predict.php?q=${encodeURIComponent(queryValue)}&type=disease" class="btn btn-sm btn-primary" style="text-decoration:none;"><i class="fas fa-cube"></i> Tái tạo 3D VIP</a>
            </div>`;
    }
    
    // Render kết quả sử dụng đúng CSS classes từ style.css
    let html = '';
    const type = queryType === 'drug_to_disease' ? 'disease' : 'drug';
    
    results.forEach(p => {
        const name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
        const id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        const scorePct = Math.min(p.score * 100, 100).toFixed(1);
        const badge = p.is_known ? '<span class="result-badge badge-known">Đã biết</span>' : '<span class="result-badge badge-new" style="box-shadow: 0 0 10px var(--accent-glow);">Mới</span>';
        
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
                ${badge}
            </div>
        `;
    });
    
    content.innerHTML = html;
    
    // Animation mở modal
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.classList.add('active');
}

function closeModal() {
    const modal = document.getElementById('detailsModal');
    modal.style.opacity = '0';
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.visibility = 'hidden';
    }, 300);
}

// Close modal khi click ra ngoài vùng xám
window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
