# Database Update Instructions

This document provides instructions on how to update the database structure to address issues with the notification and chat functions.

## Problem description

When sending messages and using the chat function, the system may encounter the following problems:

1. An error occurred when sending the message ：
```
PHP Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'message' in 'field list'
```
2. The chat interface shows "undefined" instead of the actual message content.

These problems are caused by the mismatch between the column names in the database and those used in the code 。

## Solution

We provide two methods to update the database structure:

### Method 1: Update using the Web interface (recommended)

1. Upload the `update.sql` and `update_db.php` files to the root directory of your website
2. Visit "http:// your website /`update_db.php`" in the browser
3. Complete the update according to the instructions on the page

### Method 2: Manually execute the SQL file

1. Log in to your MySQL server
2. Choose `campus_forum` database：`USE campus_forum;`
3. Execute the SQL statements in `update.sql` 

## Update

此更新将执行以下操作：

1. For `notifications` table：
    - If the table does not exist, create the table
    - If `message` column exist，rename it into `content`
    - If `reference_id` column exist，rename it into `related_id`
    - If `content` or `related_id` column does not exist，then add these columns
    - Add necessary indexes to improve performance

2. For `messages` table：
    - If `message` column exist，rename it into `content`
    - If `content` column does not exist，then add this column

## Notice

- Please back up your database before updating
- Make sure that your MySQL users have sufficient permissions to perform ALTER TABLE operations
- If you encounter any problems, please check the error messages and solutions on the `update_db.php` page

## Updated verification

After the update is completed, you can verify whether the update was successful through the following steps:

1. Log in to your account
2. Send a message to another user
3. Log in with another user account
4. Check the notification page to confirm whether the message notifications are displayed correctly
5. Check the chat interface to confirm whether the message is displayed correctly instead of showing "undefined"