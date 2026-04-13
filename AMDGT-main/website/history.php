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
$stmt = $db->prepare("SELECT * FROM predictions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="max-width: 900px; margin: 0 auto;">
    <h1 class="section-title fade-in"><i class="fas fa-history"></i> Lịch sử tra cứu</h1>
    <p class="section-subtitle fade-in">Các lần dự đoán gần đây của bạn</p>

    <?php if (empty($history)): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Chưa có lịch sử tra cứu nào.</div>
    <?php else: ?>
        <div class="table-container fade-in">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Loại</th>
                        <th>Truy vấn</th>
                        <th>Kết quả</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $h): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?php if ($h['query_type'] === 'drug_to_disease'): ?>
                                <span style="color: var(--accent-light);"><i class="fas fa-pills"></i> Thuốc→Bệnh</span>
                            <?php else: ?>
                                <span style="color: var(--info);"><i class="fas fa-virus"></i> Bệnh→Thuốc</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($h['query_value']) ?></strong></td>
                        <td>
                            <?php 
                            $results = json_decode($h['results'], true);
                            $count = is_array($results) ? count($results) : 0;
                            echo "$count kết quả";
                            ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;">
                            <?= $h['created_at'] ?>
                        </td>
                        <td>
                            <?php if ($count > 0): ?>
                            <button class="btn btn-sm btn-outline" 
                                    onclick='viewDetails(<?= json_encode($h['query_value']) ?>, <?= json_encode($h['query_type']) ?>, <?= htmlspecialchars($h['results'], ENT_QUOTES, 'UTF-8') ?>)'>
                                <i class="fas fa-eye"></i> Chi tiết
                            </button>
                            <?php endif; ?>
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

<style>
/* Custom scrollbar for modal */
#modalContent::-webkit-scrollbar { width: 6px; }
#modalContent::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb { background: var(--accent-dark); border-radius: 10px; }
#modalContent::-webkit-scrollbar-thumb:hover { background: var(--accent-light); }
</style>

<script>
function viewDetails(queryValue, queryType, results) {
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const modal = document.getElementById('detailsModal');
    const modalBox = modal.querySelector('.modal-content');
    
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
