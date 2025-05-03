<?php
// 启动会话
session_start();

// 包含函数文件
require_once 'includes/functions.php';
require_once 'includes/db.php';

// 获取当前页面
$current_page = basename($_SERVER['PHP_SELF']);

// 检查是否有闪存消息
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'info';

// 清除闪存消息
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

// 获取未读通知和消息数量
$unread_notifications = 0;
$unread_messages = 0;
if (isLoggedIn()) {
    $userId = getCurrentUserId();
    
    // 获取未读通知数量
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unread_notifications = $stmt->fetchColumn();
    
    // 获取未读消息数量
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unread_messages = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Forum</title>
    <!-- Bootstrap CSS - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/twitter-bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .avatar-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            border-radius: 50%;
        }
        .avatar-placeholder {
            width: 40px;
            height: 40px;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-graduation-cap me-2"></i>Campus Forum
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                                <i class="fas fa-home me-1"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'forum.php') ? 'active' : ''; ?>" href="forum.php">
                                <i class="fas fa-comments me-1"></i> Forum
                            </a>
                        </li>
                        <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'create_post.php') ? 'active' : ''; ?>" href="create_post.php">
                                <i class="fas fa-edit me-1"></i> Create Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>" href="chat.php">
                                <i class="fas fa-comment-dots me-1"></i> Chat
                                <?php if ($unread_messages > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_messages; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'ai_chat.php') ? 'active' : ''; ?>" href="ai_chat.php">
                                <i class="fas fa-robot me-1"></i> AI Chat
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-shield-alt me-1"></i> Admin
                                    <?php 
                                    // 获取管理员待处理事项总数
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
                                    $pendingReports = $stmt->fetchColumn();
                                    
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM staff_messages WHERE status = 'pending'");
                                    $pendingStaffMessages = $stmt->fetchColumn();
                                    
                                    $totalPending = $pendingReports + $pendingStaffMessages;
                                    if ($totalPending > 0):
                                    ?>
                                    <span class="badge bg-danger"><?php echo $totalPending; ?></span>
                                    <?php endif; ?>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                    <li><a class="dropdown-item" href="admin_users.php"><i class="fas fa-users me-2"></i>Manage Users</a></li>
                                    <li><a class="dropdown-item" href="admin_reports.php">
                                        <i class="fas fa-flag me-2"></i>Reports
                                        <?php if ($pendingReports > 0): ?>
                                        <span class="badge bg-danger"><?php echo $pendingReports; ?></span>
                                        <?php endif; ?>
                                    </a></li>
                                    <li><a class="dropdown-item" href="admin_staff_messages.php">
                                        <i class="fas fa-envelope me-2"></i>Staff Messages
                                        <?php if ($pendingStaffMessages > 0): ?>
                                        <span class="badge bg-danger"><?php echo $pendingStaffMessages; ?></span>
                                        <?php endif; ?>
                                    </a></li>
                                    <li><a class="dropdown-item" href="admin_sensitive_words.php"><i class="fas fa-filter me-2"></i>Sensitive Words</a></li>
                                </ul>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link position-relative <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" href="notifications.php">
                                    <i class="fas fa-bell me-1"></i> Notifications
                                    <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger"><?php echo $unread_notifications; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo getCurrentUsername(); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                                    <li><a class="dropdown-item" href="staff_message.php"><i class="fas fa-envelope me-2"></i>Contact Staff</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" href="login.php">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>" href="register.php">
                                    <i class="fas fa-user-plus me-1"></i> Register
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="container py-4">
        <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?> 