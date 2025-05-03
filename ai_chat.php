<?php
// 包含头部
require_once 'includes/header.php';
require_once 'includes/ai_chat.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to use AI chat.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 获取当前用户ID
$currentUserId = getCurrentUserId();

// DeepSeek API Key - In production, store this securely in environment variables
$apiKey = 'sk-9759ef1d61564923862cce2cbf64c84d'; // Replace with your actual API key

// 获取与AI助手的聊天记录
$stmt = $pdo->prepare("
    SELECT u_ai.id as ai_id
    FROM users u_ai
    WHERE u_ai.username = 'AI Assistant'
    LIMIT 1
");
$stmt->execute();
$aiUser = $stmt->fetch();

$messages = [];
$aiUserId = null;

if ($aiUser) {
    $aiUserId = $aiUser['ai_id'];
    
    // 获取聊天记录
    $stmt = $pdo->prepare("
        SELECT m.*, 
            CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sent
        FROM messages m
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([
        $currentUserId, 
        $currentUserId, 
        $aiUserId, 
        $aiUserId, 
        $currentUserId
    ]);
    
    $messages = $stmt->fetchAll();
    
    // 标记消息为已读
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    
    $stmt->execute([$aiUserId, $currentUserId]);
} else {
    // Create AI Assistant user if it doesn't exist
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, created_at, updated_at) 
        VALUES ('AI Assistant', 'ai@example.com', ?, 'admin', NOW(), NOW())
    ");
    $stmt->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $aiUserId = $pdo->lastInsertId();
}

// Handle form submission for new message
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = trim($_POST['message']);
    
    if (!empty($userMessage)) {
        // Get response from DeepSeek API
        $result = getDeepSeekResponse($userMessage, $apiKey, $messages);
        
        if ($result['success']) {
            // Save both messages to database
            saveAiChatMessage($currentUserId, $userMessage, $result['response']);
            
            // Redirect to refresh the chat
            redirect('ai_chat.php');
        } else {
            $error = 'Error: ' . $result['error'];
        }
    } else {
        $error = 'Message cannot be empty.';
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-robot me-2"></i> AI Assistant Chat
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="chat-container" class="chat-container p-3" style="height: 500px; overflow-y: auto;">
                        <?php if (empty($messages)): ?>
                        <div class="text-center my-5">
                            <p>Welcome to AI Assistant! Ask me anything, and I'll do my best to help you.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                            <div class="chat-message <?php echo $message['is_sent'] ? 'chat-message-sent' : 'chat-message-received'; ?>" data-message-id="<?php echo $message['id']; ?>">
                                <div class="chat-message-content"><?php echo isset($message['content']) ? h($message['content']) : ''; ?></div>
                                <div class="chat-message-time small text-muted"><?php echo date('h:i A', strtotime($message['created_at'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger m-3">
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-3 border-top">
                        <form id="chat-form" method="post" action="">
                            <div class="input-group">
                                <input type="text" class="form-control" name="message" id="message" placeholder="Type your message..." required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">About AI Assistant</h5>
                </div>
                <div class="card-body">
                    <p>Our AI Assistant is powered by DeepSeek, a powerful language model that can help you with:</p>
                    <ul>
                        <li>Answering questions about academic topics</li>
                        <li>Providing explanations on complex subjects</li>
                        <li>Helping with research and writing</li>
                        <li>Offering suggestions and advice</li>
                        <li>And much more!</li>
                    </ul>
                    <p class="text-muted small">Note: The AI Assistant is designed to be helpful, but it may not always provide perfect answers. Always verify important information from reliable sources.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-container {
    display: flex;
    flex-direction: column;
}

.chat-message {
    max-width: 70%;
    margin-bottom: 15px;
    padding: 10px 15px;
    border-radius: 15px;
    position: relative;
}

.chat-message-sent {
    align-self: flex-end;
    background-color: #007bff;
    color: white;
    border-bottom-right-radius: 5px;
}

.chat-message-received {
    align-self: flex-start;
    background-color: #f1f1f1;
    border-bottom-left-radius: 5px;
}

.chat-message-time {
    font-size: 0.7rem;
    margin-top: 5px;
    text-align: right;
}

.chat-message-sent .chat-message-time {
    color: rgba(255, 255, 255, 0.8);
}

/* Typing indicator */
.typing-indicator {
    padding: 10px 15px;
}

.typing-indicator .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #777;
    margin-right: 3px;
    animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator .dot:nth-child(1) {
    animation-delay: 0s;
}

.typing-indicator .dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator .dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
    }
    30% {
        transform: translateY(-5px);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to bottom of chat container
    const chatContainer = document.getElementById('chat-container');
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // Handle form submission
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message');
    
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Disable form while processing
        const submitButton = chatForm.querySelector('button[type="submit"]');
        messageInput.disabled = true;
        submitButton.disabled = true;
        
        // Add user message to chat
        addMessageToChat(message, true);
        
        // Clear input
        messageInput.value = '';
        
        // Add AI typing indicator
        const typingIndicator = addTypingIndicator();
        
        console.log('Sending message:', message);
        
        // First send the message via POST to save it in the database
        fetch('ai_chat_stream.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
        })
        .then(response => {
            console.log('POST response status:', response.status);
            
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            
            // Now create EventSource connection for streaming response
            console.log('Creating EventSource connection');
            
            // 清除之前可能存在的EventSource连接
            if (window.activeEventSource) {
                window.activeEventSource.close();
            }
            
            // 创建新的EventSource连接
            const eventSource = new EventSource('ai_chat_stream.php?message=' + encodeURIComponent(message));
            window.activeEventSource = eventSource;
            
            let aiMessage = '';
            let aiMessageElement = null;
            
            // 添加调试信息
            console.log('EventSource readyState:', eventSource.readyState);
            
            // Connection opened
            eventSource.onopen = function(e) {
                console.log('EventSource connection opened');
                // 移除打字指示器
                if (typingIndicator) {
                    typingIndicator.remove();
                }
                
                // 创建AI消息元素（如果尚未创建）
                if (!aiMessageElement) {
                    aiMessageElement = addMessageToChat('', false);
                    aiMessage = '';
                }
            };
            
            // Listen for start event
            eventSource.addEventListener('start', function(e) {
                console.log('Stream started:', e.data);
                try {
                    const data = JSON.parse(e.data);
                    console.log('Start data:', data);
                    
                    // 创建AI消息元素（如果尚未创建）
                    if (!aiMessageElement) {
                        aiMessageElement = addMessageToChat('', false);
                        aiMessage = '';
                    }
                } catch (error) {
                    console.error('Error parsing start data:', error);
                }
            });
            
            // Listen for message events
            eventSource.addEventListener('message', function(e) {
                console.log('Message received:', e.data);
                try {
                    const data = JSON.parse(e.data);
                    
                    if (!aiMessageElement) {
                        // Create new AI message element for the first chunk
                        aiMessageElement = addMessageToChat('', false);
                        aiMessage = '';
                    }
                    
                    // Append new content
                    if (data.content) {
                        aiMessage += data.content;
                        updateMessageContent(aiMessageElement, aiMessage);
                        
                        // Scroll to bottom
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }
                } catch (error) {
                    console.error('Error parsing message data:', error, e.data);
                }
            });
            
            // Listen for done event
            eventSource.addEventListener('done', function(e) {
                console.log('Stream done:', e.data);
                // Message is complete
                
                // Re-enable form
                messageInput.disabled = false;
                submitButton.disabled = false;
                messageInput.focus();
            });
            
            // Listen for close event
            eventSource.addEventListener('close', function(e) {
                console.log('Stream closed:', e.data);
                // Close the connection
                eventSource.close();
                window.activeEventSource = null;
                
                // Re-enable form
                messageInput.disabled = false;
                submitButton.disabled = false;
                messageInput.focus();
            });
            
            // Listen for error events
            eventSource.onerror = function(e) {
                console.error('SSE Error:', e);
                eventSource.close();
                window.activeEventSource = null;
                
                // Show error message if no AI message was created yet
                if (!aiMessageElement) {
                    addMessageToChat('Sorry, there was an error generating a response. Please try again.', false);
                }
                
                // Remove typing indicator if it exists
                if (typingIndicator) {
                    typingIndicator.remove();
                }
                
                // Re-enable form
                messageInput.disabled = false;
                submitButton.disabled = false;
                messageInput.focus();
            };
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            
            // Show error message
            addMessageToChat('Sorry, there was an error sending your message. Please try again.', false);
            
            // Remove typing indicator if it exists
            if (typingIndicator) {
                typingIndicator.remove();
            }
            
            // Re-enable form
            messageInput.disabled = false;
            submitButton.disabled = false;
            messageInput.focus();
        });
    });
    
    // Function to add a message to the chat
    function addMessageToChat(content, isUser) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${isUser ? 'chat-message-sent' : 'chat-message-received'}`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'chat-message-content';
        contentDiv.textContent = content;
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'chat-message-time small text-muted';
        timeDiv.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(timeDiv);
        
        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        return messageDiv;
    }
    
    // Function to update message content
    function updateMessageContent(messageElement, content) {
        const contentDiv = messageElement.querySelector('.chat-message-content');
        if (contentDiv) {
            contentDiv.textContent = content;
        }
        // 每次更新内容后滚动到底部
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    // Function to add typing indicator
    function addTypingIndicator() {
        const indicatorDiv = document.createElement('div');
        indicatorDiv.className = 'chat-message chat-message-received typing-indicator';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'chat-message-content';
        contentDiv.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
        
        indicatorDiv.appendChild(contentDiv);
        chatContainer.appendChild(indicatorDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        return indicatorDiv;
    }
    
    // 在页面加载时关闭任何可能存在的EventSource连接
    if (window.activeEventSource) {
        window.activeEventSource.close();
        window.activeEventSource = null;
    }
});
</script>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 