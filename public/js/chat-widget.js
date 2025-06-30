(function () {
  const companySlug = window.COMPANY_SLUG || "capital-renovation";
  const endpoint =
    window.CHAT_API_ENDPOINT || "http://localhost:8000/api/public-chat";

  // Theme configuration object
  const themeConfig = {
    colors: {
      primary: "#001659",
      secondary: "#E85D3D",
      background: "#FFFFFF",
      messageBackground: "#F8F9FA",
      userMessageBackground: "#3B4A6B",
      border: "#E5E7EB",
      text: "#374151",
      textLight: "#6B7280",
      white: "#FFFFFF",
    },
    spacing: {
      small: "8px",
      medium: "12px",
      large: "16px",
      xlarge: "20px",
    },
    borderRadius: {
      small: "8px",
      medium: "12px",
      large: "16px",
      xlarge: "20px",
    },
    shadows: {
      widget: "0 10px 25px rgba(0, 0, 0, 0.15)",
      message: "0 1px 3px rgba(0, 0, 0, 0.1)",
      chathead: "0 4px 12px rgba(0, 0, 0, 0.2)",
    },
    input: {
      minHeight: "44px",
      maxHeight: "120px",
    },
  };

  // Create chathead
  const chatHead = document.createElement("div");
  chatHead.innerHTML = `
    <div id="chat-head" style="
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 60px;
      height: 60px;
      background: ${themeConfig.colors.primary};
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: ${themeConfig.colors.white};
      box-shadow: ${themeConfig.shadows.chathead};
      z-index: 9999;
      transition: all 0.3s ease;
      user-select: none;
    " onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
      💬
    </div>
  `;

  // Create chatbox
  const chatBox = document.createElement("div");
  chatBox.innerHTML = `
        <div id="chat-widget" style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 360px;
            height: 500px;
            display: none;
            flex-direction: column;
            border-radius: ${themeConfig.borderRadius.xlarge};
            box-shadow: ${themeConfig.shadows.widget};
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: ${themeConfig.colors.background};
            color: ${themeConfig.colors.text};
            z-index: 10000;
            overflow: hidden;
            border: 1px solid ${themeConfig.colors.border};
            transform: scale(0.8) translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
        ">
            <!-- Header -->
            <div style="
                background: ${themeConfig.colors.primary}; 
                color: ${themeConfig.colors.white}; 
                padding: ${themeConfig.spacing.large}; 
                font-weight: 600;
                font-size: 16px;
                display: flex;
                align-items: center;
                gap: ${themeConfig.spacing.medium};
            ">
                <div style="
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background: ${themeConfig.colors.primary};
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 18px;
                ">🤖</div>
                <div>
                    <div>Bookzo.io</div>
                    <div style="
                        font-size: 12px;
                        font-weight: normal;
                        opacity: 0.8;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        margin-top: 2px;
                    ">
                        <div style="
                            width: 8px;
                            height: 8px;
                            border-radius: 50%;
                            background: #10B981;
                        "></div>
                        Online
                    </div>
                </div>
                <button id="close-chat" style="
                    margin-left: auto;
                    background: none;
                    border: none;
                    color: ${themeConfig.colors.white};
                    font-size: 30px;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    opacity: 0.8;
                " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">×</button>
            </div>

            <!-- Messages Area -->
            <div id="chat-log" style="
                flex: 1;
                padding: ${themeConfig.spacing.large};
                overflow-y: auto;
                background: ${themeConfig.colors.background};
                font-size: 14px;
                line-height: 1.5;
            "></div>

            <!-- Typing Indicator -->
            <div id="typing-indicator" style="
                display: none;
                padding: ${themeConfig.spacing.medium} ${themeConfig.spacing.large};
                background: ${themeConfig.colors.background};
                font-size: 12px;
                color: ${themeConfig.colors.textLight};
                font-style: italic;
            ">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="display: flex; gap: 3px;">
                        <div style="
                            width: 6px;
                            height: 6px;
                            background: ${themeConfig.colors.textLight};
                            border-radius: 50%;
                            animation: typing-bounce 1.4s infinite ease-in-out both;
                        "></div>
                        <div style="
                            width: 6px;
                            height: 6px;
                            background: ${themeConfig.colors.textLight};
                            border-radius: 50%;
                            animation: typing-bounce 1.4s infinite ease-in-out both;
                            animation-delay: 0.16s;
                        "></div>
                        <div style="
                            width: 6px;
                            height: 6px;
                            background: ${themeConfig.colors.textLight};
                            border-radius: 50%;
                            animation: typing-bounce 1.4s infinite ease-in-out both;
                            animation-delay: 0.32s;
                        "></div>
                    </div>
                    <span>Bookzo is typing...</span>
                </div>
            </div>

            <!-- Input Area -->
            <div style="
                padding: ${themeConfig.spacing.large}; 
                border-top: 1px solid ${themeConfig.colors.border};
                background: ${themeConfig.colors.background};
            ">
                <div style="
                    display: flex;
                    gap: ${themeConfig.spacing.small};
                    align-items: flex-end;
                ">
                    <textarea id="chat-input" rows="1" placeholder="Type Something..." style="
                        flex: 1;
                        padding: ${themeConfig.spacing.medium};
                        border-radius: ${themeConfig.borderRadius.large};
                        border: 1px solid ${themeConfig.colors.border};
                        resize: none;
                        font-size: 14px;
                        font-family: inherit;
                        box-sizing: border-box;
                        outline: none;
                        background: ${themeConfig.colors.background};
                        color: ${themeConfig.colors.text};
                        min-height: ${themeConfig.input.minHeight};
                        max-height: ${themeConfig.input.maxHeight};
                        overflow-y: auto;
                    "></textarea>
                    <button id="send-btn" style="
                        width: 44px;
                        height: 44px;
                        background: ${themeConfig.colors.primary};
                        color: ${themeConfig.colors.white};
                        border: none;
                        border-radius: 50%;
                        font-size: 16px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"><span>
                   <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"> <?xml version="1.0" encoding="utf-8"?><svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 122.88 103.44" style="enable-background:new 0 0 122.88 103.44" xml:space="preserve"><g><path d="M69.49,102.77L49.8,84.04l-20.23,18.27c-0.45,0.49-1.09,0.79-1.8,0.79c-1.35,0-2.44-1.09-2.44-2.44V60.77L0.76,37.41 c-0.98-0.93-1.01-2.47-0.09-3.45c0.31-0.33,0.7-0.55,1.11-0.67l0,0l118-33.2c1.3-0.36,2.64,0.39,3.01,1.69 c0.19,0.66,0.08,1.34-0.24,1.89l-49.2,98.42c-0.6,1.2-2.06,1.69-3.26,1.09C69.86,103.07,69.66,102.93,69.49,102.77L69.49,102.77 L69.49,102.77z M46.26,80.68L30.21,65.42v29.76L46.26,80.68L46.26,80.68z M28.15,56.73l76.32-47.26L7.22,36.83L28.15,56.73 L28.15,56.73z M114.43,9.03L31.79,60.19l38.67,36.78L114.43,9.03L114.43,9.03z"/></g></svg>
                    </span></button>
                </div>
            </div>

            <!-- Powered by footer -->
            <div style="
                padding: ${themeConfig.spacing.small} ${themeConfig.spacing.large};
                text-align: center;
                font-size: 11px;
                color: ${themeConfig.colors.textLight};
                background: ${themeConfig.colors.messageBackground};
                border-top: 1px solid ${themeConfig.colors.border};
            ">
                Powered by Bookzo.io
            </div>
        </div>
    `;

  // Add CSS for typing animation
  const style = document.createElement("style");
  style.textContent = `
    @keyframes typing-bounce {
      0%, 80%, 100% {
        transform: scale(0);
      }
      40% {
        transform: scale(1);
      }
    }
  `;
  document.head.appendChild(style);

  document.body.appendChild(chatHead);
  document.body.appendChild(chatBox);

  const log = chatBox.querySelector("#chat-log");
  const input = chatBox.querySelector("#chat-input");
  const sendBtn = chatBox.querySelector("#send-btn");
  const chatWidget = chatBox.querySelector("#chat-widget");
  const chatHeadElement = chatHead.querySelector("#chat-head");
  const closeBtn = chatBox.querySelector("#close-chat");
  const typingIndicator = chatBox.querySelector("#typing-indicator");

  let isChatOpen = false;
  let hasInitialized = false;

  function openChat() {
    isChatOpen = true;
    chatHeadElement.style.display = "none";
    chatWidget.style.display = "flex";
    
    // Trigger animation
    setTimeout(() => {
      chatWidget.style.opacity = "1";
      chatWidget.style.transform = "scale(1) translateY(0)";
    }, 10);

    // Initialize welcome message only once
    if (!hasInitialized) {
      hasInitialized = true;
      setTimeout(() => {
        appendMessage(
          "AI Agent",
          `Hello, I'm the Bookzo.io Support Assistant. I'm a bot, and I can help you find information.`
        );
      }, 500);
    }
  }

  function closeChat() {
    isChatOpen = false;
    chatWidget.style.opacity = "0";
    chatWidget.style.transform = "scale(0.8) translateY(20px)";
    
    setTimeout(() => {
      chatWidget.style.display = "none";
      chatHeadElement.style.display = "flex";
    }, 300);
  }

  function showTypingIndicator() {
    typingIndicator.style.display = "block";
    log.scrollTop = log.scrollHeight;
  }

  function hideTypingIndicator() {
    typingIndicator.style.display = "none";
  }

  // Event listeners
  chatHeadElement.addEventListener("click", openChat);
  closeBtn.addEventListener("click", closeChat);

  function appendMessage(sender, text) {
    const msg = document.createElement("div");
    const isUser = sender === "You";

    msg.style.marginBottom = themeConfig.spacing.medium;
    msg.style.display = "flex";
    msg.style.flexDirection = "column";
    msg.style.alignItems = isUser ? "flex-end" : "flex-start";

    const messageContent = document.createElement("div");
    messageContent.style.padding = `${themeConfig.spacing.medium} ${themeConfig.spacing.large}`;
    messageContent.style.borderRadius = themeConfig.borderRadius.large;
    messageContent.style.maxWidth = "85%";
    messageContent.style.wordBreak = "break-word";
    messageContent.style.whiteSpace = "pre-wrap";
    messageContent.style.fontSize = "14px";
    messageContent.style.lineHeight = "1.4";
    messageContent.style.boxShadow = themeConfig.shadows.message;

    if (isUser) {
      messageContent.style.backgroundColor =
        themeConfig.colors.userMessageBackground;
      messageContent.style.color = themeConfig.colors.white;
      messageContent.style.borderBottomRightRadius = "6px";
    } else {
      messageContent.style.backgroundColor =
        themeConfig.colors.messageBackground;
      messageContent.style.color = themeConfig.colors.text;
      messageContent.style.borderBottomLeftRadius = "6px";
    }

    // Add timestamp
    const timestamp = document.createElement("div");
    timestamp.style.fontSize = "11px";
    timestamp.style.color = themeConfig.colors.textLight;
    timestamp.style.marginTop = "4px";
    timestamp.style.textAlign = isUser ? "right" : "left";

    const now = new Date();
    const timeString = now.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });
    timestamp.textContent = timeString;

    messageContent.innerHTML = text;
    msg.appendChild(messageContent);
    msg.appendChild(timestamp);
    log.appendChild(msg);
    log.scrollTop = log.scrollHeight;
  }

  // Auto-resize textarea
  input.addEventListener("input", function () {
    this.style.height = "auto";
    const newHeight = Math.min(
      this.scrollHeight,
      parseInt(themeConfig.input.maxHeight)
    );
    this.style.height = newHeight + "px";
  });

  sendBtn.addEventListener("click", sendMessage);
  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  function sendMessage() {
    const message = input.value.trim();
    if (!message) return;

    appendMessage("You", message);
    input.value = "";
    input.style.height = "auto";

    // Show typing indicator
    showTypingIndicator();

    const conversationId = localStorage.getItem("conversation_id") || null;

    fetch(`${endpoint}/${companySlug}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        message: message,
        conversation_id: conversationId,
      }),
    })
      .then(res => res.json())
      .then(data => {
        // Hide typing indicator
        hideTypingIndicator();

        if (data.conversation_id) {
          localStorage.setItem("conversation_id", data.conversation_id);
        }

        if (data && data.reply) {
          appendMessage("AI Agent", data.reply);
        } else {
          appendMessage("AI Agent", "<em>No response</em>");
        }
      })
      .catch(() => {
        // Hide typing indicator
        hideTypingIndicator();
        appendMessage("AI Agent", "<em>Error contacting server</em>");
      });
  }
})();