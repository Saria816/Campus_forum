<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access this page.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 处理回复留言
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');
    $status = $_POST['status'] ?? '';
    
    // 验证输入
    if ($messageId <= 0) {
        $error = 'Invalid message ID.';
    } elseif (empty($response)) {
        $error = 'Please enter a response.';
    } elseif (!in_array($status, ['pending', 'in_progress', 'resolved'])) {
        $error = 'Invalid status.';
    } else {
        // 更新留言
        $stmt = $pdo->prepare("
            UPDATE staff_messages 
            SET response = ?, status = ?, responded_by = ?, responded_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$response, $status, getCurrentUserId(), $messageId]);
        
        if ($result) {
            // 获取用户ID
            $stmt = $pdo->prepare("SELECT user_id FROM staff_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $userId = $stmt->fetchColumn();
            
            // 创建通知
            createNotification(
                $userId,
                'staff_message',
                'Staff has responded to your message.',
                $messageId
            );
            
            $success = 'Your response has been sent to the user.';
        } else {
            $error = 'An error occurred while sending your response.';
        }
    }
}

// 获取留言过滤条件
$filter = $_GET['filter'] ?? 'all';
$department = $_GET['department'] ?? '';

// 构建查询
$query = "
    SELECT sm.*, u.username, 
        CASE 
            WHEN sm.status = 'pending' THEN 'Pending'
            WHEN sm.status = 'in_progress' THEN 'In Progress'
            WHEN sm.status = 'resolved' THEN 'Resolved'
            ELSE sm.status
        END as status_text,
        ru.username as responded_by_username
    FROM staff_messages sm
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN users ru ON sm.responded_by = ru.id
    WHERE 1=1
";

$params = [];

// 添加过滤条件
if ($filter === 'pending') {
    $query .= " AND sm.status = 'pending'";
} elseif ($filter === 'in_progress') {
    $query .= " AND sm.status = 'in_progress'";
} elseif ($filter === 'resolved') {
    $query .= " AND sm.status = 'resolved'";
}

if (!empty($department)) {
    $query .= " AND sm.department = ?";
    $params[] = $department;
}

// 添加排序
$query .= " ORDER BY sm.created_at DESC";

// 执行查询
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// 获取部门列表
$stmt = $pdo->query("SELECT DISTINCT department FROM staff_messages ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Staff Messages Management</h5>
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
                                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All Messages</option>
                                <option value="pending" <?php echo ($filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo ($filter === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo ($filter === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                            <select name="department" class="form-select me-2">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>" <?php echo ($department === $dept) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $dept)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0 pt-2">Total: <?php echo count($messages); ?> messages</p>
                    </div>
                </div>
                
                <?php if (empty($messages)): ?>
                <div class="alert alert-info">
                    No messages found matching your criteria.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Department</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                            <tr>
                                <td><?php echo $message['id']; ?></td>
                                <td><?php echo h($message['username']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $message['department'])); ?></td>
                                <td><?php echo h($message['subject']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($message['created_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo ($message['status'] === 'resolved') ? 'bg-success' : (($message['status'] === 'in_progress') ? 'bg-warning' : 'bg-secondary'); ?>">
                                        <?php echo $message['status_text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary view-message" data-bs-toggle="modal" data-bs-target="#messageModal" 
                                        data-id="<?php echo $message['id']; ?>"
                                        data-username="<?php echo h($message['username']); ?>"
                                        data-department="<?php echo ucfirst(str_replace('_', ' ', $message['department'])); ?>"
                                        data-subject="<?php echo h($message['subject']); ?>"
                                        data-message="<?php echo h($message['message']); ?>"
                                        data-created="<?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>"
                                        data-status="<?php echo $message['status']; ?>"
                                        data-response="<?php echo h($message['response'] ?? ''); ?>"
                                        data-responded-by="<?php echo h($message['responded_by_username'] ?? ''); ?>"
                                        data-responded-at="<?php echo !empty($message['responded_at']) ? date('M d, Y H:i', strtotime($message['responded_at'])) : ''; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
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

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>From:</strong> <span id="modal-username"></span></p>
                        <p><strong>Department:</strong> <span id="modal-department"></span></p>
                        <p><strong>Date:</strong> <span id="modal-created"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Subject:</strong> <span id="modal-subject"></span></p>
                        <p><strong>Status:</strong> <span id="modal-status-text"></span></p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Message</h6>
                    </div>
                    <div class="card-body">
                        <p id="modal-message"></p>
                    </div>
                </div>
                
                <div id="response-section">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Response</h6>
                        </div>
                        <div class="card-body">
                            <p id="modal-response"></p>
                            <div class="text-muted small">
                                <span id="modal-responded-by"></span>
                                <span id="modal-responded-at"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form id="response-form" method="post" action="">
                    <input type="hidden" name="message_id" id="modal-message-id">
                    
                    <div class="mb-3">
                        <label for="response" class="form-label">Your Response</label>
                        <textarea class="form-control" id="response" name="response" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Update Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send Response</button>
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
    // 处理查看消息
    const viewButtons = document.querySelectorAll('.view-message');
    const responseSection = document.getElementById('response-section');
    const responseForm = document.getElementById('response-form');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const department = this.getAttribute('data-department');
            const subject = this.getAttribute('data-subject');
            const message = this.getAttribute('data-message');
            const created = this.getAttribute('data-created');
            const status = this.getAttribute('data-status');
            const response = this.getAttribute('data-response');
            const respondedBy = this.getAttribute('data-responded-by');
            const respondedAt = this.getAttribute('data-responded-at');
            
            document.getElementById('modal-message-id').value = id;
            document.getElementById('modal-username').textContent = username;
            document.getElementById('modal-department').textContent = department;
            document.getElementById('modal-subject').textContent = subject;
            document.getElementById('modal-message').textContent = message;
            document.getElementById('modal-created').textContent = created;
            
            // 设置状态文本
            let statusText = 'Pending';
            let statusClass = 'bg-secondary';
            
            if (status === 'in_progress') {
                statusText = 'In Progress';
                statusClass = 'bg-warning';
            } else if (status === 'resolved') {
                statusText = 'Resolved';
                statusClass = 'bg-success';
            }
            
            document.getElementById('modal-status-text').innerHTML = `<span class="badge ${statusClass}">${statusText}</span>`;
            
            // 设置状态下拉框
            document.getElementById('status').value = status;
            
            // 如果有回复，显示回复部分
            if (response) {
                document.getElementById('modal-response').textContent = response;
                document.getElementById('modal-responded-by').textContent = respondedBy ? `Responded by: ${respondedBy}` : '';
                document.getElementById('modal-responded-at').textContent = respondedAt ? ` on ${respondedAt}` : '';
                responseSection.style.display = 'block';
                document.getElementById('response').value = response;
            } else {
                responseSection.style.display = 'none';
                document.getElementById('response').value = '';
            }
        });
    });
});
</script>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 