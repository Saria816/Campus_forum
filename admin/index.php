<?php
// 启动会话
session_start();

// 包含函数文件
require_once '../includes/functions.php';
require_once '../includes/db.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to access the admin area.';
    $_SESSION['flash_type'] = 'danger';
    redirect('../login.php');
}

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access the admin area.';
    $_SESSION['flash_type'] = 'danger';
    redirect('../index.php');
}

// 重定向到管理员仪表盘
redirect('../admin_dashboard.php');
?> 