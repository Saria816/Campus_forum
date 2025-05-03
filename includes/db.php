<?php
// 数据库连接配置
$host = '127.0.0.1';
$username = 'root';
$password = '2255172';
$database = 'campus_forum';
$charset = 'utf8mb4';

try {
    // 创建PDO连接
    $dsn = "mysql:host=$host;dbname=$database;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // 设置为全局变量
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    // 显示错误信息
    die("Database connection failed: " . $e->getMessage());
} 