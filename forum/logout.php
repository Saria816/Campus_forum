<?php
// 包含函数文件
require_once 'includes/functions.php';

// 销毁会话
session_destroy();

// 重定向到登录页面
redirect('login.php');
?> 