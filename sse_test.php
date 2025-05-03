<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSE Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        #messages {
            border: 1px solid #ccc;
            padding: 10px;
            height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }
        .message {
            margin-bottom: 5px;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .user {
            background-color: #e3f2fd;
        }
        .ai {
            background-color: #f1f8e9;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
        }
        .info {
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>SSE Test Page</h1>
    <div id="messages"></div>
    <form id="message-form">
        <div style="display: flex; margin-bottom: 10px;">
            <input type="text" id="message-input" style="flex: 1; padding: 8px;" placeholder="Type your message...">
            <button type="submit" style="margin-left: 10px; padding: 8px 15px;">Send</button>
        </div>
    </form>
    <div>
        <h3>Debug Info:</h3>
        <pre id="debug"></pre>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const debugContainer = document.getElementById('debug');
            
            // Add a message to the container
            function addMessage(text, type) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${type}`;
                messageDiv.textContent = text;
                messagesContainer.appendChild(messageDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Add debug info
            function addDebug(text) {
                debugContainer.textContent += text + '\n';
            }
            
            // Handle form submission
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                // Add user message
                addMessage('User: ' + message, 'user');
                
                // Clear input
                messageInput.value = '';
                
                // Add info message
                addMessage('Connecting to server...', 'info');
                
                // Create URL with message parameter
                const url = 'ai_chat_stream.php?message=' + encodeURIComponent(message);
                addDebug('Connecting to: ' + url);
                
                // Create EventSource
                const eventSource = new EventSource(url);
                
                // Handle connection open
                eventSource.onopen = function(e) {
                    addDebug('Connection opened');
                    addMessage('Connection established', 'info');
                };
                
                // Handle messages
                eventSource.addEventListener('message', function(e) {
                    addDebug('Received message event: ' + e.data);
                    try {
                        const data = JSON.parse(e.data);
                        if (data.content) {
                            addMessage('AI: ' + data.content, 'ai');
                        }
                    } catch (error) {
                        addDebug('Error parsing message: ' + error);
                        addMessage('Error parsing message: ' + e.data, 'error');
                    }
                });
                
                // Handle start event
                eventSource.addEventListener('start', function(e) {
                    addDebug('Received start event: ' + e.data);
                    addMessage('AI is typing...', 'info');
                });
                
                // Handle done event
                eventSource.addEventListener('done', function(e) {
                    addDebug('Received done event: ' + e.data);
                    addMessage('AI response complete', 'info');
                });
                
                // Handle close event
                eventSource.addEventListener('close', function(e) {
                    addDebug('Received close event: ' + e.data);
                    eventSource.close();
                    addMessage('Connection closed', 'info');
                });
                
                // Handle error event
                eventSource.addEventListener('error', function(e) {
                    addDebug('Received error event: ' + JSON.stringify(e));
                    eventSource.close();
                    addMessage('Error: Connection failed or was closed unexpectedly', 'error');
                });
                
                // Handle custom error event from server
                eventSource.addEventListener('error', function(e) {
                    if (e.data) {
                        addDebug('Received custom error: ' + e.data);
                        try {
                            const data = JSON.parse(e.data);
                            if (data.error) {
                                addMessage('Server Error: ' + data.error, 'error');
                            }
                        } catch (error) {
                            // Ignore parsing errors
                        }
                    }
                });
            });
        });
    </script>
</body>
</html> 