(function () {
    const companySlug = window.COMPANY_SLUG || 'capital-renovation';
    const endpoint = window.CHAT_API_ENDPOINT || 'http://localhost:8000/api/public-chat';

    const chatBox = document.createElement('div');
    chatBox.innerHTML = `
        <div style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            height: 420px;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            font-family: Arial, sans-serif;
            background: white;
            color: black;
            z-index: 9999;
            overflow: hidden;
            border: 1px solid #ddd;
        ">
            <div style="background: #4f46e5; color: white; padding: 12px; font-weight: bold;">
                Chat with us
            </div>
            <div id="chat-log" style="
                flex: 1;
                padding: 10px;
                overflow-y: auto;
                background: #f9f9f9;
                font-size: 14px;
            "></div>
            <div style="padding: 10px; border-top: 1px solid #ddd;">
                <textarea id="chat-input" rows="2" placeholder="Type your message..." style="
                    width: 100%;
                    padding: 8px;
                    border-radius: 8px;
                    border: 1px solid #ccc;
                    resize: none;
                    font-size: 14px;
                    box-sizing: border-box;
                "></textarea>
                <button id="send-btn" style="
                    margin-top: 8px;
                    width: 100%;
                    background: #4f46e5;
                    color: white;
                    border: none;
                    padding: 10px;
                    border-radius: 8px;
                    font-size: 14px;
                    cursor: pointer;
                ">Send</button>
            </div>
        </div>
    `;
    document.body.appendChild(chatBox);

    const log = chatBox.querySelector('#chat-log');
    const input = chatBox.querySelector('#chat-input');
    const sendBtn = chatBox.querySelector('#send-btn');

    function appendMessage(sender, text) {
        const msg = document.createElement('div');
        msg.style.marginBottom = '10px';
        msg.style.padding = '8px 12px';
        msg.style.borderRadius = '12px';
        msg.style.maxWidth = '80%';
        msg.style.wordBreak = 'break-word';
        msg.style.whiteSpace = 'pre-wrap';

        if (sender === 'You') {
            msg.style.alignSelf = 'flex-end';
            msg.style.backgroundColor = '#e0e7ff';
            msg.style.marginLeft = 'auto';
        } else {
            msg.style.backgroundColor = '#f3f4f6';
            msg.style.marginRight = 'auto';
        }

        msg.innerHTML = `<strong>${sender}:</strong><br>${text}`;
        log.appendChild(msg);
        log.scrollTop = log.scrollHeight;
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        const message = input.value.trim();
        if (!message) return;

        appendMessage('You', message);
        input.value = '';

        fetch(`${endpoint}/${companySlug}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.reply) {
                    appendMessage('Bot', data.reply);
                } else {
                    appendMessage('Bot', '<em>No response</em>');
                }
            })
            .catch(() => {
                appendMessage('Bot', '<em>Error contacting server</em>');
            });
    }

    // Optional: Welcome message
    appendMessage('Bot', "Hi! I'm here to help you 😊");

})();
