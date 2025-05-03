<?php
// 包含头部
require_once 'includes/header.php';

// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    redirect('index.php');
}

// 处理注册表单提交
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    
    // 验证输入
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Username must be between 3 and 20 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!validateEmailDomain($email, 'liverpool.ac.uk')) {
        $error = 'Email must be from the liverpool.ac.uk domain.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = 'Username already exists.';
        } else {
            // 检查邮箱是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already exists.';
            } else {
                // 创建用户
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, gender, role, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'user', NOW(), NOW())
                ");
                
                $result = $stmt->execute([$username, $email, $hashedPassword, $gender]);
                
                if ($result) {
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'An error occurred. Please try again.';
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0">Create a New Account</h4>
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
                    <p>You can now <a href="login.php">login</a> with your credentials.</p>
                </div>
                <?php else: ?>
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                            </div>
                            <div class="form-text">Choose a unique username (3-20 characters).</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            <div class="form-text">Must be a valid university email address.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="form-text">At least 6 characters.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender (Optional)</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="Male" <?php echo (isset($gender) && $gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($gender) && $gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($gender) && $gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a></label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By registering for an account on Campus Forum, you agree to these Terms and Conditions.</p>
                
                <h6>2. User Conduct</h6>
                <p>Users must not post content that is illegal, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, invasive of another's privacy, or otherwise objectionable.</p>
                
                <h6>3. Privacy Policy</h6>
                <p>Your use of Campus Forum is also governed by our Privacy Policy, which outlines how we collect, use, and protect your personal information.</p>
                
                <h6>4. Account Security</h6>
                <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                
                <h6>5. Content Ownership</h6>
                <p>You retain ownership of the content you post on Campus Forum, but grant us a license to use, modify, and display that content in connection with the service.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 