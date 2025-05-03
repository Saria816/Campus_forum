<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You must be logged in to get messages.']);
    exit;
}

// 检查是否是GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// 获取接收者ID和最后消息ID
$receiverId = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

// 验证输入
if ($receiverId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid receiver.']);
    exit;
}

// Get new message
$stmt = $pdo->prepare("
    SELECT m.*, 
        CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sent
    FROM messages m
    WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        AND m.id > ?
    ORDER BY m.created_at ASC
");

$stmt->execute([
    getCurrentUserId(),
    getCurrentUserId(),
    $receiverId,
    $receiverId,
    getCurrentUserId(),
    $lastMessageId
]);

$messages = $stmt->fetchAll();

// 标记消息为已读
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
");

$stmt->execute([$receiverId, getCurrentUserId()]);

// 格式化消息
$formattedMessages = [];
foreach ($messages as $message) {
    $formattedMessages[] = [
        'id' => $message['id'],
        'content' => $message['content'],
        'time' => date('h:i A', strtotime($message['created_at'])),
        'is_sent' => (bool)$message['is_sent']
    ];
}

// 返回响应
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $formattedMessages
]);
exit; 