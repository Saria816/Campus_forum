-- 为posts表添加匿名发帖时的个人信息字段
ALTER TABLE posts ADD COLUMN anonymous_gender VARCHAR(10) NULL AFTER is_anonymous;
ALTER TABLE posts ADD COLUMN anonymous_grade VARCHAR(20) NULL AFTER anonymous_gender;
ALTER TABLE posts ADD COLUMN anonymous_major VARCHAR(50) NULL AFTER anonymous_grade; 