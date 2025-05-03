<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You must be logged in to check updates.']);
    exit;
}

// 获取当前用户ID
$userId = getCurrentUserId();

// 获取未读通知数量
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$notificationsCount = (int)$stmt->fetchColumn();

// 获取未读消息数量
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$messagesCount = (int)$stmt->fetchColumn();

// 返回响应
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notifications' => $notificationsCount,
    'messages' => $messagesCount
]);
exit; 