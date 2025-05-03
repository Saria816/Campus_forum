-- 更新数据库结构的SQL文件

-- 检查notifications表是否存在
SET @table_exists = 0;
SELECT COUNT(*) INTO @table_exists FROM information_schema.tables 
WHERE table_schema = 'campus_forum' AND table_name = 'notifications';

-- 如果notifications表存在，则检查并更新列名
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND column_name = 'message';

-- 如果message列存在，将其重命名为content
SET @rename_sql = IF(@column_exists > 0, 'ALTER TABLE notifications CHANGE message content TEXT NOT NULL', 'SELECT 1');
PREPARE stmt FROM @rename_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查reference_id列是否存在
SET @ref_column_exists = 0;
SELECT COUNT(*) INTO @ref_column_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND column_name = 'reference_id';

-- 如果reference_id列存在，将其重命名为related_id
SET @rename_ref_sql = IF(@ref_column_exists > 0, 'ALTER TABLE notifications CHANGE reference_id related_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @rename_ref_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 如果notifications表不存在，则创建它
SET @create_table_sql = IF(@table_exists = 0, 'CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    related_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');

PREPARE stmt FROM @create_table_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 如果notifications表存在但没有content列，则添加它
SET @content_exists = 0;
SELECT COUNT(*) INTO @content_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND column_name = 'content';

SET @add_content_sql = IF(@content_exists = 0 AND @table_exists > 0, 'ALTER TABLE notifications ADD COLUMN content TEXT NOT NULL AFTER type', 'SELECT 1');
PREPARE stmt FROM @add_content_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 如果notifications表存在但没有related_id列，则添加它
SET @related_exists = 0;
SELECT COUNT(*) INTO @related_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND column_name = 'related_id';

SET @add_related_sql = IF(@related_exists = 0 AND @table_exists > 0, 'ALTER TABLE notifications ADD COLUMN related_id INT NULL AFTER content', 'SELECT 1');
PREPARE stmt FROM @add_related_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查索引是否存在并创建
SET @idx_user_exists = 0;
SELECT COUNT(*) INTO @idx_user_exists FROM information_schema.statistics
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND index_name = 'idx_notifications_user_id';

SET @create_idx_user_sql = IF(@idx_user_exists = 0, 'ALTER TABLE notifications ADD INDEX idx_notifications_user_id (user_id)', 'SELECT 1');
PREPARE stmt FROM @create_idx_user_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_read_exists = 0;
SELECT COUNT(*) INTO @idx_read_exists FROM information_schema.statistics
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND index_name = 'idx_notifications_is_read';

SET @create_idx_read_sql = IF(@idx_read_exists = 0, 'ALTER TABLE notifications ADD INDEX idx_notifications_is_read (is_read)', 'SELECT 1');
PREPARE stmt FROM @create_idx_read_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_date_exists = 0;
SELECT COUNT(*) INTO @idx_date_exists FROM information_schema.statistics
WHERE table_schema = 'campus_forum' AND table_name = 'notifications' AND index_name = 'idx_notifications_created_at';

SET @create_idx_date_sql = IF(@idx_date_exists = 0, 'ALTER TABLE notifications ADD INDEX idx_notifications_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @create_idx_date_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查messages表是否存在
SET @messages_table_exists = 0;
SELECT COUNT(*) INTO @messages_table_exists FROM information_schema.tables 
WHERE table_schema = 'campus_forum' AND table_name = 'messages';

-- 如果messages表存在，则检查并更新列名
SET @message_column_exists = 0;
SELECT COUNT(*) INTO @message_column_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'messages' AND column_name = 'message';

-- 如果message列存在，将其重命名为content
SET @rename_message_sql = IF(@message_column_exists > 0, 'ALTER TABLE messages CHANGE message content TEXT NOT NULL', 'SELECT 1');
PREPARE stmt FROM @rename_message_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 如果messages表存在但没有content列，则添加它
SET @message_content_exists = 0;
SELECT COUNT(*) INTO @message_content_exists FROM information_schema.columns 
WHERE table_schema = 'campus_forum' AND table_name = 'messages' AND column_name = 'content';

SET @add_message_content_sql = IF(@message_content_exists = 0 AND @messages_table_exists > 0, 'ALTER TABLE messages ADD COLUMN content TEXT NOT NULL AFTER receiver_id', 'SELECT 1');
PREPARE stmt FROM @add_message_content_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查password_resets表是否存在
SET @pr_table_exists = 0;
SELECT COUNT(*) INTO @pr_table_exists FROM information_schema.tables 
WHERE table_schema = 'campus_forum' AND table_name = 'password_resets';

-- 如果password_resets表不存在，则创建它
SET @create_pr_table_sql = IF(@pr_table_exists = 0, 'CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reset (user_id),
    UNIQUE KEY unique_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');

PREPARE stmt FROM @create_pr_table_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 如果表存在但没有必要的列，添加它们
SET @check_user_id_column = IF(@pr_table_exists > 0, 'SELECT COUNT(*) INTO @user_id_exists FROM information_schema.columns
WHERE table_schema = \'campus_forum\' AND table_name = \'password_resets\' AND column_name = \'user_id\'', 'SELECT 1');

PREPARE stmt FROM @check_user_id_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_user_id_sql = IF(@pr_table_exists > 0 AND @user_id_exists = 0, 'ALTER TABLE password_resets ADD COLUMN user_id INT NOT NULL AFTER id,
ADD CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');

PREPARE stmt FROM @add_user_id_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加唯一键
SET @check_unique_token = IF(@pr_table_exists > 0, 'SELECT COUNT(*) INTO @unique_token_exists FROM information_schema.statistics
WHERE table_schema = \'campus_forum\' AND table_name = \'password_resets\' AND index_name = \'unique_token\'', 'SELECT 1');

PREPARE stmt FROM @check_unique_token;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_unique_token_sql = IF(@pr_table_exists > 0 AND @unique_token_exists = 0, 'ALTER TABLE password_resets ADD UNIQUE KEY unique_token (token)', 'SELECT 1');

PREPARE stmt FROM @add_unique_token_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加用户唯一键
SET @check_unique_user = IF(@pr_table_exists > 0, 'SELECT COUNT(*) INTO @unique_user_exists FROM information_schema.statistics
WHERE table_schema = \'campus_forum\' AND table_name = \'password_resets\' AND index_name = \'unique_user_reset\'', 'SELECT 1');

PREPARE stmt FROM @check_unique_user;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_unique_user_sql = IF(@pr_table_exists > 0 AND @unique_user_exists = 0, 'ALTER TABLE password_resets ADD UNIQUE KEY unique_user_reset (user_id)', 'SELECT 1');

PREPARE stmt FROM @add_unique_user_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加is_banned列到users表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'users' 
AND column_name = 'is_banned';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "is_banned column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加banned_until列到users表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'users' 
AND column_name = 'banned_until';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN banned_until DATETIME DEFAULT NULL',
    'SELECT "banned_until column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加ban_reason列到users表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'users' 
AND column_name = 'ban_reason';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN ban_reason TEXT DEFAULT NULL',
    'SELECT "ban_reason column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加banned_by列到users表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'users' 
AND column_name = 'banned_by';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN banned_by INT DEFAULT NULL, ADD FOREIGN KEY (banned_by) REFERENCES users(id)',
    'SELECT "banned_by column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加banned_at列到users表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'users' 
AND column_name = 'banned_at';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN banned_at DATETIME DEFAULT NULL',
    'SELECT "banned_at column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加responded_by列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'responded_by';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN responded_by INT DEFAULT NULL, ADD FOREIGN KEY (responded_by) REFERENCES users(id)',
    'SELECT "responded_by column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加response列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'response';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN response TEXT DEFAULT NULL',
    'SELECT "response column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加responded_at列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'responded_at';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN responded_at DATETIME DEFAULT NULL',
    'SELECT "responded_at column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加department列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'department';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN department VARCHAR(50) DEFAULT NULL',
    'SELECT "department column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加priority列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'priority';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN priority ENUM("low", "medium", "high") DEFAULT "medium"',
    'SELECT "priority column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加status列到staff_messages表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'staff_messages' 
AND column_name = 'status';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE staff_messages ADD COLUMN status ENUM("pending", "in_progress", "resolved") DEFAULT "pending"',
    'SELECT "status column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加added_by列到sensitive_words表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'sensitive_words' 
AND column_name = 'added_by';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE sensitive_words ADD COLUMN added_by INT DEFAULT NULL, ADD FOREIGN KEY (added_by) REFERENCES users(id)',
    'SELECT "added_by column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加added_at列到sensitive_words表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'sensitive_words' 
AND column_name = 'added_at';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE sensitive_words ADD COLUMN added_at DATETIME DEFAULT NULL',
    'SELECT "added_at column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加is_active列到sensitive_words表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'sensitive_words' 
AND column_name = 'is_active';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE sensitive_words ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT "is_active column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加created_at列到sensitive_words表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'sensitive_words' 
AND column_name = 'created_at';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE sensitive_words ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT "created_at column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加updated_at列到sensitive_words表
SELECT COUNT(*) INTO @exists
FROM information_schema.columns 
WHERE table_schema = 'campus_forum' 
AND table_name = 'sensitive_words' 
AND column_name = 'updated_at';

SET @query = IF(
    @exists = 0,
    'ALTER TABLE sensitive_words ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "updated_at column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 