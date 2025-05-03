<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access this page.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 处理举报处理
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    // 验证输入
    if ($reportId <= 0) {
        $error = 'Invalid report ID.';
    } elseif (!in_array($action, ['ignore', 'delete', 'ban'])) {
        $error = 'Invalid action.';
    } else {
        // 获取举报信息
        $stmt = $pdo->prepare("
            SELECT r.*, p.user_id as post_user_id, p.content as post_content
            FROM reports r
            JOIN posts p ON r.post_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        
        if (!$report) {
            $error = 'Report not found.';
        } else {
            // 开始事务
            $pdo->beginTransaction();
            
            try {
                // 更新举报状态
                $stmt = $pdo->prepare("
                    UPDATE reports 
                    SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), resolution = ?
                    WHERE id = ?
                ");
                $stmt->execute([getCurrentUserId(), $action, $reportId]);
                
                // 如果是删除帖子
                if ($action === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                    $stmt->execute([$report['post_id']]);
                    
                    // 通知帖子作者
                    createNotification(
                        $report['post_user_id'],
                        'post_deleted',
                        'Your post has been deleted by an administrator due to a violation of our community guidelines.' . ($reason ? ' Reason: ' . $reason : ''),
                        null
                    );
                }
                
                // 如果是封禁用户
                if ($action === 'ban') {
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, banned_reason = ? WHERE id = ?");
                    $stmt->execute([$reason, $report['post_user_id']]);
                    
                    // 通知被封禁用户
                    createNotification(
                        $report['post_user_id'],
                        'user_banned',
                        'Your account has been banned due to a violation of our community guidelines.' . ($reason ? ' Reason: ' . $reason : ''),
                        null
                    );
                }
                
                // 通知举报者
                createNotification(
                    $report['user_id'],
                    'report_resolved',
                    'Your report has been reviewed and resolved by an administrator.',
                    $report['id']
                );
                
                // 提交事务
                $pdo->commit();
                
                $success = 'The report has been resolved successfully.';
            } catch (Exception $e) {
                // 回滚事务
                $pdo->rollBack();
                $error = 'An error occurred while resolving the report: ' . $e->getMessage();
            }
        }
    }
}

// 获取举报过滤条件
$filter = $_GET['filter'] ?? 'pending';

// 构建查询
$query = "
    SELECT r.*, u.username as reporter_username, p.title as post_title, p.content as post_content,
        pu.username as post_username, ru.username as resolved_by_username
    FROM reports r
    JOIN users u ON r.user_id = u.id
    JOIN posts p ON r.post_id = p.id
    JOIN users pu ON p.user_id = pu.id
    LEFT JOIN users ru ON r.resolved_by = ru.id
    WHERE 1=1
";

$params = [];

// 添加过滤条件
if ($filter === 'pending') {
    $query .= " AND r.status = 'pending'";
} elseif ($filter === 'resolved') {
    $query .= " AND r.status = 'resolved'";
}

// 添加排序
$query .= " ORDER BY r.created_at DESC";

// 执行查询
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Reports Management</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="get" action="" class="d-flex">
                            <select name="filter" class="form-select me-2">
                                <option value="pending" <?php echo ($filter === 'pending') ? 'selected' : ''; ?>>Pending Reports</option>
                                <option value="resolved" <?php echo ($filter === 'resolved') ? 'selected' : ''; ?>>Resolved Reports</option>
                                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All Reports</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0 pt-2">Total: <?php echo count($reports); ?> reports</p>
                    </div>
                </div>
                
                <?php if (empty($reports)): ?>
                <div class="alert alert-info">
                    No reports found matching your criteria.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reporter</th>
                                <th>Post</th>
                                <th>Reason</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['id']; ?></td>
                                <td><?php echo h($report['reporter_username']); ?></td>
                                <td>
                                    <a href="post.php?id=<?php echo $report['post_id']; ?>" target="_blank">
                                        <?php echo h(substr($report['post_title'], 0, 30)) . (strlen($report['post_title']) > 30 ? '...' : ''); ?>
                                    </a>
                                    <div class="small text-muted">by <?php echo h($report['post_username']); ?></div>
                                </td>
                                <td><?php echo h($report['reason']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                <td>
                                    <?php if ($report['status'] === 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Resolved</span>
                                    <div class="small text-muted">
                                        <?php echo ucfirst($report['resolution']); ?> by <?php echo h($report['resolved_by_username']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($report['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-primary view-report" data-bs-toggle="modal" data-bs-target="#reportModal" 
                                        data-id="<?php echo $report['id']; ?>"
                                        data-reporter="<?php echo h($report['reporter_username']); ?>"
                                        data-post-id="<?php echo $report['post_id']; ?>"
                                        data-post-title="<?php echo h($report['post_title']); ?>"
                                        data-post-content="<?php echo h($report['post_content']); ?>"
                                        data-post-username="<?php echo h($report['post_username']); ?>"
                                        data-reason="<?php echo h($report['reason']); ?>"
                                        data-created="<?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>Resolved</button>
                                    <?php endif; ?>
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

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Reporter:</strong> <span id="modal-reporter"></span></p>
                        <p><strong>Date:</strong> <span id="modal-created"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Reason:</strong> <span id="modal-reason"></span></p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Reported Post</h6>
                    </div>
                    <div class="card-body">
                        <h5 id="modal-post-title"></h5>
                        <div class="small text-muted mb-2">by <span id="modal-post-username"></span></div>
                        <p id="modal-post-content"></p>
                        <a id="modal-post-link" href="#" target="_blank" class="btn btn-sm btn-outline-primary">View Post</a>
                    </div>
                </div>
                
                <form id="resolve-form" method="post" action="">
                    <input type="hidden" name="report_id" id="modal-report-id">
                    
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <div class="d-flex">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="action" id="action-ignore" value="ignore" checked>
                                <label class="form-check-label" for="action-ignore">
                                    Ignore Report
                                </label>
                            </div>
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="action" id="action-delete" value="delete">
                                <label class="form-check-label" for="action-delete">
                                    Delete Post
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action" id="action-ban" value="ban">
                                <label class="form-check-label" for="action-ban">
                                    Ban User
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason (optional)</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                        <div class="form-text">This will be included in the notification to the user.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Resolve Report</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 处理查看举报
    const viewButtons = document.querySelectorAll('.view-report');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const reporter = this.getAttribute('data-reporter');
            const postId = this.getAttribute('data-post-id');
            const postTitle = this.getAttribute('data-post-title');
            const postContent = this.getAttribute('data-post-content');
            const postUsername = this.getAttribute('data-post-username');
            const reason = this.getAttribute('data-reason');
            const created = this.getAttribute('data-created');
            
            document.getElementById('modal-report-id').value = id;
            document.getElementById('modal-reporter').textContent = reporter;
            document.getElementById('modal-reason').textContent = reason;
            document.getElementById('modal-created').textContent = created;
            document.getElementById('modal-post-title').textContent = postTitle;
            document.getElementById('modal-post-username').textContent = postUsername;
            document.getElementById('modal-post-content').textContent = postContent;
            
            const postLink = document.getElementById('modal-post-link');
            postLink.href = 'post.php?id=' + postId;
        });
    });
});
</script>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 