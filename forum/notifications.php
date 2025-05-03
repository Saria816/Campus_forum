<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to view notifications.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 获取当前用户ID
$userId = getCurrentUserId();

// 处理标记所有通知为已读
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $_SESSION['flash_message'] = 'All notifications marked as read.';
    $_SESSION['flash_type'] = 'success';
    redirect('notifications.php');
}

// 处理删除所有通知
if (isset($_GET['delete_all']) && $_GET['delete_all'] == 1) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $_SESSION['flash_message'] = 'All notifications deleted.';
    $_SESSION['flash_type'] = 'success';
    redirect('notifications.php');
}

// 处理单个通知标记为已读
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notificationId = (int)$_GET['mark_read'];
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    $_SESSION['flash_message'] = 'Notification marked as read.';
    $_SESSION['flash_type'] = 'success';
    redirect('notifications.php');
}

// 处理单个通知删除
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notificationId = (int)$_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    $_SESSION['flash_message'] = 'Notification deleted.';
    $_SESSION['flash_type'] = 'success';
    redirect('notifications.php');
}

// 获取通知过滤条件
$filter = $_GET['filter'] ?? 'all';

// 构建查询
$query = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

// 添加过滤条件
if ($filter === 'unread') {
    $query .= " AND is_read = 0";
}

// 添加排序
$query .= " ORDER BY created_at DESC";

// 执行查询
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// 获取未读通知数量
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Notifications</h5>
                <div>
                    <?php if (!empty($notifications)): ?>
                    <div class="btn-group">
                        <a href="notifications.php?mark_all_read=1" class="btn btn-sm btn-light" onclick="return confirm('Are you sure you want to mark all notifications as read?')">
                            <i class="fas fa-check-double me-1"></i> Mark All Read
                        </a>
                        <a href="notifications.php?delete_all=1" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete all notifications? This action cannot be undone.')">
                            <i class="fas fa-trash me-1"></i> Delete All
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="get" action="" class="d-flex">
                            <select name="filter" class="form-select me-2" onchange="this.form.submit()">
                                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All Notifications</option>
                                <option value="unread" <?php echo ($filter === 'unread') ? 'selected' : ''; ?>>Unread Notifications</option>
                            </select>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0 pt-2">
                            <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger"><?php echo $unreadCount; ?> unread</span>
                            <?php endif; ?>
                            Total: <?php echo count($notifications); ?> notifications
                        </p>
                    </div>
                </div>
                
                <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You have no notifications.
                </div>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item list-group-item-action <?php echo ($notification['is_read'] == 0) ? 'list-group-item-light' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1">
                                <?php if ($notification['is_read'] == 0): ?>
                                <span class="badge bg-primary me-2">New</span>
                                <?php endif; ?>
                                
                                <?php
                                // 根据通知类型显示不同的图标和标题
                                switch ($notification['type']) {
                                    case 'post_like':
                                        echo '<i class="fas fa-heart text-danger me-2"></i> Post Like';
                                        break;
                                    case 'post_comment':
                                        echo '<i class="fas fa-comment text-primary me-2"></i> New Comment';
                                        break;
                                    case 'comment_reply':
                                        echo '<i class="fas fa-reply text-info me-2"></i> Comment Reply';
                                        break;
                                    case 'new_message':
                                        echo '<i class="fas fa-envelope text-success me-2"></i> New Message';
                                        break;
                                    case 'post_deleted':
                                        echo '<i class="fas fa-trash text-danger me-2"></i> Post Deleted';
                                        break;
                                    case 'user_banned':
                                        echo '<i class="fas fa-ban text-danger me-2"></i> Account Banned';
                                        break;
                                    case 'user_unbanned':
                                        echo '<i class="fas fa-unlock text-success me-2"></i> Account Unbanned';
                                        break;
                                    case 'role_changed':
                                        echo '<i class="fas fa-user-shield text-warning me-2"></i> Role Changed';
                                        break;
                                    case 'report_resolved':
                                        echo '<i class="fas fa-flag text-success me-2"></i> Report Resolved';
                                        break;
                                    case 'staff_response':
                                        echo '<i class="fas fa-envelope-open-text text-info me-2"></i> Staff Response';
                                        break;
                                    default:
                                        echo '<i class="fas fa-bell text-secondary me-2"></i> Notification';
                                }
                                ?>
                            </h6>
                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo h($notification['content']); ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <?php if ($notification['related_id']): ?>
                                <?php
                                // 根据通知类型生成不同的链接
                                $link = '#';
                                $linkText = 'View';
                                
                                switch ($notification['type']) {
                                    case 'post_like':
                                    case 'post_comment':
                                    case 'post_deleted':
                                        $link = 'post.php?id=' . $notification['related_id'];
                                        $linkText = 'View Post';
                                        break;
                                    case 'comment_reply':
                                        $link = 'post.php?id=' . $notification['related_id'] . '#comment-' . $notification['related_id'];
                                        $linkText = 'View Comment';
                                        break;
                                    case 'new_message':
                                        $link = 'chat.php?user=' . $notification['related_id'];
                                        $linkText = 'View Message';
                                        break;
                                    case 'report_resolved':
                                        $link = 'post.php?id=' . $notification['related_id'];
                                        $linkText = 'View Report';
                                        break;
                                    case 'staff_response':
                                        $link = 'staff_message.php';
                                        $linkText = 'View Response';
                                        break;
                                }
                                ?>
                                <a href="<?php echo $link; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i> <?php echo $linkText; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($notification['is_read'] == 0): ?>
                                <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                    <i class="fas fa-check me-1"></i> Mark as Read
                                </a>
                                <?php endif; ?>
                                <a href="notifications.php?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?')">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 