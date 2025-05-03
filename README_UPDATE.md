# 数据库更新说明

本文档提供了关于如何更新数据库结构以解决通知和聊天功能问题的说明。

## 问题描述

在发送消息和使用聊天功能时，系统会出现以下问题：

1. 发送消息时出现错误：
```
PHP Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'message' in 'field list'
```

2. 聊天界面显示 "undefined" 而不是实际消息内容。

这些问题是由于数据库中的列名与代码中使用的列名不匹配导致的。

## 解决方案

我们提供了两种方法来更新数据库结构：

### 方法一：使用 Web 界面更新（推荐）

1. 将 `update.sql` 和 `update_db.php` 文件上传到您的网站根目录
2. 在浏览器中访问 `http://您的网站/update_db.php`
3. 按照页面上的指示完成更新

### 方法二：手动执行 SQL 文件

1. 登录到您的 MySQL 服务器
2. 选择 `campus_forum` 数据库：`USE campus_forum;`
3. 执行 `update.sql` 文件中的 SQL 语句

## 更新内容

此更新将执行以下操作：

1. 对于 `notifications` 表：
   - 如果表不存在，则创建该表
   - 如果 `message` 列存在，将其重命名为 `content`
   - 如果 `reference_id` 列存在，将其重命名为 `related_id`
   - 如果 `content` 或 `related_id` 列不存在，则添加这些列
   - 添加必要的索引以提高性能

2. 对于 `messages` 表：
   - 如果 `message` 列存在，将其重命名为 `content`
   - 如果 `content` 列不存在，则添加该列

## 注意事项

- 更新前请备份您的数据库
- 确保您的 MySQL 用户有足够的权限执行 ALTER TABLE 操作
- 如果您遇到任何问题，请查看 `update_db.php` 页面上的错误信息和解决方案

## 更新后的验证

更新完成后，您可以通过以下步骤验证更新是否成功：

1. 登录到您的账户
2. 发送一条消息给另一个用户
3. 使用另一个用户账户登录
4. 查看通知页面，确认消息通知是否正确显示
5. 查看聊天界面，确认消息是否正确显示，而不是显示 "undefined" 