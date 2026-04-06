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
<div id="detailsModal" class="modal-backdrop" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(10, 15, 30, 0.8); backdrop-filter: blur(8px); opacity: 0; transition: opacity 0.3s ease;">
    <div class="modal-content" style="background-color: var(--bg-card); margin: 5vh auto; border-radius: 16px; width: 90%; max-width: 700px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 40px rgba(0,0,0,0.5); transform: translateY(-20px); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        
        <!-- Modal Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2); border-top-left-radius: 16px; border-top-right-radius: 16px;">
            <div id="modalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 12px;">
                Chi tiết dự đoán
            </div>
            <button onclick="closeModal()" style="background: rgba(255,255,255,0.1); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 1.2rem;" onmouseover="this.style.background='rgba(255,60,60,0.8)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 25px;">
            <div class="card" style="margin: 0; box-shadow: none; border: 1px solid rgba(255,255,255,0.05);">
                <div class="results-list" id="modalContent" style="max-height: 55vh; overflow-y: auto; padding-right: 5px;">
                    <!-- Chi tiết AJAX -->
                </div>
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
            <div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: normal; margin-bottom: 2px;">Dự đoán bệnh tiềm năng cho thuốc</div>
                <div style="color: var(--text-primary);">${queryValue}</div>
            </div>`;
    } else {
        title.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(74, 0, 224, 0.2); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="fas fa-virus"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: normal; margin-bottom: 2px;">Dự đoán thuốc điều trị cho bệnh</div>
                <div style="color: var(--text-primary);">${queryValue}</div>
            </div>`;
    }
    
    // Render kết quả sử dụng đúng CSS classes từ style.css
    let html = '';
    const type = queryType === 'drug_to_disease' ? 'disease' : 'drug';
    
    results.forEach(p => {
        const name = type === 'disease' ? (p.disease_name || `Disease #${p.disease_idx}`) : (p.drug_name || `Drug #${p.drug_idx}`);
        const id = type === 'disease' ? (p.disease_id || '') : (p.drug_id || '');
        const scorePct = Math.min(p.score * 100, 100).toFixed(1);
        const badge = p.is_known ? '<span class="result-badge badge-known">Đã biết</span>' : '<span class="result-badge badge-new">Mới</span>';
        
        html += `
            <div class="result-item fade-in">
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
    
    content.innerHTML = html;
    
    // Animation mở modal
    modal.style.display = 'block';
    setTimeout(() => {
        modal.style.opacity = '1';
        modalBox.style.transform = 'translateY(0)';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('detailsModal');
    const modalBox = modal.querySelector('.modal-content');
    
    modal.style.opacity = '0';
    modalBox.style.transform = 'translateY(-20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); // 300ms match transition time
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
