<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to contact staff.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 处理表单提交
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    // 验证输入
    if (empty($subject) || empty($message) || empty($department)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($subject) > 200) {
        $error = 'Subject must be less than 200 characters.';
    } else {
        // 过滤敏感词
        $filteredMessage = filterSensitiveWords($message);
        
        // 保存留言
        $stmt = $pdo->prepare("
            INSERT INTO staff_messages (user_id, department, subject, message, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        $result = $stmt->execute([getCurrentUserId(), $department, $subject, $filteredMessage]);
        
        if ($result) {
            $success = 'Your message has been sent to the staff. They will respond as soon as possible.';
            
            // 清空表单
            $subject = '';
            $message = '';
            $department = '';
        } else {
            $error = 'An error occurred while sending your message. Please try again.';
        }
    }
}

// 获取用户的留言历史
$stmt = $pdo->prepare("
    SELECT sm.*, 
        CASE 
            WHEN sm.status = 'pending' THEN 'Pending'
            WHEN sm.status = 'in_progress' THEN 'In Progress'
            WHEN sm.status = 'resolved' THEN 'Resolved'
            ELSE sm.status
        END as status_text
    FROM staff_messages sm
    WHERE sm.user_id = ?
    ORDER BY sm.created_at DESC
");
$stmt->execute([getCurrentUserId()]);
$messages = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Contact Staff</h5>
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
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="academic" <?php echo ($department === 'academic') ? 'selected' : ''; ?>>Academic Affairs</option>
                            <option value="technical" <?php echo ($department === 'technical') ? 'selected' : ''; ?>>Technical Support</option>
                            <option value="financial" <?php echo ($department === 'financial') ? 'selected' : ''; ?>>Financial Services</option>
                            <option value="student_services" <?php echo ($department === 'student_services') ? 'selected' : ''; ?>>Student Services</option>
                            <option value="other" <?php echo ($department === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo h($subject ?? ''); ?>" required>
                        <div class="form-text">Maximum 200 characters.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="6" required><?php echo h($message ?? ''); ?></textarea>
                        <div class="form-text">Please provide as much detail as possible.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Your Messages</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($messages)): ?>
                <div class="p-4 text-center">
                    <p>You haven't sent any messages to staff yet.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($messages as $msg): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo h($msg['subject']); ?></h6>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></small>
                        </div>
                        <p class="mb-1 text-truncate"><?php echo h(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Department: <?php echo ucfirst(str_replace('_', ' ', $msg['department'])); ?></small>
                            <span class="badge <?php echo ($msg['status'] === 'resolved') ? 'bg-success' : (($msg['status'] === 'in_progress') ? 'bg-warning' : 'bg-secondary'); ?>">
                                <?php echo $msg['status_text']; ?>
                            </span>
                        </div>
                        <?php if (!empty($msg['response'])): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small class="fw-bold">Staff Response:</small>
                            <p class="mb-0 small"><?php echo h($msg['response']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Contact Information</h5>
            </div>
            <div class="card-body">
                <p>For urgent matters, please contact us directly:</p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-phone me-2"></i> +44 123 456 7890</li>
                    <li><i class="fas fa-envelope me-2"></i> support@liverpool.ac.uk</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Student Services Center, University of Liverpool</li>
                </ul>
                <p class="mb-0 small text-muted">Office hours: Monday to Friday, 9:00 AM - 5:00 PM</p>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 