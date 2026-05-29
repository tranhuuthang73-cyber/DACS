<?php
require_once 'includes/config.php';
$pageTitle = 'Lịch sử';
include 'includes/header.php';

if (!isLoggedIn()) {
    echo '<div class="auth-container"><div class="alert alert-info"><i class="fas fa-info-circle"></i> Vui lòng <a href="login.php">đăng nhập</a> để xem lịch sử.</div></div>';
    include 'includes/footer.php';
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM predictions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 2rem 1rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <h1 class="section-title fade-in" style="font-size: 2.5rem; background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><i class="fas fa-history"></i> Lịch sử tra cứu</h1>
        <p class="section-subtitle fade-in" style="font-size: 1.1rem; color: var(--text-secondary);">Quản lý và xem lại các dự đoán AI gần đây của bạn</p>
    </div>

    <?php if (empty($history)): ?>
        <div class="alert alert-info" style="background: var(--bg-glass); backdrop-filter: blur(10px); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 16px; padding: 2rem; text-align: center; font-size: 1.1rem;">
            <i class="fas fa-search" style="font-size: 3rem; color: var(--accent); opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
            Bạn chưa có lịch sử tra cứu nào. Hãy đến trang <a href="predict.php" style="color: var(--accent-light); font-weight: bold;">Dự đoán</a> để bắt đầu!
        </div>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="margin:0; font-size: 1.2rem; color: var(--text-primary);">Đã tìm thấy <?= count($history) ?> kết quả</h2>
            <button class="btn btn-danger" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; border-radius: 12px; padding: 10px 20px; font-weight: bold;" onclick="clearAllHistory()">
                <i class="fas fa-trash-alt" style="margin-right: 8px;"></i> Xóa toàn bộ lịch sử
            </button>
        </div>
        <div class="table-container fade-in" style="background: var(--bg-glass); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: rgba(255, 255, 255, 0.03);">
                    <tr>
                        <th style="padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600;">#</th>
                        <th style="padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600;">Loại Phân Tích</th>
                        <th style="padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600;">Đối Tượng</th>
                        <th style="padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600;">Kết Quả</th>
                        <th style="padding: 1.2rem; text-align: left; color: var(--text-muted); font-weight: 600;">Thời Gian</th>
                        <th style="padding: 1.2rem; text-align: right; color: var(--text-muted); font-weight: 600;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $h): ?>
                    <tr style="border-top: 1px solid var(--border-light); transition: background 0.2s;" onmouseover="this.style.background='rgba(99, 102, 241, 0.05)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.2rem; font-weight: bold; color: var(--text-muted);"><?= $i + 1 ?></td>
                        <td style="padding: 1.2rem;">
                            <?php if ($h['query_type'] === 'drug_to_disease'): ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(99, 102, 241, 0.15); color: #818cf8; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(99, 102, 241, 0.3);"><i class="fas fa-pills"></i> Thuốc → Bệnh</span>
                            <?php elseif ($h['query_type'] === 'protein_to_any'): ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(244, 114, 182, 0.15); color: #f472b6; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(244, 114, 182, 0.3);"><i class="fas fa-dna"></i> Protein → Thuốc/Bệnh</span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(16, 185, 129, 0.15); color: #34d399; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.3);"><i class="fas fa-virus"></i> Bệnh → Thuốc</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1.2rem;"><strong style="font-size: 1.05rem; color: var(--text-primary);"><?= htmlspecialchars($h['query_value']) ?></strong></td>
                        <td style="padding: 1.2rem; white-space: nowrap;">
                            <?php 
                            $results = json_decode($h['results'], true);
                            $count = is_array($results) ? count($results) : 0;
                            $uniqueDrugs = [];
                            $uniqueDiseases = [];
                            if (is_array($results)) {
                                foreach ($results as $r) {
                                    if (!empty($r['drug_id'])) $uniqueDrugs[$r['drug_id']] = true;
                                    elseif (!empty($r['drug_name'])) $uniqueDrugs[$r['drug_name']] = true;
                                    
                                    if (!empty($r['disease_id'])) $uniqueDiseases[$r['disease_id']] = true;
                                    elseif (!empty($r['disease_name'])) $uniqueDiseases[$r['disease_name']] = true;
                                }
                            }
                            $dCount = count($uniqueDrugs);
                            $dsCount = count($uniqueDiseases);
                            if ($h['query_type'] === 'drug_to_disease') $dCount = max(1, $dCount);
                            if ($h['query_type'] === 'disease_to_drug') $dsCount = max(1, $dsCount);
                            echo "<span style='color:#818cf8; font-weight:bold;' title='Số lượng Thuốc'>{$dCount} <i class='fas fa-pills' style='font-size:0.85em'></i></span> <span style='color:var(--text-muted); margin:0 4px;'>-</span> <span style='color:#34d399; font-weight:bold;' title='Số lượng Bệnh'>{$dsCount} <i class='fas fa-virus' style='font-size:0.85em'></i></span>";
                            ?>
                        </td>
                        <td style="padding: 1.2rem; color: var(--text-muted); font-size: 0.85rem;">
                            <i class="far fa-clock" style="margin-right: 4px;"></i> <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                        </td>
                        <td style="padding: 1.2rem; text-align: right; white-space: nowrap;">
                            <?php if ($count > 0): ?>
                            <button class="btn btn-sm btn-outline" style="margin-right: 8px; border-radius: 10px; padding: 8px 16px;"
                                    onclick='viewDetails(<?= json_encode($h['query_value']) ?>, <?= json_encode($h['query_type']) ?>, <?= htmlspecialchars($h['results'], ENT_QUOTES, 'UTF-8') ?>)'>
                                <i class="fas fa-eye"></i> Chi tiết
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger" style="border-radius: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171;"
                                    onclick='deleteHistory(<?= $h['id'] ?>)' title="Xóa bản ghi này">
                                <i class="fas fa-trash"></i> Xóa
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
            <div class="results-list" id="modalContent" style="margin-top: 0; display: flex; flex-direction: column; gap: 12px;">
                <!-- Chi tiết AJAX -->
            </div>
        </div>
        
    </div>
</div>

<!-- Modal xác nhận xóa (Custom - không bị trình duyệt chặn) -->
<div id="confirmModal" style="position: fixed; inset: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; background: rgba(10, 14, 23, 0.9); backdrop-filter: blur(8px); z-index: 99999; opacity: 0; visibility: hidden; transition: all 0.25s ease;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.5rem 2rem; max-width: 420px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); text-align: center; transform: scale(0.9); transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);" id="confirmBox">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);">
            <i class="fas fa-trash-alt" style="font-size: 1.8rem; color: #ef4444;"></i>
        </div>
        <h3 style="margin: 0 0 0.8rem; color: var(--text-primary); font-size: 1.4rem; font-weight: 800;">Xác nhận xóa</h3>
        <p id="confirmMessage" style="margin: 0 0 2rem; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6;">Bạn có chắc chắn muốn xóa bản ghi lịch sử này?<br>Hành động này <strong style="color: #ef4444;">không thể hoàn tác</strong>.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="confirmCancel()" class="btn" style="padding: 12px 24px; background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.2s;">
                Hủy bỏ
            </button>
            <button onclick="confirmOk()" class="btn btn-danger" style="padding: 12px 24px; background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); transition: 0.2s;">
                Xóa ngay
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

/* Animation Classes */
#detailsModal.active .modal-content { transform: translateY(0); }
#confirmModal.show { opacity: 1 !important; visibility: visible !important; }
#confirmModal.show #confirmBox { transform: scale(1) !important; }

/* Modern Result Item Styling for Modal */
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
</style>

<script>
// === Delete History Logic ===
let currentDeleteId = null;

function deleteHistory(id) {
    currentDeleteId = id;
    const modal = document.getElementById('confirmModal');
    modal.classList.add('show');
}

function confirmCancel() {
    currentDeleteId = null;
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
}

function clearAllHistory() {
    currentDeleteId = 'all';
    document.getElementById('confirmMessage').innerHTML = 'Bạn có chắc chắn muốn xóa <strong>toàn bộ</strong> lịch sử?<br>Hành động này <strong style="color: #ef4444;">không thể hoàn tác</strong>.';
    const modal = document.getElementById('confirmModal');
    modal.classList.add('show');
}

function confirmOk() {
    if (!currentDeleteId) return;
    const id = currentDeleteId;
    confirmCancel(); // Hide modal

    const payload = id === 'all' ? { action: 'clear_all_history' } : { action: 'delete_history', id: id };

    fetch('api/history.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if(window.showToast) showToast('Đã xóa lịch sử thành công!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            if(window.showToast) showToast(res.error || 'Lỗi khi xóa', 'error');
            else alert(res.error);
        }
    })
    .catch(err => {
        console.error(err);
        if(window.showToast) showToast('Lỗi kết nối mạng!', 'error');
    });
}

// === View Details Logic ===
function viewDetails(queryValue, queryType, results) {
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const modal = document.getElementById('detailsModal');
    
    // Determine icon/color/label based on query type
    let iconClass, iconBg, iconColor, typeLabel, linkType;
    if (queryType === 'drug_to_disease') {
        iconClass = 'fa-pills'; iconBg = 'rgba(142, 45, 226, 0.15)'; iconColor = '#c084fc';
        typeLabel = 'Phân Tích AI: Thuốc -> Bệnh'; linkType = 'drug';
    } else if (queryType === 'protein_to_any') {
        iconClass = 'fa-dna'; iconBg = 'rgba(244, 114, 182, 0.15)'; iconColor = '#f472b6';
        typeLabel = 'Phân Tích AI: Protein -> Thuốc/Bệnh'; linkType = 'protein';
    } else {
        iconClass = 'fa-virus'; iconBg = 'rgba(16, 185, 129, 0.15)'; iconColor = '#34d399';
        typeLabel = 'Phân Tích AI: Bệnh -> Thuốc'; linkType = 'disease';
    }
    
    title.innerHTML = `
        <div style="width: 46px; height: 46px; border-radius: 12px; background: ${iconBg}; border: 1px solid ${iconColor}33; color: ${iconColor}; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas ${iconClass}"></i>
        </div>
        <div style="flex: 1;">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">${typeLabel}</div>
            <div style="color: var(--text-primary); font-size: 1.2rem; font-weight: 800;">${queryValue}</div>
        </div>
        <div>
            <a href="predict.php?q=${encodeURIComponent(queryValue)}&type=${linkType}" class="btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; padding: 10px 18px; border-radius: 12px; text-decoration:none; font-weight: 700; box-shadow: 0 4px 15px rgba(99,102,241,0.3);"><i class="fas fa-cube" style="margin-right: 6px;"></i> Tái tạo 3D</a>
        </div>`;
    
    // Render kết quả
    let html = '';
    const isProtein = queryType === 'protein_to_any';
    const type = queryType === 'drug_to_disease' ? 'disease' : 'drug';
    
    results.forEach((p, i) => {
        let name, id;
        if (isProtein) {
            // Protein pathways: show Drug → Protein → Disease
            name = (p.drug_name || 'Drug') + ' → ' + (p.disease_name || 'Disease');
            id = p.pathway || '';
        } else {
            name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
            id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        }
        
        // Score: protein predictions store as 0-100, drug/disease as 0-1
        const rawScore = p.score || 0;
        const scorePct = rawScore > 1 ? Math.min(rawScore, 100).toFixed(1) : Math.min(rawScore * 100, 100).toFixed(1);
        const scoreNum = parseFloat(scorePct);
        
        const badge = p.is_known 
            ? '<span style="background: rgba(16,185,129,0.15); color: #34d399; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(16,185,129,0.3);">Đã biết</span>' 
            : '<span style="background: rgba(245,158,11,0.15); color: #fbbf24; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(245,158,11,0.3); box-shadow: 0 0 10px rgba(245,158,11,0.2);">Liên kết mới</span>';
        
        let scoreClass, valueColor;
        if (scoreNum >= 70) {
            scoreClass = 'background: linear-gradient(90deg, #10b981, #34d399);';
            valueColor = '#34d399';
        } else if (scoreNum >= 40) {
            scoreClass = 'background: linear-gradient(90deg, #f59e0b, #fbbf24);';
            valueColor = '#fbbf24';
        } else {
            scoreClass = 'background: linear-gradient(90deg, #ef4444, #f87171);';
            valueColor = '#f87171';
        }

        html += `
            <div class="modern-result-item">
                <div class="modern-result-rank">${p.rank || (i + 1)}</div>
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
    if (event.target == modal) closeModal();
}
</script>

<?php include 'includes/footer.php'; ?>
