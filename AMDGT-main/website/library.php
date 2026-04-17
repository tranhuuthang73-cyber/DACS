<?php
require_once 'includes/config.php';
$pageTitle = 'Thư viện Y tế';
include 'includes/header.php';
?>

<div class="container fade-in" style="max-width: 1200px; margin: 0 auto; padding: 2rem 1rem;">
    <!-- Hero Section -->
    <div class="library-hero">
        <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem;"><i class="fas fa-book-medical" style="color: var(--accent);"></i> Thư Viện Y Tế AMDGT</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 700px; margin: 0 auto;">Trung tâm tri thức tích hợp: Tra cứu tài liệu khoa học thế giới và Trợ lý AI giải đáp bệnh lý chuyên sâu.</p>
    </div>

    <!-- Search Section -->
    <div class="library-search-container">
        <div style="display: flex; gap: 15px; margin-bottom: 1.5rem;">
            <div style="flex: 1; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 18px; top: 18px; color: var(--text-muted);"></i>
                <input type="text" id="lib-search" placeholder="Nhập tên bệnh, tên thuốc hoặc từ khóa y khoa (vd: Alzheimer, Metformin)..." 
                       style="width: 100%; padding: 16px 16px 16px 50px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-size: 1rem;">
            </div>
            <button onclick="searchLibrary()" class="btn btn-primary" style="padding: 0 30px; border-radius: 12px; font-weight: 700;">TÌM KIẾM</button>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted);">Gợi ý:</span>
            <button class="xai-similar-tag" onclick="quickSearch('Diabetes Mellitus')">Diabetes</button>
            <button class="xai-similar-tag" onclick="quickSearch('Alzheimer')">Alzheimer</button>
            <button class="xai-similar-tag" onclick="quickSearch('Hypertension')">Hypertension</button>
            <button class="xai-similar-tag" onclick="quickSearch('Aspirin Mechanism')">Aspirin</button>
        </div>
    </div>

    <div class="library-grid">
        <!-- Main Content: Research Results -->
        <div id="library-content">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-microscope" style="color: var(--accent);"></i> Tài liệu Khoa học (PubMed)
            </h3>
            <div id="pubmed-results">
                <div class="alert alert-info" style="border-radius: 16px; padding: 2rem; text-align: center; background: rgba(99, 102, 241, 0.05);">
                    <i class="fas fa-lightbulb" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: var(--accent);"></i>
                    <p>Chào mừng bạn đến với Thư viện Y tế. Bạn có thể tra cứu thông tin về các bệnh lý đang phân tích trên GNN để hiểu rõ hơn về cơ chế sinh học của chúng.</p>
                </div>
            </div>
        </div>

        <!-- Sidebar: MedBot AI Assistant -->
        <div class="medbot-container">
            <div class="medbot-header">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; color: var(--accent);">
                    <i class="fas fa-robot"></i>
                </div>
                <div>
                    <div style="font-weight: 800; font-size: 1.1rem;">MedBot Assistant</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Trợ lý AI Y tế (Gemini Power)</div>
                </div>
            </div>
            
            <div class="medbot-messages" id="chat-messages">
                <div class="msg msg-bot">
                    Chào bạn! Tôi là MedBot. Tôi có thể giúp bạn giải thích về các bệnh lý, cơ chế thuốc hoặc tóm tắt các nghiên cứu y khoa. Bạn muốn tìm hiểu về điều gì hôm nay?
                </div>
            </div>

            <div class="medbot-input">
                <input type="text" id="chat-input" placeholder="Hỏi MedBot về bệnh hoặc thuốc..." 
                       style="flex: 1; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary);">
                <button onclick="sendMessage()" class="btn btn-primary" style="padding: 10px 15px; border-radius: 10px;"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Info Card Popup -->
<div class="info-popup" id="info-popup">
    <button onclick="closePopup()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fas fa-times"></i></button>
    <div id="popup-content"></div>
</div>

<script>
function quickSearch(term) {
    document.getElementById('lib-search').value = term;
    searchLibrary();
}

function searchLibrary() {
    const q = document.getElementById('lib-search').value.trim();
    if(!q) return;

    const container = document.getElementById('pubmed-results');
    container.innerHTML = '<div style="text-align:center; padding: 3rem;"><div class="ai-scanner" style="width:50px; height:50px; margin: 0 auto 1.5rem;"></div><p>Đang truy vấn CSDL PubMed...</p></div>';

    fetch(`api/pubmed.php?drug=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            if(!data.articles || data.articles.length === 0) {
                container.innerHTML = '<div class="alert alert-warning">Không tìm thấy tài liệu liên quan trên PubMed.</div>';
                return;
            }

            let html = '';
            data.articles.forEach(a => {
                html += `
                    <div class="research-card">
                        <span class="research-tag">PMID: ${a.pmid}</span>
                        <h4 style="margin-bottom: 0.8rem; line-height: 1.4; color: var(--text-primary); font-weight: 700;">${a.title}</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            <i class="fas fa-user-edit"></i> ${a.authors} <br>
                            <i class="fas fa-journal-whills"></i> <em>${a.journal}</em> (${a.year})
                        </p>
                        <div style="display:flex; justify-content: space-between; align-items: center;">
                            <a href="${a.url}" target="_blank" class="btn btn-sm btn-outline" style="border-radius: 8px;"><i class="fas fa-external-link-alt"></i> Xem chi tiết</a>
                            <button onclick="askBotAbout('${a.title.replace(/'/g, "\\'")}')" class="btn btn-sm btn-outline" style="border-radius: 8px; color: var(--accent);"><i class="fas fa-comment-medical"></i> Hỏi AI về bài này</button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        });
}

// MedBot Chat Logic
let chatHistory = [];

function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if(!msg) return;

    appendMessage('user', msg);
    input.value = '';

    fetch('api/gemini_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ message: msg, history: chatHistory })
    })
    .then(r => r.json())
    .then(data => {
        appendMessage('bot', data.reply);
        chatHistory.push({role: 'user', text: msg});
        chatHistory.push({role: 'model', text: data.reply});
    });
}

function askBotAbout(title) {
    const msg = "Hãy tóm tắt nội dung chính và tầm quan trọng của nghiên cứu này: " + title;
    appendMessage('user', "Tóm tắt giúp tôi nghiên cứu: " + title.substring(0, 50) + "...");
    
    fetch('api/gemini_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ message: msg, history: chatHistory })
    })
    .then(r => r.json())
    .then(data => {
        appendMessage('bot', data.reply);
        chatHistory.push({role: 'user', text: msg});
        chatHistory.push({role: 'model', text: data.reply});
    });
}

function appendMessage(role, text) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = `msg msg-${role}`;
    // Simple markdown-to-html for AI responses
    div.innerHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Allow Enter to send message
document.getElementById('chat-input').addEventListener('keypress', function(e) {
    if(e.key === 'Enter') sendMessage();
});
</script>

<?php include 'includes/footer.php'; ?>
