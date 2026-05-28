    </main>
    <footer class="footer">
        <div class="footer-content">
            <p>© 2026 AMDGT - Attention-aware Multi-modal Dual Graph Transformer</p>
            <p class="footer-sub">Drug-Disease Association Prediction with GNN + Persistent Homology</p>
        </div>
    </footer>

    <!-- ============================================
         FLOATING AI MEDICAL CHAT WIDGET
         ============================================ -->
    <button class="ai-chat-fab" id="ai-chat-fab" onclick="toggleAIChat()" title="💊 Trợ lý AI Y tế">
        <i class="fas fa-robot"></i>
        <span class="ai-chat-badge">AI</span>
    </button>

    <div class="ai-chat-box" id="ai-chat-box">
        <div class="chat-header">
            <div class="chat-header-avatar">🤖</div>
            <div class="chat-header-info">
                <h4>MedBot - Trợ Lý AI Y Tế</h4>
                <span>Powered by Gemini AI</span>
            </div>
            <div class="chat-header-dot"></div>
        </div>
        
        <div class="chat-messages" id="chat-messages">
            <div class="chat-msg msg-system">🔬 Trợ lý AI chuyên y tế - Không thay thế bác sĩ</div>
            <div class="chat-msg msg-ai">
                👋 Xin chào! Tôi là <strong>MedBot</strong> - Trợ lý AI Y tế của AMDGT.<br><br>
                Tôi có thể giúp bạn:<br>
                • 💊 Tác dụng phụ của thuốc<br>
                • 🔬 Tương tác thuốc<br>
                • 🏥 Câu hỏi y khoa cơ bản<br><br>
                Hãy hỏi tôi bất cứ điều gì! 😊
            </div>
        </div>

        <div class="chat-suggestions" id="chat-suggestions">
            <button class="chat-suggest-btn" onclick="quickAsk('Tác dụng phụ của Aspirin?')">💊 Aspirin</button>
            <button class="chat-suggest-btn" onclick="quickAsk('Tương tác thuốc là gì?')">🔬 Tương tác</button>
            <button class="chat-suggest-btn" onclick="quickAsk('Paracetamol uống bao nhiêu?')">💊 Paracetamol</button>
            <button class="chat-suggest-btn" onclick="quickAsk('GNN là gì?')">🧠 GNN</button>
        </div>

        <div class="chat-input-area">
            <input type="text" id="chat-input" placeholder="Hỏi về thuốc, bệnh, tác dụng phụ..." 
                   onkeydown="if(event.key==='Enter')sendChatMessage()">
            <button class="chat-send-btn" id="chat-send-btn" onclick="sendChatMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
    // ============ AI CHAT WIDGET LOGIC ============
    let chatOpen = false;
    let chatHistory = [];
    let chatBusy = false;

    function toggleAIChat() {
        chatOpen = !chatOpen;
        const fab = document.getElementById('ai-chat-fab');
        const box = document.getElementById('ai-chat-box');
        
        if (chatOpen) {
            fab.classList.add('chat-open');
            fab.innerHTML = '<i class="fas fa-times"></i>';
            box.classList.add('chat-visible');
            document.getElementById('chat-input').focus();
        } else {
            fab.classList.remove('chat-open');
            fab.innerHTML = '<i class="fas fa-robot"></i><span class="ai-chat-badge">AI</span>';
            box.classList.remove('chat-visible');
        }
    }

    function quickAsk(text) {
        document.getElementById('chat-input').value = text;
        sendChatMessage();
    }

    function addChatMessage(content, type) {
        const container = document.getElementById('chat-messages');
        const msg = document.createElement('div');
        msg.className = `chat-msg msg-${type}`;
        
        // Format markdown-like bold text
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Format markdown links
        content = content.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" style="color:var(--accent-light);">$1</a>');
        // Format line breaks
        content = content.replace(/\n/g, '<br>');
        
        msg.innerHTML = content;
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
    }

    function showTyping() {
        const container = document.getElementById('chat-messages');
        const typing = document.createElement('div');
        typing.className = 'chat-typing';
        typing.id = 'chat-typing-indicator';
        typing.innerHTML = '<span></span><span></span><span></span>';
        container.appendChild(typing);
        container.scrollTop = container.scrollHeight;
    }

    function hideTyping() {
        const el = document.getElementById('chat-typing-indicator');
        if (el) el.remove();
    }

    function sendChatMessage() {
        if (chatBusy) return;
        
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        if (!message) return;
        
        input.value = '';
        chatBusy = true;
        document.getElementById('chat-send-btn').disabled = true;
        
        // Add user message
        addChatMessage(message, 'user');
        chatHistory.push({ role: 'user', text: message });
        
        // Hide suggestions after first message
        document.getElementById('chat-suggestions').style.display = 'none';
        
        // Show typing indicator
        showTyping();
        
        // Call API
        fetch('api/gemini_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                message: message,
                history: chatHistory.slice(-10) // Last 10 messages for context
            })
        })
        .then(r => r.json())
        .then(data => {
            hideTyping();
            if (data.error) {
                addChatMessage('⚠️ ' + data.error, 'ai');
            } else {
                addChatMessage(data.reply, 'ai');
                chatHistory.push({ role: 'model', text: data.reply });
                
                // Show mode indicator
                if (data.mode === 'demo' || data.mode === 'demo_fallback') {
                    const container = document.getElementById('chat-messages');
                    const badge = document.createElement('div');
                    badge.className = 'chat-msg msg-system';
                    badge.textContent = '📡 Chế độ Demo - Thêm API Key Gemini để mở khóa đầy đủ';
                    container.appendChild(badge);
                    container.scrollTop = container.scrollHeight;
                }
            }
        })
        .catch(err => {
            hideTyping();
            addChatMessage('❌ Lỗi kết nối: ' + err.message, 'ai');
        })
        .finally(() => {
            chatBusy = false;
            document.getElementById('chat-send-btn').disabled = false;
            input.focus();
        });
    }
    </script>

    <script src="assets/js/app.js"></script>
</body>
</html>
