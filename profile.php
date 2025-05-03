<?php
// 包含头部
require_once 'includes/header.php';

// 获取用户ID
$userId = isset($_GET['id']) ? (int)$_GET['id'] : getCurrentUserId();

// 如果用户未登录且未指定用户ID，则重定向到登录页面
if (!isLoggedIn() && !isset($_GET['id'])) {
    $_SESSION['flash_message'] = 'You must be logged in to view your profile.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 获取用户信息
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 如果用户不存在，显示错误
if (!$user) {
    $_SESSION['flash_message'] = 'User not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 检查是否是当前用户的个人资料
$isOwnProfile = isLoggedIn() && $userId == getCurrentUserId();

// 处理个人资料更新
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    // 处理头像上传
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif');
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $error = 'Only JPG, PNG, and GIF images are allowed.';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $error = 'Image size must be less than 2MB.';
        } else {
            $uploadDir = 'uploads/avatars/';
            
            // 确保上传目录存在
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一的文件名
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                // 删除旧头像
                if (!empty($user['avatar'])) {
                    $oldAvatar = $uploadDir . $user['avatar'];
                    if (file_exists($oldAvatar) && strpos($user['avatar'], 'avatar_' . $userId) === 0) {
                        unlink($oldAvatar);
                    }
                }
                
                // 更新用户头像
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$filename, $userId]);
                $success = 'Profile avatar updated successfully.';
                
                // 更新本地用户信息
                $user['avatar'] = $filename;
            } else {
                $error = 'Failed to upload avatar. Please try again.';
            }
        }
    }
    
    // 只有在没有头像上传错误时处理个人资料更新
    if (empty($error) && isset($_POST['email'])) {
        $bio = trim($_POST['bio'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($bio) > 500) {
            $error = 'Bio is too long. Maximum 500 characters allowed.';
        } else {
            // 检查邮箱是否已被使用
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Email is already in use by another account.';
            } else {
                // 如果要更改密码
                if (!empty($newPassword)) {
                    // 验证当前密码
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $hashedPassword = $stmt->fetchColumn();
                    
                    if (!password_verify($currentPassword, $hashedPassword)) {
                        $error = 'Current password is incorrect.';
                    } elseif (strlen($newPassword) < 8) {
                        $error = 'New password must be at least 8 characters long.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'New passwords do not match.';
                    } else {
                        // 更新用户信息和密码
                        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, bio = ?, password = ? WHERE id = ?");
                        $stmt->execute([$email, $bio, $hashedNewPassword, $userId]);
                        $success = 'Profile and password updated successfully.';
                    }
                } else {
                    // 只更新用户信息
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, bio = ? WHERE id = ?");
                    $stmt->execute([$email, $bio, $userId]);
                    if (empty($success)) { // 避免覆盖头像上传的成功消息
                        $success = 'Profile updated successfully.';
                    }
                }
                
                // 重新获取用户信息
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }
        }
    }
}

// 获取用户的帖子
$stmt = $pdo->prepare("
    SELECT p.*, 
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count
    FROM posts p
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$posts = $stmt->fetchAll();

// 获取用户的评论
$stmt = $pdo->prepare("
    SELECT c.*, p.title as post_title, p.id as post_id
    FROM comments c
    JOIN posts p ON c.post_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$comments = $stmt->fetchAll();

// 获取用户的统计信息
$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->execute([$userId]);
$postCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$stmt->execute([$userId]);
$commentCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM post_likes pl
    JOIN posts p ON pl.post_id = p.id
    WHERE p.user_id = ?
");
$stmt->execute([$userId]);
$receivedLikesCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE user_id = ?");
$stmt->execute([$userId]);
$givenLikesCount = $stmt->fetchColumn();
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">User Profile</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if (!empty($user['avatar'])): ?>
                    <img src="uploads/avatars/<?php echo h($user['avatar']); ?>" class="rounded-circle mb-3" width="120" height="120" alt="User Avatar">
                    <?php else: ?>
                    <div class="avatar-circle mx-auto mb-3" style="width: 120px; height: 120px; font-size: 48px;">
                        <?php echo getUserInitials($user['username']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($isOwnProfile): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="fas fa-camera me-1"></i> Change Avatar
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <h4><?php echo h($user['username']); ?></h4>
                    <p class="text-muted">
                        <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge bg-danger">Administrator</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">User</span>
                        <?php endif; ?>
                        
                        <?php if (isset($user['is_banned']) && $user['is_banned']): ?>
                        <span class="badge bg-danger">Banned</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6>Bio</h6>
                    <p><?php echo !empty($user['bio']) ? h($user['bio']) : '<em class="text-muted">No bio provided</em>'; ?></p>
                </div>
                
                <div class="mb-3">
                    <h6>Statistics</h6>
                    <div class="row text-center">
                        <div class="col-6 mb-2">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $postCount; ?></div>
                                <div class="stat-label">Posts</div>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $commentCount; ?></div>
                                <div class="stat-label">Comments</div>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $receivedLikesCount; ?></div>
                                <div class="stat-label">Received Likes</div>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $givenLikesCount; ?></div>
                                <div class="stat-label">Given Likes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!$isOwnProfile && isLoggedIn()): ?>
                <div class="d-grid gap-2">
                    <a href="chat.php?user=<?php echo $user['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-comment-dots me-1"></i> Send Message
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($isOwnProfile): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit Profile</h5>
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
                
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo h($user['username']); ?>" disabled>
                        <div class="form-text">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo h($user['email']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo h($user['bio'] ?? ''); ?></textarea>
                        <div class="form-text">Maximum 500 characters.</div>
                    </div>
                    
                    <hr>
                    
                    <h6>Change Password</h6>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <div class="form-text">Leave blank if you don't want to change your password.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Recent Posts</h5>
            </div>
            <div class="card-body">
                <?php if (empty($posts)): ?>
                <p class="text-muted">No posts yet.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($posts as $post): ?>
                    <a href="post.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo h($post['title']); ?></h6>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo h(substr($post['content'], 0, 100)) . (strlen($post['content']) > 100 ? '...' : ''); ?></p>
                        <small class="text-muted">
                            <i class="fas fa-comment me-1"></i> <?php echo $post['comment_count']; ?> comments
                            <i class="fas fa-heart ms-2 me-1"></i> <?php echo $post['like_count']; ?> likes
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($postCount > 5): ?>
                <div class="text-center mt-3">
                    <a href="forum.php?user=<?php echo $userId; ?>" class="btn btn-outline-primary btn-sm">View All Posts</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Recent Comments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($comments as $comment): ?>
                    <a href="post.php?id=<?php echo $comment['post_id']; ?>#comment-<?php echo $comment['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">On: <?php echo h($comment['post_title']); ?></h6>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo h(substr($comment['content'], 0, 100)) . (strlen($comment['content']) > 100 ? '...' : ''); ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($commentCount > 5): ?>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-outline-primary btn-sm">View All Comments</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.avatar-initials {
    color: white;
    font-size: 48px;
    font-weight: bold;
}

.stat-box {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
}
</style>

<?php
// 包含底部
require_once 'includes/footer.php';
?>

<!-- 头像上传模态框 -->
<?php if ($isOwnProfile): ?>
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avatarModalLabel">Change Profile Avatar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo h($user['avatar']); ?>" class="rounded-circle mb-3" width="150" height="150" alt="Current Avatar">
                        <p class="text-muted">Current Avatar</p>
                        <?php else: ?>
                        <div class="avatar-circle mx-auto mb-3" style="width: 150px; height: 150px; font-size: 60px;">
                            <?php echo getUserInitials($user['username']); ?>
                        </div>
                        <p class="text-muted">You don't have a custom avatar yet</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="avatar" class="form-label">Upload New Avatar</label>
                        <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" required>
                        <div class="form-text">
                            Max file size: 2MB. Allowed file types: JPG, PNG, GIF.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="preview-avatar" name="preview_avatar">
                            <label class="form-check-label" for="preview-avatar">
                                Preview avatar before saving
                            </label>
                        </div>
                    </div>
                    
                    <div id="avatar-preview" class="text-center mb-3" style="display: none;">
                        <h6>Preview</h6>
                        <img id="preview-image" src="#" class="rounded-circle" width="150" height="150" alt="Avatar Preview">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Avatar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 头像预览脚本
document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('avatar');
    const previewCheckbox = document.getElementById('preview-avatar');
    const previewContainer = document.getElementById('avatar-preview');
    const previewImage = document.getElementById('preview-image');
    
    previewCheckbox.addEventListener('change', function() {
        if (this.checked && avatarInput.files.length > 0) {
            previewContainer.style.display = 'block';
            readURL(avatarInput);
        } else {
            previewContainer.style.display = 'none';
        }
    });
    
    avatarInput.addEventListener('change', function() {
        if (previewCheckbox.checked && this.files.length > 0) {
            previewContainer.style.display = 'block';
            readURL(this);
        }
    });
    
    function readURL(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImage.setAttribute('src', e.target.result);
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }
});
</script>
<?php endif; ?> 