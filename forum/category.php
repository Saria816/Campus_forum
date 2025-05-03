<?php
// 启动会话
session_start();

// 包含函数文件
require_once 'includes/functions.php';

// 获取分类ID（如果有）
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// 重定向到论坛页面
if ($categoryId) {
    redirect('forum.php?category=' . $categoryId);
} else {
    redirect('forum.php');
}
?> 