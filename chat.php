<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to use chat.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 获取当前用户ID
$currentUserId = getCurrentUserId();

// 获取聊天对象ID（如果有）
$receiverId = isset($_GET['user']) ? (int)$_GET['user'] : null;

// 获取用户的最近聊天列表
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END as user_id,
        u.username,
        u.avatar,
        (
            SELECT content 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = user_id) OR (sender_id = user_id AND receiver_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = user_id) OR (sender_id = user_id AND receiver_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE sender_id = user_id AND receiver_id = ? AND is_read = 0
        ) as unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY last_message_time DESC
");

$stmt->execute([
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId, 
    $currentUserId
]);

$chatUsers = $stmt->fetchAll();

// 如果有聊天对象，获取聊天记录
$messages = [];
$chatUser = null;

if ($receiverId) {
    // 获取聊天对象信息
    $stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$receiverId]);
    $chatUser = $stmt->fetch();
    
    if ($chatUser) {
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
            $receiverId, 
            $receiverId, 
            $currentUserId
        ]);
        
        $messages = $stmt->fetchAll();
        
        // 标记消息为已读
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        
        $stmt->execute([$receiverId, $currentUserId]);
    }
}

// 获取所有用户（用于新建聊天）
$stmt = $pdo->prepare("
    SELECT id, username, avatar 
    FROM users 
    WHERE id != ? AND id NOT IN (
        SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
    )
    ORDER BY username
");

$stmt->execute([$currentUserId, $currentUserId, $currentUserId, $currentUserId]);
$otherUsers = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Conversations</h5>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($chatUsers)): ?>
                <div class="p-4 text-center">
                    <p>No conversations yet. Start a new chat!</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($chatUsers as $user): ?>
                    <a href="chat.php?user=<?php echo $user['user_id']; ?>" class="list-group-item list-group-item-action <?php echo ($receiverId == $user['user_id']) ? 'active' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo h($user['username']); ?></h6>
                            <small><?php echo date('M d', strtotime($user['last_message_time'])); ?></small>
                        </div>
                        <p class="mb-1 text-truncate"><?php echo h($user['last_message']); ?></p>
                        <?php if ($user['unread_count'] > 0): ?>
                        <span class="badge bg-primary rounded-pill"><?php echo $user['unread_count']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <?php if ($chatUser): ?>
                    Chat with <?php echo h($chatUser['username']); ?>
                    <?php else: ?>
                    Select a conversation or start a new one
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if ($chatUser): ?>
                <div id="chat-container" class="chat-container p-3">
                    <?php if (empty($messages)): ?>
                    <div class="text-center my-5">
                        <p>No messages yet. Start the conversation!</p>
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
                
                <div class="p-3 border-top">
                    <form id="chat-form">
                        <input type="hidden" id="receiver_id" value="<?php echo $receiverId; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control chat-input" id="message" placeholder="Type your message..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="text-center my-5 p-4">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <p>Select a conversation from the list or start a new chat.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newChatModalLabel">Start New Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($otherUsers)): ?>
                <p class="text-center">No more users available to chat with.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($otherUsers as $user): ?>
                    <a href="chat.php?user=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                        <?php echo h($user['username']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 