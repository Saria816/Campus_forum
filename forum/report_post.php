<?php
// 包含函数文件
require_once 'includes/functions.php';
require_once 'includes/db.php';

// 检查用户是否登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to report a post.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = 'Invalid request method.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 获取并验证输入
$postId = (int)($_POST['post_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

// 验证输入
$errors = [];

if ($postId <= 0) {
    $errors[] = 'Invalid post ID.';
}

if (empty($reason)) {
    $errors[] = 'Please provide a reason for your report.';
} elseif (strlen($reason) > 500) {
    $errors[] = 'Reason is too long. Maximum 500 characters allowed.';
}

// 如果有错误，返回到帖子页面
if (!empty($errors)) {
    $_SESSION['flash_message'] = implode(' ', $errors);
    $_SESSION['flash_type'] = 'danger';
    redirect('post.php?id=' . $postId);
}

// 检查帖子是否存在
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    $_SESSION['flash_message'] = 'Post not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 检查用户是否已经举报过这个帖子
$userId = getCurrentUserId();
$stmt = $pdo->prepare("SELECT id FROM reports WHERE post_id = ? AND user_id = ? AND status = 'pending'");
$stmt->execute([$postId, $userId]);
if ($stmt->fetch()) {
    $_SESSION['flash_message'] = 'You have already reported this post. Please wait for a moderator to review it.';
    $_SESSION['flash_type'] = 'warning';
    redirect('post.php?id=' . $postId);
}

// 过滤敏感词
$reason = filterSensitiveWords($reason);

// 保存举报
try {
    $stmt = $pdo->prepare("
        INSERT INTO reports (post_id, user_id, reason, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$postId, $userId, $reason]);
    
    // 通知管理员
    $adminIds = getAdminIds();
    foreach ($adminIds as $adminId) {
        createNotification(
            $adminId,
            'new_report',
            'A new post has been reported. Please review it.',
            $postId
        );
    }
    
    $_SESSION['flash_message'] = 'Thank you for your report. A moderator will review it shortly.';
    $_SESSION['flash_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'An error occurred while submitting your report. Please try again later.';
    $_SESSION['flash_type'] = 'danger';
}

// 重定向回帖子页面
redirect('post.php?id=' . $postId);

/**
 * 获取所有管理员ID
 * 
 * @return array 管理员ID数组
 */
function getAdminIds() {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?> 