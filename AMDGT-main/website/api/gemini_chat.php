<?php
/**
 * Gemini AI Chat Proxy
 * PHP proxy gọi Google Gemini API cho Trợ lý ảo Y tế
 * Tách biệt hoàn toàn với AI Server (GNN predictions)
 */
require_once __DIR__ . '/../includes/config.php';

// Không cần đăng nhập cũng dùng được chat (hoặc bật nếu cần bảo mật)
// if (!isLoggedIn()) { jsonResponse(['error' => 'Chưa đăng nhập'], 401); }

// ============ GEMINI API KEY ============
// Lấy API Key miễn phí tại: https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
// ========================================

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (empty($message)) {
    jsonResponse(['error' => 'Tin nhắn trống'], 400);
}

if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    // Demo mode khi chưa có API Key - trả lời offline thông minh
    $demoResponse = getDemoResponse($message);
    jsonResponse(['reply' => $demoResponse, 'mode' => 'demo']);
}

// ============ GỌI GEMINI API ============
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

// System prompt Y tế chuyên dụng
$systemInstruction = "Bạn là Trợ lý Y tế AI chuyên nghiệp tên MedBot, được tích hợp trong hệ thống dự đoán liên kết Thuốc-Bệnh AMDGT. " .
    "Nhiệm vụ của bạn:\n" .
    "1. Trả lời các câu hỏi về thuốc: tác dụng phụ, liều dùng, tương tác thuốc, cơ chế hoạt động.\n" .
    "2. Trả lời các câu hỏi về bệnh: triệu chứng, nguyên nhân, phương pháp điều trị.\n" .
    "3. Giải thích các khái niệm y khoa một cách dễ hiểu.\n" .
    "4. Luôn nhắc nhở người dùng tham khảo ý kiến bác sĩ trước khi dùng thuốc.\n" .
    "5. Trả lời bằng tiếng Việt, ngắn gọn, dễ hiểu (tối đa 200 từ).\n" .
    "6. Sử dụng emoji phù hợp cho sinh động.\n" .
    "7. Nếu câu hỏi không liên quan đến y tế, nhẹ nhàng từ chối và hướng dẫn hỏi về y tế.\n" .
    "LƯU Ý QUAN TRỌNG: Bạn KHÔNG phải bác sĩ. Luôn khuyên người dùng hỏi bác sĩ chuyên khoa.";

// Build conversation contents
$contents = [];

// Thêm lịch sử chat
foreach ($history as $msg) {
    $contents[] = [
        'role' => $msg['role'] === 'user' ? 'user' : 'model',
        'parts' => [['text' => $msg['text']]]
    ];
}

// Thêm tin nhắn hiện tại
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $message]]
];

$requestBody = [
    'system_instruction' => [
        'parts' => [['text' => $systemInstruction]]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.9,
        'maxOutputTokens' => 800
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jsonResponse(['error' => 'Lỗi kết nối Gemini API: ' . $curlError], 500);
}

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    // Fallback to demo mode
    $demoResponse = getDemoResponse($message);
    jsonResponse(['reply' => $demoResponse, 'mode' => 'demo_fallback']);
}

$reply = $data['candidates'][0]['content']['parts'][0]['text'];
jsonResponse(['reply' => $reply, 'mode' => 'gemini']);


// ============ DEMO RESPONSES (Offline Mode) ============
function getDemoResponse($message) {
    $msg = mb_strtolower($message, 'UTF-8');
    
    // Tác dụng phụ
    if (strpos($msg, 'tác dụng phụ') !== false || strpos($msg, 'side effect') !== false) {
        if (strpos($msg, 'aspirin') !== false) {
            return "💊 **Tác dụng phụ của Aspirin:**\n\n" .
                "• Đau dạ dày, buồn nôn, ợ nóng\n" .
                "• Xuất huyết tiêu hóa (dùng lâu dài)\n" .
                "• Ù tai (liều cao)\n" .
                "• Phản ứng dị ứng (hiếm gặp)\n" .
                "• Tăng nguy cơ chảy máu\n\n" .
                "⚠️ *Không dùng cho trẻ < 16 tuổi (nguy cơ hội chứng Reye). Luôn tham khảo ý kiến bác sĩ!*";
        }
        if (strpos($msg, 'paracetamol') !== false || strpos($msg, 'acetaminophen') !== false) {
            return "💊 **Tác dụng phụ của Paracetamol:**\n\n" .
                "• Thông thường an toàn ở liều điều trị\n" .
                "• Buồn nôn nhẹ, đau bụng (hiếm)\n" .
                "• ⚠️ **Tổn thương gan nghiêm trọng** nếu quá liều (>4g/ngày)\n" .
                "• Phản ứng da (rất hiếm)\n\n" .
                "⚠️ *Không dùng quá 4g/ngày. Tránh dùng cùng rượu bia. Luôn tham khảo bác sĩ!*";
        }
        return "💊 Để trả lời chính xác về tác dụng phụ, bạn hãy cho tôi biết tên thuốc cụ thể nhé! \n\n" .
            "VD: *\"Tác dụng phụ của Aspirin là gì?\"*\n\n" .
            "⚠️ *Lưu ý: Tôi là AI trợ lý, không phải bác sĩ. Hãy tham khảo ý kiến chuyên gia y tế!*";
    }
    
    // Tương tác thuốc
    if (strpos($msg, 'tương tác') !== false || strpos($msg, 'interaction') !== false) {
        return "💊 **Tương tác thuốc** là khi hai hay nhiều thuốc dùng cùng lúc gây ra tác dụng khác biệt.\n\n" .
            "Các loại tương tác:\n" .
            "• 🔴 **Đối kháng**: thuốc triệt tiêu tác dụng của nhau\n" .
            "• 🟡 **Hiệp đồng**: tăng tác dụng (có thể nguy hiểm)\n" .
            "• 🟢 **Bổ sung**: kết hợp tốt cho điều trị\n\n" .
            "Hãy sử dụng tính năng **Dự đoán** của hệ thống AMDGT để kiểm tra!\n\n" .
            "⚠️ *Luôn thông báo cho bác sĩ tất cả thuốc bạn đang dùng!*";
    }
    
    // Liều dùng
    if (strpos($msg, 'liều') !== false || strpos($msg, 'dose') !== false || strpos($msg, 'uống') !== false) {
        return "💊 Liều dùng thuốc phụ thuộc vào nhiều yếu tố:\n\n" .
            "• 👤 Tuổi, cân nặng, giới tính\n" .
            "• 🏥 Tình trạng bệnh lý\n" .
            "• 💊 Các thuốc đang dùng kèm\n" .
            "• 🔬 Chức năng gan, thận\n\n" .
            "Bạn cần hỏi **bác sĩ hoặc dược sĩ** để được tư vấn liều dùng chính xác nhé!\n\n" .
            "⚠️ *Không tự ý thay đổi liều dùng mà không có sự hướng dẫn của bác sĩ!*";
    }

    // Greeting
    if (strpos($msg, 'xin chào') !== false || strpos($msg, 'hello') !== false || strpos($msg, 'hi') !== false || strpos($msg, 'chào') !== false) {
        return "👋 Xin chào! Tôi là **MedBot** - Trợ lý AI Y tế của hệ thống AMDGT.\n\n" .
            "Tôi có thể giúp bạn:\n" .
            "• 💊 Tìm hiểu về tác dụng phụ của thuốc\n" .
            "• 🔬 Giải thích tương tác thuốc\n" .
            "• 🏥 Trả lời câu hỏi y khoa cơ bản\n\n" .
            "Hãy hỏi tôi bất cứ điều gì! 😊";
    }
    
    // Default
    return "🤖 Cảm ơn câu hỏi của bạn!\n\n" .
        "Hệ thống đang chạy ở **chế độ Demo** (chưa có API Key Gemini).\n\n" .
        "Để kích hoạt đầy đủ, hãy:\n" .
        "1. Lấy API Key miễn phí tại [Google AI Studio](https://aistudio.google.com/app/apikey)\n" .
        "2. Thêm vào file `api/gemini_chat.php`\n\n" .
        "Hiện tại bạn có thể thử hỏi:\n" .
        "• *\"Tác dụng phụ của Aspirin?\"*\n" .
        "• *\"Tương tác thuốc là gì?\"*\n" .
        "• *\"Liều dùng Paracetamol?\"*\n\n" .
        "⚠️ *Luôn tham khảo ý kiến bác sĩ!*";
}
?>
