<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录且是管理员
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to delete posts.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

// 检查是否提供了帖子ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = 'Invalid post ID.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

$postId = (int)$_GET['id'];

// 检查帖子是否存在
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if ($stmt->rowCount() === 0) {
    $_SESSION['flash_message'] = 'Post not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

// 开始事务
$pdo->beginTransaction();

try {
    // 删除相关的点赞
    $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    
    // 删除相关的评论
    $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
    $stmt->execute([$postId]);
    
    // 删除相关的举报
    $stmt = $pdo->prepare("DELETE FROM reports WHERE post_id = ?");
    $stmt->execute([$postId]);
    
    // 删除相关的通知
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE reference_id = ? AND (type = 'post' OR type = 'comment' OR type = 'like')");
    $stmt->execute([$postId]);
    
    // 删除帖子
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    
    // 提交事务
    $pdo->commit();
    
    $_SESSION['flash_message'] = 'Post has been deleted successfully.';
    $_SESSION['flash_type'] = 'success';
} catch (Exception $e) {
    // 回滚事务
    $pdo->rollBack();
    
    $_SESSION['flash_message'] = 'An error occurred while deleting the post.';
    $_SESSION['flash_type'] = 'danger';
}

redirect('forum.php'); 