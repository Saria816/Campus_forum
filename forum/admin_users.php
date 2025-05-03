<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access this page.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 处理用户操作
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    // 验证输入
    if ($userId <= 0) {
        $error = 'Invalid user ID.';
    } elseif (!in_array($action, ['ban', 'unban', 'promote', 'demote'])) {
        $error = 'Invalid action.';
    } else {
        // 检查用户是否存在
        $stmt = $pdo->prepare("SELECT id, username, role, is_banned FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'User not found.';
        } elseif ($user['id'] == getCurrentUserId()) {
            $error = 'You cannot perform this action on yourself.';
        } else {
            // 执行操作
            try {
                switch ($action) {
                    case 'ban':
                        if ($user['is_banned']) {
                            $error = 'User is already banned.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, banned_reason = ? WHERE id = ?");
                            $stmt->execute([$reason, $userId]);
                            
                            // 通知用户
                            createNotification(
                                $userId,
                                'user_banned',
                                'Your account has been banned.' . ($reason ? ' Reason: ' . $reason : ''),
                                null
                            );
                            
                            $success = 'User has been banned successfully.';
                        }
                        break;
                        
                    case 'unban':
                        if (!$user['is_banned']) {
                            $error = 'User is not banned.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, banned_reason = NULL WHERE id = ?");
                            $stmt->execute([$userId]);
                            
                            // 通知用户
                            createNotification(
                                $userId,
                                'user_unbanned',
                                'Your account has been unbanned. You can now use the forum again.',
                                null
                            );
                            
                            $success = 'User has been unbanned successfully.';
                        }
                        break;
                        
                    case 'promote':
                        if ($user['role'] === 'admin') {
                            $error = 'User is already an administrator.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                            $stmt->execute([$userId]);
                            
                            // 通知用户
                            createNotification(
                                $userId,
                                'role_changed',
                                'You have been promoted to administrator.',
                                null
                            );
                            
                            $success = 'User has been promoted to administrator successfully.';
                        }
                        break;
                        
                    case 'demote':
                        if ($user['role'] !== 'admin') {
                            $error = 'User is not an administrator.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                            $stmt->execute([$userId]);
                            
                            // 通知用户
                            createNotification(
                                $userId,
                                'role_changed',
                                'You have been demoted from administrator to regular user.',
                                null
                            );
                            
                            $success = 'User has been demoted to regular user successfully.';
                        }
                        break;
                }
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

// 获取过滤条件
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// 构建查询
$query = "
    SELECT u.*, 
        (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
        (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count
    FROM users u
    WHERE 1=1
";

$params = [];

// 添加过滤条件
if ($filter === 'admin') {
    $query .= " AND u.role = 'admin'";
} elseif ($filter === 'banned') {
    $query .= " AND u.is_banned = 1";
}

// 添加搜索条件
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 添加排序
$query .= " ORDER BY u.created_at DESC";

// 执行查询
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">User Management</h5>
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
                    <div class="col-md-8">
                        <form method="get" action="" class="d-flex">
                            <select name="filter" class="form-select me-2" style="width: auto;">
                                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All Users</option>
                                <option value="admin" <?php echo ($filter === 'admin') ? 'selected' : ''; ?>>Administrators</option>
                                <option value="banned" <?php echo ($filter === 'banned') ? 'selected' : ''; ?>>Banned Users</option>
                            </select>
                            <input type="text" name="search" class="form-control me-2" placeholder="Search by username or email" value="<?php echo h($search); ?>">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-0 pt-2">Total: <?php echo count($users); ?> users</p>
                    </div>
                </div>
                
                <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    No users found matching your criteria.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Posts</th>
                                <th>Comments</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo h($user['username']); ?></td>
                                <td><?php echo h($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">Administrator</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_banned']): ?>
                                    <span class="badge bg-danger">Banned</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['post_count']; ?></td>
                                <td><?php echo $user['comment_count']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $user['id']; ?>">
                                            <?php if ($user['id'] != getCurrentUserId()): ?>
                                                <?php if (!$user['is_banned']): ?>
                                                <li><a class="dropdown-item text-danger user-action" href="#" data-bs-toggle="modal" data-bs-target="#userActionModal" data-action="ban" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo h($user['username']); ?>">Ban User</a></li>
                                                <?php else: ?>
                                                <li><a class="dropdown-item text-success user-action" href="#" data-bs-toggle="modal" data-bs-target="#userActionModal" data-action="unban" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo h($user['username']); ?>">Unban User</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                <li><a class="dropdown-item text-primary user-action" href="#" data-bs-toggle="modal" data-bs-target="#userActionModal" data-action="promote" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo h($user['username']); ?>">Promote to Admin</a></li>
                                                <?php else: ?>
                                                <li><a class="dropdown-item text-warning user-action" href="#" data-bs-toggle="modal" data-bs-target="#userActionModal" data-action="demote" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo h($user['username']); ?>">Demote to User</a></li>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <li><a class="dropdown-item disabled" href="#">Cannot modify yourself</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="profile.php?id=<?php echo $user['id']; ?>" target="_blank">View Profile</a></li>
                                        </ul>
                                    </div>
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

<!-- User Action Modal -->
<div class="modal fade" id="userActionModal" tabindex="-1" aria-labelledby="userActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userActionModalLabel">User Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal-user-id">
                    <input type="hidden" name="action" id="modal-action">
                    
                    <p id="modal-confirmation-message"></p>
                    
                    <div id="reason-container" class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                        <div class="form-text">This will be included in the notification to the user.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="modal-submit-btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 处理用户操作
    const userActions = document.querySelectorAll('.user-action');
    
    userActions.forEach(action => {
        action.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            const actionType = this.getAttribute('data-action');
            
            document.getElementById('modal-user-id').value = userId;
            document.getElementById('modal-action').value = actionType;
            
            const reasonContainer = document.getElementById('reason-container');
            const submitBtn = document.getElementById('modal-submit-btn');
            let confirmationMessage = '';
            
            switch (actionType) {
                case 'ban':
                    confirmationMessage = `Are you sure you want to ban user "${username}"?`;
                    submitBtn.textContent = 'Ban User';
                    submitBtn.className = 'btn btn-danger';
                    reasonContainer.style.display = 'block';
                    break;
                case 'unban':
                    confirmationMessage = `Are you sure you want to unban user "${username}"?`;
                    submitBtn.textContent = 'Unban User';
                    submitBtn.className = 'btn btn-success';
                    reasonContainer.style.display = 'none';
                    break;
                case 'promote':
                    confirmationMessage = `Are you sure you want to promote "${username}" to administrator?`;
                    submitBtn.textContent = 'Promote User';
                    submitBtn.className = 'btn btn-primary';
                    reasonContainer.style.display = 'none';
                    break;
                case 'demote':
                    confirmationMessage = `Are you sure you want to demote "${username}" from administrator to regular user?`;
                    submitBtn.textContent = 'Demote User';
                    submitBtn.className = 'btn btn-warning';
                    reasonContainer.style.display = 'none';
                    break;
            }
            
            document.getElementById('modal-confirmation-message').textContent = confirmationMessage;
        });
    });
});
</script>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 