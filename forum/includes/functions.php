<?php
// 开启会话（仅在会话未启动时）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否已登录
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 获取当前用户ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// 获取当前用户信息
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// 获取当前用户名
function getCurrentUsername() {
    $user = getCurrentUser();
    return $user ? $user['username'] : 'Guest';
}

// 检查用户是否是管理员
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

// 重定向到指定URL
function redirect($url) {
    if (headers_sent()) {
        echo "<script>window.location.href='$url';</script>";
        exit;
    } else {
        header("Location: $url");
        exit;
    }
}

// 过滤敏感词
function filterSensitiveWords($text) {
    global $pdo;
    
    // 从数据库获取敏感词列表
    $stmt = $pdo->query("SELECT word FROM sensitive_words");
    $words = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($words as $word) {
        $replacement = str_repeat('*', mb_strlen($word));
        $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', $replacement, $text);
    }
    
    return $text;
}

// 创建通知
function createNotification($userId, $type, $message, $referenceId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, content, related_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$userId, $type, $message, $referenceId]);
}

// 获取未读通知数量
function getUnreadNotificationsCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

// 验证邮箱域名
function validateEmailDomain($email, $domain = 'liverpool.ac.uk') {
    $emailParts = explode('@', $email);
    if (count($emailParts) !== 2) {
        return false;
    }
    
    return strtolower($emailParts[1]) === strtolower($domain);
}

// 生成随机字符串
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

// 安全地输出HTML
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// 获取匿名用户显示名称
function getAnonymousDisplayName($userId, $showInfo = []) {
    global $pdo;
    
    $displayName = "Anonymous User";
    
    if (!empty($showInfo)) {
        $stmt = $pdo->prepare("SELECT " . implode(", ", $showInfo) . " FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch();
        
        if ($userInfo) {
            $infoArray = [];
            foreach ($showInfo as $info) {
                if (isset($userInfo[$info]) && !empty($userInfo[$info])) {
                    $infoArray[] = $info . ": " . $userInfo[$info];
                }
            }
            
            if (!empty($infoArray)) {
                $displayName .= " (" . implode(", ", $infoArray) . ")";
            }
        }
    }
    
    return $displayName;
}

// 获取用户头像初始字母
function getUserInitials($username) {
    if (empty($username)) {
        return 'U';
    }
    
    // 尝试获取名字的首字母
    $words = explode(' ', $username);
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_substr($word, 0, 1, 'UTF-8');
            if (strlen($initials) >= 2) {
                break;
            }
        }
    }
    
    return mb_strtoupper($initials, 'UTF-8');
}

// 生成初始字母头像HTML
function getInitialsAvatar($username, $size = 40) {
    $initials = getUserInitials($username);
    $colors = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#34495e', '#16a085', '#27ae60', '#2980b9', '#8e44ad', '#2c3e50', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#f39c12', '#d35400', '#c0392b', '#bdc3c7'];
    $colorIndex = abs(crc32($username)) % count($colors);
    $bgColor = $colors[$colorIndex];
    
    return '<div class="avatar-circle" style="width: ' . $size . 'px; height: ' . $size . 'px; background-color: ' . $bgColor . '; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
        ' . $initials . '
    </div>';
}

// 发送密码重置邮件
function sendPasswordResetEmail($email, $token) {
    // 在实际环境中，您需要配置真实的邮件发送功能
    // 这里为演示目的，只是将邮件内容保存到session以便显示
    $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $token . '&email=' . urlencode($email);
    
    $subject = 'Password Reset Request';
    $message = "Hello,\n\n";
    $message .= "You have requested to reset your password on Campus Forum. Please click the link below to reset your password:\n\n";
    $message .= $resetLink . "\n\n";
    $message .= "This link will expire in 1 hour for security reasons.\n\n";
    $message .= "If you did not request this password reset, please ignore this email and your password will remain unchanged.\n\n";
    $message .= "Regards,\n";
    $message .= "Campus Forum Team";
    
    // 存储到session以便显示（仅用于演示）
    $_SESSION['password_reset_email_subject'] = $subject;
    $_SESSION['password_reset_email_message'] = $message;
    $_SESSION['password_reset_email_to'] = $email;
    
    // 实际应用中，您会使用mail()函数或其他邮件库发送邮件
    // mail($email, $subject, $message);
    
    return true;
} 