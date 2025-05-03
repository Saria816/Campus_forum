<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    redirect('index.php');
}

// 获取令牌和邮箱
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// 验证令牌和邮箱
$validToken = false;
$userId = null;
$tokenExpired = false;

if (!empty($token) && !empty($email)) {
    // 检查令牌是否有效
    $stmt = $pdo->prepare("
        SELECT pr.user_id, pr.expires_at, u.username 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.email = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token, $email]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        $validToken = true;
        $userId = $reset['user_id'];
        $username = $reset['username'];
    } else {
        // 检查令牌是否过期
        $stmt = $pdo->prepare("
            SELECT 1 FROM password_resets
            WHERE token = ? AND email = ? AND expires_at <= NOW()
        ");
        $stmt->execute([$token, $email]);
        $tokenExpired = $stmt->fetchColumn();
    }
}

// 处理表单提交
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // 更新密码
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $userId]);
        
        if ($result) {
            // 删除重置令牌
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $success = 'Your password has been reset successfully. You can now log in with your new password.';
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Campus Forum</title>
    <!-- Bootstrap CSS - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/twitter-bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Campus Forum</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-center mb-4">Reset Password</h5>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <hr>
                            <p class="mb-0">You can now <a href="login.php">log in</a> with your new password.</p>
                        </div>
                        <?php elseif ($tokenExpired): ?>
                        <div class="alert alert-warning">
                            <h6>Password Reset Link Expired</h6>
                            <p>Your password reset link has expired. For security reasons, these links are only valid for 1 hour.</p>
                            <hr>
                            <p class="mb-0">Please <a href="forgot_password.php">request a new password reset link</a>.</p>
                        </div>
                        <?php elseif (!$validToken): ?>
                        <div class="alert alert-danger">
                            <h6>Invalid Password Reset Link</h6>
                            <p>The password reset link you followed is invalid or has already been used.</p>
                            <hr>
                            <p class="mb-0">Please <a href="forgot_password.php">request a new password reset link</a>.</p>
                        </div>
                        <?php else: ?>
                        <form method="post" action="reset_password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>">
                            <div class="mb-3">
                                <p>Hello <strong><?php echo h($username); ?></strong>, you can set a new password below:</p>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0"><a href="login.php">Back to Login</a></p>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Home</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery - 使用国内CDN -->
    <script src="https://cdn.staticfile.org/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap JS - 使用国内CDN -->
    <script src="https://cdn.staticfile.org/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html> 