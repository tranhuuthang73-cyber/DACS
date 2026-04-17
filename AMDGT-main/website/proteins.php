<?php
require_once 'includes/config.php';
$pageTitle = 'Protein Explorer';
include 'includes/header.php';

$dataset = $_GET['dataset'] ?? 'C-dataset';
$search = $_GET['q'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$db = getDB();

// Fetch proteins
if ($search) {
    $stmt = $db->prepare("SELECT * FROM proteins WHERE dataset = ? AND (name LIKE ? OR protein_id LIKE ?) LIMIT ?, ?");
    $stmt->bindValue(1, $dataset);
    $stmt->bindValue(2, "%$search%");
    $stmt->bindValue(3, "%$search%");
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->bindValue(5, $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $proteins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM proteins WHERE dataset = ? AND (name LIKE ? OR protein_id LIKE ?)");
    $countStmt->execute([$dataset, "%$search%", "%$search%"]);
    $total = $countStmt->fetchColumn();
} else {
    $stmt = $db->prepare("SELECT * FROM proteins WHERE dataset = ? LIMIT ?, ?");
    $stmt->bindValue(1, $dataset);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $proteins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = $db->prepare("SELECT COUNT(*) FROM proteins WHERE dataset = ?");
    $total->execute([$dataset]);
    $total = $total->fetchColumn();
}

$totalPages = ceil($total / $perPage);
?>

<div class="container fade-in" style="max-width: 1100px; margin: 0 auto; padding: 2rem 1rem;">
    <div class="library-hero" style="text-align: center; margin-bottom: 3rem;">
        <h1 style="font-size: 2.8rem; font-weight: 800; margin-bottom: 1rem;"><i class="fas fa-dna" style="color: #ec4899;"></i> Trung Tâm Protein</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 700px; margin: 0 auto;">Khám phá các Protein mục tiêu và mạng lưới tương tác sinh học trong hệ thống dự đoán GNN.</p>
    </div>

    <!-- Search & Filters -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
        <form method="GET" action="proteins.php" style="display: flex; gap: 15px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 300px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 15px; top: 15px; color: var(--text-muted);"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập ID Protein hoặc tên (vd: P01137)..." 
                       style="width: 100%; padding: 12px 12px 12px 45px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary);">
            </div>
            <select name="dataset" class="form-select" style="width: auto; min-width: 150px;">
                <option value="C-dataset" <?= $dataset === 'C-dataset' ? 'selected' : '' ?>>C-Dataset</option>
                <option value="B-dataset" <?= $dataset === 'B-dataset' ? 'selected' : '' ?>>B-Dataset</option>
                <option value="F-dataset" <?= $dataset === 'F-dataset' ? 'selected' : '' ?>>F-Dataset</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Lọc Kết Quả</button>
        </form>
    </div>

    <?php if (empty($proteins)): ?>
        <div class="alert alert-info">Không tìm thấy protein nào khớp với truy vấn của bạn.</div>
    <?php else: ?>
        <div class="results-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($proteins as $p): ?>
                <div class="card result-item" style="display: flex; flex-direction: column; gap: 10px; padding: 1.5rem; transition: transform 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="result-badge badge-new" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">Index #<?= $p['idx'] ?></span>
                        <i class="fas fa-dna" style="color: rgba(236, 72, 153, 0.5);"></i>
                    </div>
                    <h3 style="font-size: 1.15rem; margin: 0.5rem 0;"><?= htmlspecialchars($p['protein_id']) ?></h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; flex: 1;"><?= htmlspecialchars($p['name']) ?></p>
                    
                    <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <button class="btn btn-outline btn-sm" onclick="showProteinDetails('<?= $p['protein_id'] ?>', <?= $p['idx'] ?>)" style="width: 100%; justify-content: center;">
                            <i class="fas fa-info-circle"></i> Xem Tương Tác
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="display: flex; justify-content: center; gap: 8px; margin-top: 3rem;">
                <?php for ($i = 1; $i <= $totalPages; $i++): 
                    if ($i > 5 && $i < $totalPages - 5 && abs($i - $page) > 2) {
                        if ($i === 6 || $i === $totalPages - 6) echo '<span style="color: var(--text-muted)">...</span>';
                        continue;
                    }
                ?>
                    <a href="proteins.php?dataset=<?= $dataset ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" 
                       class="tab <?= $page === $i ? 'active' : '' ?>" style="text-decoration: none; padding: 8px 15px;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Details Modal (Using existing styles) -->
<div id="proteinModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div class="card" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 600px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 id="modalTitle" style="margin:0;"><i class="fas fa- dna"></i> Chi tiết Protein</h2>
            <button onclick="closeModal()" style="background:none; border:none; color: var(--text-muted); cursor:pointer; font-size: 1.5rem;"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalBody" style="max-height: 400px; overflow-y: auto;">
            <!-- Content loaded via JS -->
            <div class="loading">Đang tải dữ liệu tương tác...</div>
        </div>
    </div>
</div>

<script>
function showProteinDetails(id, idx) {
    const modal = document.getElementById('proteinModal');
    const body = document.getElementById('modalBody');
    const title = document.getElementById('modalTitle');
    
    title.innerHTML = `<i class="fas fa-dna" style="color: #ec4899;"></i> Protein: ${id}`;
    modal.style.display = 'block';
    body.innerHTML = '<div class="loading">Đang phân tích mạng lưới tương tác...</div>';
    
    // Simulate fetching interactions (In a real app, this would be an API call)
    // For now, we'll explain what features ESM provides
    setTimeout(() => {
        body.innerHTML = `
            <div style="background: rgba(99, 102, 241, 0.05); padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid #ec4899;">
                <h4 style="margin-bottom: 5px;">Đặc trưng Sinh học (Deep Learning)</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Mô hình sử dụng vector **ESM-2 (1280 dimensions)** để mã hóa cấu trúc không gian của chuỗi acid amin của protein này.</p>
            </div>
            <h4 style="margin: 1.5rem 0 1rem; font-size: 1rem;">Liên kết Dataset (Ground Truth):</h4>
            <div id="interaction-list">
                <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 2rem;">
                    Đang truy vấn các cạnh nối (Edges) từ bộ dữ liệu đồ thị... 
                    <br><br>
                    <span style="color: var(--accent-light)">Gợi ý: Protein này đóng vai trò là "nút trung gian" trong mô hình GNN để tính toán xác suất liên kết giữa thuốc và bệnh mục tiêu.</span>
                </p>
            </div>
        `;
    }, 600);
}

function closeModal() {
    document.getElementById('proteinModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('proteinModal')) {
        closeModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
