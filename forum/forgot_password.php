<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    redirect('index.php');
}

// 处理表单提交
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // 检查邮箱是否存在
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // 生成重置令牌
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1小时后过期
            
            // 存储重置令牌到数据库
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, email, token, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()
            ");
            $stmt->execute([$user['id'], $email, $token, $expires]);
            
            // 发送密码重置邮件
            if (sendPasswordResetEmail($email, $token)) {
                $success = 'Password reset instructions have been sent to your email.';
            } else {
                $error = 'Failed to send password reset email. Please try again later.';
            }
        } else {
            // 出于安全考虑，不向用户透露邮箱是否存在
            $success = 'If your email address exists in our database, you will receive a password recovery link shortly.';
        }
    }
}

// 检查是否需要显示发送的邮件（仅用于演示）
$showEmail = false;
if (isset($_SESSION['password_reset_email_to'])) {
    $emailTo = $_SESSION['password_reset_email_to'];
    $emailSubject = $_SESSION['password_reset_email_subject'];
    $emailMessage = $_SESSION['password_reset_email_message'];
    $showEmail = true;
    
    // 清除session中的邮件信息
    unset($_SESSION['password_reset_email_to']);
    unset($_SESSION['password_reset_email_subject']);
    unset($_SESSION['password_reset_email_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Campus Forum</title>
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
                        <h5 class="card-title text-center mb-4">Forgot Password</h5>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <hr>
                            <p class="mb-0">Please check your email for instructions on how to reset your password.</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($success)): ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-text">Enter the email address you used to register.</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            </div>
                        </form>
                        <?php endif; ?>
                        
                        <!-- 显示发送的邮件内容（仅用于演示） -->
                        <?php if ($showEmail): ?>
                        <div class="mt-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Demo: Password Reset Email</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>To:</strong> <?php echo h($emailTo); ?></p>
                                    <p><strong>Subject:</strong> <?php echo h($emailSubject); ?></p>
                                    <hr>
                                    <pre class="bg-light p-3"><?php echo h($emailMessage); ?></pre>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-info-circle me-2"></i> This is a demonstration. In a production environment, an actual email would be sent to the user.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">Remember your password? <a href="login.php">Login</a></p>
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