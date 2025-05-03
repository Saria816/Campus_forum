<?php
// 启动输出缓冲
ob_start();

// 包含头部
require_once 'includes/header.php';

// 检查是否提供了帖子ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = 'Invalid post ID.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

$postId = (int)$_GET['id'];

// 获取帖子详情
$stmt = $pdo->prepare("
    SELECT p.*, 
        c.name as category_name,
        u.username, 
        u.avatar,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked
    FROM posts p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");

$stmt->execute([isLoggedIn() ? getCurrentUserId() : 0, $postId]);
$post = $stmt->fetch();

if (!$post) {
    $_SESSION['flash_message'] = 'Post not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

// 获取评论
$stmt = $pdo->prepare("
    SELECT c.*, 
        u.username, 
        u.avatar
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");

$stmt->execute([$postId]);
$comments = $stmt->fetchAll();

// 处理评论提交
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $content = trim($_POST['content'] ?? '');
    
    if (empty($content)) {
        $commentError = 'Comment cannot be empty.';
    } else {
        // 过滤敏感词
        $filteredContent = filterSensitiveWords($content);
        
        // 添加评论
        $stmt = $pdo->prepare("
            INSERT INTO comments (post_id, user_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $postId,
            getCurrentUserId(),
            $filteredContent
        ]);
        
        if ($result) {
            // 创建通知（如果不是自己的帖子）
            if ($post['user_id'] != getCurrentUserId()) {
                createNotification(
                    $post['user_id'],
                    'comment',
                    getCurrentUser()['username'] . ' commented on your post.',
                    $postId
                );
            }
            
            // 重定向以避免刷新时重复提交
            redirect('post.php?id=' . $postId);
        } else {
            $commentError = 'An error occurred while adding your comment.';
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- 帖子详情 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><?php echo h($post['title']); ?></h5>
                        <small>
                            <a href="forum.php?category=<?php echo $post['category_id']; ?>" class="text-white">
                                <?php echo h($post['category_name']); ?>
                            </a>
                        </small>
                    </div>
                    <?php if (isLoggedIn() && (getCurrentUserId() == $post['user_id'] || isAdmin())): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" id="postOptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="postOptionsDropdown">
                            <?php if (getCurrentUserId() == $post['user_id']): ?>
                            <li><a class="dropdown-item" href="edit_post.php?id=<?php echo $post['id']; ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                            <?php endif; ?>
                            <?php if (isAdmin()): ?>
                            <li><a class="dropdown-item text-danger" href="delete_post.php?id=<?php echo $post['id']; ?>" onclick="return confirm('Are you sure you want to delete this post?');"><i class="fas fa-trash me-2"></i>Delete</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <?php if ($post['is_anonymous']): ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user-secret"></i>
                            </div>
                            <?php else: ?>
                            <?php if (!empty($post['avatar'])): ?>
                            <img src="uploads/avatars/<?php echo $post['avatar']; ?>" class="rounded-circle" width="40" height="40" alt="Avatar">
                            <?php else: ?>
                            <?php echo getInitialsAvatar($post['username'], 40); ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ms-3">
                            <div class="fw-bold">
                                <?php if ($post['is_anonymous']): ?>
                                Anonymous
                                <?php if (!empty($post['anonymous_gender']) || !empty($post['anonymous_grade']) || !empty($post['anonymous_major'])): ?>
                                <span class="text-muted small">
                                    (
                                    <?php 
                                    $info = [];
                                    if (!empty($post['anonymous_gender'])) $info[] = h($post['anonymous_gender']);
                                    if (!empty($post['anonymous_grade'])) $info[] = h($post['anonymous_grade']);
                                    if (!empty($post['anonymous_major'])) $info[] = h($post['anonymous_major']);
                                    echo implode(', ', $info);
                                    ?>
                                    )
                                </span>
                                <?php endif; ?>
                                <?php else: ?>
                                <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none">
                                    <?php echo h($post['username']); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                Posted on <?php echo date('M d, Y \a\t h:i A', strtotime($post['created_at'])); ?>
                                <?php if ($post['updated_at'] != $post['created_at']): ?>
                                (Edited on <?php echo date('M d, Y \a\t h:i A', strtotime($post['updated_at'])); ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="post-content mb-3">
                        <?php echo nl2br(h($post['content'])); ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if (isLoggedIn()): ?>
                            <button class="btn btn-sm <?php echo $post['user_liked'] ? 'text-primary' : 'text-muted'; ?> like-button" data-post-id="<?php echo $post['id']; ?>">
                                <i class="fas fa-thumbs-up"></i> <span class="like-count"><?php echo $post['like_count']; ?></span>
                            </button>
                            <button class="btn btn-sm text-muted report-button" data-post-id="<?php echo $post['id']; ?>">
                                <i class="fas fa-flag"></i> Report
                            </button>
                            <?php else: ?>
                            <span class="text-muted">
                                <i class="fas fa-thumbs-up"></i> <?php echo $post['like_count']; ?> likes
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> comments
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 评论区 -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Comments (<?php echo count($comments); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($comments)): ?>
                    <p class="text-center text-muted my-4">No comments yet. Be the first to comment!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment mb-3 pb-3 border-bottom">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($comment['avatar'])): ?>
                                    <img src="uploads/avatars/<?php echo $comment['avatar']; ?>" class="rounded-circle" width="32" height="32" alt="Avatar">
                                    <?php else: ?>
                                    <?php echo getInitialsAvatar($comment['username'], 32); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-bold">
                                            <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="text-decoration-none">
                                                <?php echo h($comment['username']); ?>
                                            </a>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('M d, Y \a\t h:i A', strtotime($comment['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="mt-1">
                                        <?php echo nl2br(h($comment['content'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
                    <div class="mt-4">
                        <h6>Add a Comment</h6>
                        <?php if (!empty($commentError)): ?>
                        <div class="alert alert-danger">
                            <?php echo $commentError; ?>
                        </div>
                        <?php endif; ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <textarea class="form-control" name="content" rows="3" placeholder="Write your comment here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Comment</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mt-4">
                        <a href="login.php">Log in</a> to leave a comment.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 举报模态框 -->
<?php if (isLoggedIn()): ?>
<div class="modal fade" id="report-modal" tabindex="-1" aria-labelledby="report-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="report-modal-label">Report Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="report_post.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="post_id" id="report-post-id">
                    <div class="mb-3">
                        <label for="report-reason" class="form-label">Reason for reporting</label>
                        <select class="form-select" id="report-reason" name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="spam">Spam</option>
                            <option value="harassment">Harassment or bullying</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="misinformation">Misinformation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="report-details" class="form-label">Additional details (optional)</label>
                        <textarea class="form-control" id="report-details" name="details" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// 包含底部
require_once 'includes/footer.php';

// 结束输出缓冲并发送
ob_end_flush();
?> 