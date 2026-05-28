<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Vui lòng đăng nhập'], 401);
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'delete_history') {
        $id = $input['id'] ?? 0;
        
        // Chắc chắn chỉ xóa lịch sử của chính user đang đăng nhập
        $stmt = $db->prepare("DELETE FROM predictions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Không tìm thấy lịch sử hoặc bạn không có quyền xóa'], 404);
        }
    } elseif ($action === 'clear_all_history') {
        $stmt = $db->prepare("DELETE FROM predictions WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Hành động không hợp lệ'], 400);
    }
}
