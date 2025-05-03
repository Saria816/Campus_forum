// 页面加载完成后执行
$(document).ready(function() {
    // 初始化工具提示
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // 初始化弹出框
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // 实时聊天功能
    if ($('#chat-container').length) {
        // 滚动到聊天底部
        scrollToBottom();
        
        // 发送消息
        $('#chat-form').on('submit', function(e) {
            e.preventDefault();
            
            var message = $('#message').val().trim();
            var receiverId = $('#receiver_id').val();
            
            if (message !== '') {
                // 发送消息到服务器
                $.ajax({
                    url: 'send_message.php',
                    type: 'POST',
                    data: {
                        receiver_id: receiverId,
                        message: message
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // 清空输入框
                            $('#message').val('');
                            
                            // 添加消息到聊天窗口
                            addMessage(response.message, true);
                            
                            // 滚动到底部
                            scrollToBottom();
                        } else {
                            alert('Failed to send message: ' + response.error);
                        }
                    },
                    error: function() {
                        alert('An error occurred while sending the message.');
                    }
                });
            }
        });
        
        // 定期获取新消息
        setInterval(fetchNewMessages, 3000);
    }
    
    // AI聊天功能
    if ($('#ai-chat-container').length) {
        // 滚动到聊天底部
        scrollToBottom();
        
        // 发送消息到AI
        $('#ai-chat-form').on('submit', function(e) {
            e.preventDefault();
            
            var message = $('#ai-message').val().trim();
            
            if (message !== '') {
                // 添加用户消息到聊天窗口
                addAIMessage(message, true);
                
                // 清空输入框
                $('#ai-message').val('');
                
                // 显示AI正在输入
                $('#ai-typing').show();
                
                // 滚动到底部
                scrollToBottom();
                
                // 发送消息到服务器
                $.ajax({
                    url: 'ai_chat_send.php',
                    type: 'POST',
                    data: {
                        message: message
                    },
                    dataType: 'json',
                    success: function(response) {
                        // 隐藏AI正在输入
                        $('#ai-typing').hide();
                        
                        if (response.success) {
                            // 添加AI回复到聊天窗口
                            addAIMessage(response.message, false);
                            
                            // 滚动到底部
                            scrollToBottom();
                        } else {
                            alert('Failed to get AI response: ' + response.error);
                        }
                    },
                    error: function() {
                        // 隐藏AI正在输入
                        $('#ai-typing').hide();
                        alert('An error occurred while communicating with AI.');
                    }
                });
            }
        });
    }
    
    // 帖子点赞功能
    $('.like-button').on('click', function() {
        var postId = $(this).data('post-id');
        var button = $(this);
        
        $.ajax({
            url: 'like_post.php',
            type: 'POST',
            data: {
                post_id: postId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 更新点赞数量
                    button.find('.like-count').text(response.likes);
                    
                    // 切换点赞状态
                    if (response.liked) {
                        button.addClass('text-primary');
                    } else {
                        button.removeClass('text-primary');
                    }
                }
            }
        });
    });
    
    // 帖子举报功能
    $('.report-button').on('click', function() {
        var postId = $(this).data('post-id');
        $('#report-post-id').val(postId);
        $('#report-modal').modal('show');
    });
    
    // 匿名发帖选项
    $('#anonymous-checkbox').on('change', function() {
        if ($(this).is(':checked')) {
            $('#anonymous-options').slideDown();
        } else {
            $('#anonymous-options').slideUp();
        }
    });
    
    // 通知标记为已读
    $('.mark-read-button').on('click', function() {
        var notificationId = $(this).data('notification-id');
        var notificationElement = $(this).closest('.notification');
        
        $.ajax({
            url: 'mark_notification_read.php',
            type: 'POST',
            data: {
                notification_id: notificationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    notificationElement.removeClass('notification-unread');
                }
            }
        });
    });
});

// 滚动到聊天底部
function scrollToBottom() {
    var chatContainer = document.getElementById('chat-container');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    var aiChatContainer = document.getElementById('ai-chat-container');
    if (aiChatContainer) {
        aiChatContainer.scrollTop = aiChatContainer.scrollHeight;
    }
}

// 添加消息到聊天窗口
function addMessage(message, isSent) {
    var messageClass = isSent ? 'chat-message-sent' : 'chat-message-received';
    var html = '<div class="chat-message ' + messageClass + '" data-message-id="' + message.id + '">' +
               '<div class="chat-message-content">' + message.content + '</div>' +
               '<div class="chat-message-time small text-muted">' + message.time + '</div>' +
               '</div>';
    
    $('#chat-container').append(html);
}

// 添加消息到AI聊天窗口
function addAIMessage(content, isUser) {
    var time = new Date().toLocaleTimeString();
    var messageClass = isUser ? 'chat-message-sent' : 'chat-message-received ai-message';
    var sender = isUser ? 'You' : 'AI Assistant';
    
    var html = '<div class="chat-message ' + messageClass + '">' +
               '<div class="fw-bold">' + sender + '</div>' +
               '<div class="chat-message-content">' + content + '</div>' +
               '<div class="chat-message-time small text-muted">' + time + '</div>' +
               '</div>';
    
    $('#ai-chat-container').append(html);
}

// 获取新消息
function fetchNewMessages() {
    var receiverId = $('#receiver_id').val();
    var lastMessageId = $('.chat-message').last().data('message-id') || 0;
    
    $.ajax({
        url: 'get_new_messages.php',
        type: 'GET',
        data: {
            receiver_id: receiverId,
            last_message_id: lastMessageId
        },
        dataType: 'json',
        success: function(response) {
            if (response.messages && response.messages.length > 0) {
                // 添加新消息到聊天窗口
                response.messages.forEach(function(message) {
                    addMessage(message, message.is_sent);
                });
                
                // 滚动到底部
                scrollToBottom();
            }
        }
    });
} 