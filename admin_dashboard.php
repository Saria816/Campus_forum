<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access this page.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 获取统计数据
// 用户统计
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$totalAdmins = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1");
$totalBannedUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newUsersThisWeek = $stmt->fetchColumn();

// 内容统计
$stmt = $pdo->query("SELECT COUNT(*) FROM posts");
$totalPosts = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newPostsThisWeek = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM comments");
$totalComments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newCommentsThisWeek = $stmt->fetchColumn();

// 举报统计
$stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
$pendingReports = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reports");
$totalReports = $stmt->fetchColumn();

// 消息统计
$stmt = $pdo->query("SELECT COUNT(*) FROM staff_messages WHERE status = 'pending'");
$pendingStaffMessages = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM staff_messages");
$totalStaffMessages = $stmt->fetchColumn();

// 获取最近的活动
$stmt = $pdo->query("
    SELECT 'new_user' as type, u.id, u.username, u.created_at as date, NULL as content
    FROM users u
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 'new_post' as type, p.id, u.username, p.created_at as date, p.title as content
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 'new_report' as type, r.id, u.username, r.created_at as date, p.title as content
    FROM reports r
    JOIN users u ON r.user_id = u.id
    JOIN posts p ON r.post_id = p.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 'new_staff_message' as type, sm.id, u.username, sm.created_at as date, sm.subject as content
    FROM staff_messages sm
    JOIN users u ON sm.user_id = u.id
    WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    ORDER BY date DESC
    LIMIT 10
");
$recentActivities = $stmt->fetchAll();

// 获取热门帖子
$stmt = $pdo->query("
    SELECT p.*, u.username, 
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY (comment_count + like_count) DESC
    LIMIT 5
");
$popularPosts = $stmt->fetchAll();

// 获取最活跃用户
$stmt = $pdo->query("
    SELECT u.id, u.username, u.created_at,
        (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) as post_count,
        (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) as comment_count
    FROM users u
    WHERE u.is_banned = 0
    ORDER BY (post_count + comment_count) DESC
    LIMIT 5
");
$activeUsers = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Administrator Dashboard</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $totalUsers; ?></h1>
                                <p class="lead">Total Users</p>
                                <div class="small text-muted">
                                    <?php echo $newUsersThisWeek; ?> new this week
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $totalPosts; ?></h1>
                                <p class="lead">Total Posts</p>
                                <div class="small text-muted">
                                    <?php echo $newPostsThisWeek; ?> new this week
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $pendingReports; ?></h1>
                                <p class="lead">Pending Reports</p>
                                <div class="small text-muted">
                                    <?php echo $totalReports; ?> total reports
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $pendingStaffMessages; ?></h1>
                                <p class="lead">Pending Messages</p>
                                <div class="small text-muted">
                                    <?php echo $totalStaffMessages; ?> total messages
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <a href="admin_users.php" class="btn btn-primary d-block">
                                            <i class="fas fa-users me-2"></i> Manage Users
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="admin_reports.php" class="btn btn-warning d-block">
                                            <i class="fas fa-flag me-2"></i> Handle Reports
                                            <?php if ($pendingReports > 0): ?>
                                            <span class="badge bg-danger ms-1"><?php echo $pendingReports; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="admin_staff_messages.php" class="btn btn-info d-block text-white">
                                            <i class="fas fa-envelope me-2"></i> Staff Messages
                                            <?php if ($pendingStaffMessages > 0): ?>
                                            <span class="badge bg-danger ms-1"><?php echo $pendingStaffMessages; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="admin_sensitive_words.php" class="btn btn-danger d-block">
                                            <i class="fas fa-filter me-2"></i> Sensitive Words
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivities)): ?>
                                <p class="text-muted">No recent activity.</p>
                                <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <?php if ($activity['type'] === 'new_user'): ?>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">New User Registration</h6>
                                            <small><?php echo timeAgo($activity['date']); ?></small>
                                        </div>
                                        <p class="mb-1">User <a href="profile.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['username']); ?></a> has registered.</p>
                                        
                                        <?php elseif ($activity['type'] === 'new_post'): ?>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">New Post Created</h6>
                                            <small><?php echo timeAgo($activity['date']); ?></small>
                                        </div>
                                        <p class="mb-1">User <a href="profile.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['username']); ?></a> created a new post: <a href="post.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['content']); ?></a></p>
                                        
                                        <?php elseif ($activity['type'] === 'new_report'): ?>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">New Report Submitted</h6>
                                            <small><?php echo timeAgo($activity['date']); ?></small>
                                        </div>
                                        <p class="mb-1">User <a href="profile.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['username']); ?></a> reported a post: <a href="post.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['content']); ?></a></p>
                                        
                                        <?php elseif ($activity['type'] === 'new_staff_message'): ?>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">New Staff Message</h6>
                                            <small><?php echo timeAgo($activity['date']); ?></small>
                                        </div>
                                        <p class="mb-1">User <a href="profile.php?id=<?php echo $activity['id']; ?>"><?php echo h($activity['username']); ?></a> sent a message: <?php echo h($activity['content']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Popular Posts</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($popularPosts)): ?>
                                <p class="text-muted">No posts found.</p>
                                <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($popularPosts as $post): ?>
                                    <a href="post.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo h($post['title']); ?></h6>
                                            <small><?php echo timeAgo($post['created_at']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo h(substr($post['content'], 0, 100)) . (strlen($post['content']) > 100 ? '...' : ''); ?></p>
                                        <small>By <?php echo h($post['username']); ?> • <?php echo $post['comment_count']; ?> comments • <?php echo $post['like_count']; ?> likes</small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Most Active Users</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($activeUsers)): ?>
                                <p class="text-muted">No active users found.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Registered</th>
                                                <th>Posts</th>
                                                <th>Comments</th>
                                                <th>Total Activity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeUsers as $user): ?>
                                            <tr>
                                                <td><a href="profile.php?id=<?php echo $user['id']; ?>"><?php echo h($user['username']); ?></a></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td><?php echo $user['post_count']; ?></td>
                                                <td><?php echo $user['comment_count']; ?></td>
                                                <td><?php echo $user['post_count'] + $user['comment_count']; ?></td>
                                                <td>
                                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * 计算时间差并返回友好的时间格式
 * 
 * @param string $datetime 日期时间字符串
 * @return string 友好的时间格式
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

// 包含底部
require_once 'includes/footer.php';
?> 