<?php
// 数据库更新脚本

// 获取数据库连接信息
require_once 'includes/db.php';

// 从PDO连接中提取数据库连接信息
$dsn = "mysql:host=$host;dbname=$database;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

echo "<h1>数据库更新</h1>";
echo "<p>开始执行数据库更新...</p>";

try {
    // 使用mysqli连接数据库
    $mysqli = new mysqli($host, $username, $password, $database);
    
    // 检查连接
    if ($mysqli->connect_error) {
        throw new Exception("数据库连接失败: " . $mysqli->connect_error);
    }
    
    // 读取SQL文件内容
    $sql = file_get_contents('update.sql');
    
    // 设置多语句查询
    if ($mysqli->multi_query($sql)) {
        $successCount = 0;
        $errorCount = 0;
        
        do {
            // 存储第一个结果集
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
            
            if ($mysqli->error) {
                echo "<p style='color: orange;'>警告: " . $mysqli->error . "</p>";
                $errorCount++;
            } else {
                $successCount++;
            }
            
            // 检查是否有更多查询
        } while ($mysqli->more_results() && $mysqli->next_result());
        
        if ($errorCount > 0) {
            echo "<p style='color: orange;'>数据库更新完成，但有 $errorCount 个警告。</p>";
        } else {
            echo "<p style='color: green;'>数据库更新成功完成！共执行了 $successCount 个SQL语句。</p>";
        }
    } else {
        echo "<p style='color: red;'>数据库更新失败: " . $mysqli->error . "</p>";
    }
    
    $mysqli->close();
    
    echo "<p>notifications表已更新，列名已调整为与代码匹配。</p>";
    echo "<p><a href='index.php'>返回首页</a></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>数据库更新失败: " . $e->getMessage() . "</p>";
    
    // 提供更详细的错误信息和解决方案
    echo "<p>您可以尝试以下解决方案：</p>";
    echo "<ol>";
    echo "<li>确保您的MySQL用户有足够的权限执行ALTER TABLE操作。</li>";
    echo "<li>手动登录MySQL，执行update.sql文件中的SQL语句。</li>";
    echo "<li>检查数据库连接信息是否正确（includes/db.php）。</li>";
    echo "</ol>";
    
    echo "<p><a href='index.php'>返回首页</a></p>";
}
?> 