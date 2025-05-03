<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You must be logged in to send messages.']);
    exit;
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// 获取接收者ID和消息内容
$receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$content = trim($_POST['message'] ?? '');

// 验证输入
if ($receiverId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid receiver.']);
    exit;
}

if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

// 检查接收者是否存在
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$receiverId]);
if ($stmt->rowCount() === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Receiver not found.']);
    exit;
}

// Filter sensitive words
$filteredContent = filterSensitiveWords($content);

// Send message
$stmt = $pdo->prepare("
    INSERT INTO messages (sender_id, receiver_id, content, is_read, created_at)
    VALUES (?, ?, ?, 0, NOW())
");

$result = $stmt->execute([getCurrentUserId(), $receiverId, $filteredContent]);

if ($result) {
    $messageId = $pdo->lastInsertId();
    
    // Message acquisition time
    $stmt = $pdo->prepare("SELECT created_at FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $createdAt = $stmt->fetchColumn();
    
    // Create Notification
    createNotification(
        $receiverId,
        'new_message',
        getCurrentUser()['username'] . ' sent you a message.',
        $messageId
    );
    
    // 返回成功响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'content' => $filteredContent,
            'time' => date('h:i A', strtotime($createdAt)),
            'is_sent' => true
        ]
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
}
exit; 