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
            <div class="stat-value"><?= $stats['drugs'] ?></div>
            <div class="stat-label"><i class="fas fa-pills"></i> Thuốc</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['diseases'] ?></div>
            <div class="stat-label"><i class="fas fa-virus"></i> Bệnh</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['associations'] ?></div>
            <div class="stat-label"><i class="fas fa-link"></i> Liên kết</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['predictions'] ?></div>
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

<script>
let currentPage = {drugs: 1, diseases: 1, assoc: 1};

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
    fetch(`api/admin.php?action=drugs&page=${page}`)
        .then(r => r.json())
        .then(data => {
            let html = '<div class="table-container"><table><thead><tr><th>IDX</th><th>Drug ID</th><th>Tên</th><th>Actions</th></tr></thead><tbody>';
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
    fetch(`api/admin.php?action=diseases&page=${page}`)
        .then(r => r.json())
        .then(data => {
            let html = '<div class="table-container"><table><thead><tr><th>IDX</th><th>Disease ID</th><th>Tên</th><th>Actions</th></tr></thead><tbody>';
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
    fetch('api/admin.php?action=logs')
        .then(r => r.json())
        .then(data => {
            let html = '<div class="table-container"><table><thead><tr><th>User</th><th>Loại</th><th>Truy vấn</th><th>Thời gian</th></tr></thead><tbody>';
            data.logs.forEach(l => {
                html += `<tr><td>${l.username}</td><td>${l.query_type}</td><td>${l.query_value}</td><td style="font-size:0.8rem">${l.created_at}</td></tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('admin-content').innerHTML = html;
        });
}

function saveDrug(id) {
    const name = document.getElementById('dn-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_drug', id, name})
    }).then(r => r.json()).then(d => { if(d.success) alert('Đã lưu!'); });
}

function saveDisease(id) {
    const name = document.getElementById('din-' + id).value;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update_disease', id, name})
    }).then(r => r.json()).then(d => { if(d.success) alert('Đã lưu!'); });
}

function deleteDrug(id) {
    if(!confirm('Xóa thuốc này?')) return;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'delete_drug', id})
    }).then(r => r.json()).then(d => { if(d.success) loadDrugs(); });
}

function deleteDisease(id) {
    if(!confirm('Xóa bệnh này?')) return;
    fetch('api/admin.php', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'delete_disease', id})
    }).then(r => r.json()).then(d => { if(d.success) loadDiseases(); });
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
</script>

<?php include 'includes/footer.php'; ?>
