<?php
// Contain function files
require_once 'includes/db.php';
require_once 'includes/functions.php';

// If the user is logged in, redirect to the home page
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle the submission of the login form
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // search users
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Update the last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Set the success message
            $_SESSION['flash_message'] = 'Welcome back, ' . $user['username'] . '!';
            $_SESSION['flash_type'] = 'success';
            
            redirect('index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Campus Forum</title>
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
                        <h5 class="card-title text-center mb-4">Login to Your Account</h5>
                        
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <div class="mb-3 text-end">
                                <a href="forgot_password.php" class="text-decoration-none small">Forgot Password?</a>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">Don't have an account? <a href="register.php">Register</a></p>
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