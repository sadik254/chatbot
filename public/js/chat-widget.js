(function() {
    const companySlug = window.COMPANY_SLUG || 'capital-renovation';

    const chatBox = document.createElement('div');
    chatBox.innerHTML = `
        <div style="position:fixed;bottom:20px;right:20px;width:300px;border:1px solid #ccc;padding:10px;background:white;z-index:9999;">
            <div><strong>Chat with us</strong></div>
            <div id="chat-log" style="height:150px;overflow:auto;"></div>
            <textarea id="chat-input" placeholder="Type..." style="width:100%;"></textarea>
            <button id="send-btn">Send</button>
        </div>
    `;
    document.body.appendChild(chatBox);

    const log = document.getElementById('chat-log');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');

    sendBtn.onclick = function () {
        const message = input.value;
        if (!message) return;
        log.innerHTML += `<div><strong>You:</strong> ${message}</div>`;
        input.value = '';

        fetch(`https://yourdomain.com/api/public-chat/${companySlug}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        })
        .then(res => res.json())
        .then(data => {
            log.innerHTML += `<div><strong>Bot:</strong> ${data.reply}</div>`;
            log.scrollTop = log.scrollHeight;
        }).catch(err => {
            log.innerHTML += `<div><em>Error getting reply</em></div>`;
        });
    };
})();
