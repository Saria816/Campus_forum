<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You must be logged in to like posts.']);
    exit;
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// 获取帖子ID
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

// 验证输入
if ($postId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid post ID.']);
    exit;
}

// 检查帖子是否存在
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if ($stmt->rowCount() === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Post not found.']);
    exit;
}

// 获取当前用户ID
$userId = getCurrentUserId();

// Check whether the user has given a like
$stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
$stmt->execute([$postId, $userId]);
$liked = $stmt->rowCount() > 0;

// If you have already liked, cancel the like. Otherwise, add a like
if ($liked) {
    $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
    $result = $stmt->execute([$postId, $userId]);
    $liked = false;
} else {
    $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
    $result = $stmt->execute([$postId, $userId]);
    $liked = true;
    
    // If it's not your own post, create a notification
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $postUserId = $stmt->fetchColumn();
    
    if ($postUserId != $userId) {
        createNotification(
            $postUserId,
            'post_like',
            getCurrentUser()['username'] . ' liked your post.',
            $postId
        );
    }
}

// 获取帖子的点赞数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
$stmt->execute([$postId]);
$likeCount = $stmt->fetchColumn();

// 返回响应
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'liked' => $liked,
    'likes' => $likeCount
]);
exit; 