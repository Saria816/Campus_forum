**Campus Forum**
> 以下内容基于完整源码、`Campus Forum Code Framework.docx`、`README_UPDATE.md`、`update_db.php` 等文件以及在服务器实际部署后生成的 MySQL 数据库结构整理而成。

---

### 1 整体存储方案概览
| 维度 | 设计要点 |
|------|---------|
|**基础栈**|LAMP（Linux + Apache/Nginx + MySQL + PHP）；数据库当前单体部署在同一云主机，可后续拆分读写或做托管 RDS。|
|**数据模型**|标准关系模型；业务对象以 **MySQL 表** 映射并通过外键 / 触发器维系引用完整性。|
|**访问方式**|PHP **PDO** + 预处理语句，所有 CRUD 均封装在函数 / 控制器中，避免了手写 SQL 注入风险。示例：`notifications.php` 中的 `prepare + execute` 调用。|
|**持久介质**|结构化数据 → MySQL；非结构化数据（头像文件）→ 本地文件系统 `assets/images/uploads/`（未来可迁移至 OSS）。|
|**开发工具**|逻辑建模 & DDL：MySQL Workbench；日常运维 & 可视化：Navicat 17。|

```
// notifications.php（片段）-- 访问方式
$query  = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();
```
---

### 2 逻辑数据模型
> 采用“核心业务表 + 辅助表”分层，表与功能一一对应，必要时配合枚举字段减少 Join。

| 领域 | 关键表 | 说明 |
|------|--------|------|
|**用户与权限**|`users`|保存账号、角色(`role` ENUM)、状态(`status` ENUM)、哈希密码等。|
|**论坛内容**|`categories`, `posts`, `comments`, `post_likes`|帖子与评论均带 `is_anonymous` 标志；`post_likes` 复合唯一索引 `(post_id, user_id)` 防重复点赞。|
|**私信 & 通知**|`messages`, `notifications`|`messages.is_read` tinyint 做已读标志；`notifications.type` ENUM + `related_id` 实现多态关联。更新脚本对列名不一致问题做了迁移。|
|**运营 & 内容安全**|`reports`, `sensitive_words`, `staff_messages`|违规上报、敏感词、与管理员沟通；后台可对这些表做增删改。|
|**AI 助手**|`ai_chat_messages`（在  Workbench Schema 中可见）|记录人机对话历史，为日后做语料分析或训练打基础。|

---

### 3 物理设计要点

| 项目 | 实践 | 补充说明 |
|------|------|----------|
|**字符集**|全部库与表统一 `utf8mb4_general_ci`，满足 emoji & 多语言。|
|**引擎**|默认 InnoDB；支持事务 & 行级锁，方便后期分布式。|
|**索引**|主键自增 BIGINT；所有外键列 (`*_id`) 均建普通索引；高频查询列额外加复合索引，例如：<br>`messages (receiver_id, is_read, created_at)` —— 支撑“未读私信”查询。|
|**约束**|必填字段 `NOT NULL`；级联删除策略：删除帖子同步删评论/点赞，举报采用软删避免法律争议。|
|**分区/分表**|当前数据量 <1 M；单表足够。预计 50 W+ 帖子或 100 W+ 消息后，可：<br>• `messages` 按 `sender_id % 8` 哈希分表<br>• `posts` 按年月分区（主键带时间戳）。|
|**文件存储**|头像等图片落地 `assets/images/uploads/{userId}_{rand}.{ext}`；目录使用 Nginx `location` 禁止执行 PHP，防木马。|
|**备份策略**|Navicat 定时导出 SQL + 阿里云磁盘快照；生产建议 `mysqldump --single-transaction` 日备 + 周全量。|

---

### 4 数据访问层（DAL）实现

1. **连接池**：`includes/db.php` 通过单例返回全局 `$pdo`，连接参数（`host`,`dbname`,`charset` …）硬编码，需迁至 `.env`。
2. **DAO/Service**：大部分业务逻辑直接写在页面或控制器；可抽象为 `UserRepository`, `PostRepository` 提升可测试性。
3. **事务**：目前仅删除用户/帖子场景需级联；建议在 `functions.php` 提供 `beginTransaction()` 包装。
4. **缓存**：敏感词、首页热门帖未缓存；可引入 **Redis + APCu**——Redis 存全局 KV，APCu 做 PHP-FPM 本地缓存，减少查询。

```
// includes/db.php -- 连接池
$host     = '127.0.0.1';
$username = 'root';
$password = '2255172';
$database = 'campus_forum';
$charset  = 'utf8mb4';

$dsn  = "mysql:host=$host;dbname=$database;charset=$charset";
$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, $username, $password, $opts);
$GLOBALS['pdo'] = $pdo;&#8203;:contentReference[oaicite:2]{index=2}&#8203;:contentReference[oaicite:3]{index=3}
```
```
// functions.php（filterSensitiveWords）-- 敏感词过滤
function filterSensitiveWords($text) {
global $pdo;
$stmt  = $pdo->query("SELECT word FROM sensitive_words");
$words = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($words as $word) {
        $replacement = str_repeat('*', mb_strlen($word));
        $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', $replacement, $text);
    }
    return $text;
}&#8203;:contentReference[oaicite:4]{index=4}&#8203;:contentReference[oaicite:5]{index=5}
```
---

### 5 数据迁移与版本控制

| 环节 | 现状 | 建议 |
|------|------|------|
|**DDL 变更**|`update.sql` + `update_db.php` 支持一次性脚本执行；脚本读取 SQL 并回显结果。|引入 **Phinx/Laravel Migrations** 记录迁移 ID 并支持回滚。|
|**热修**|`README_UPDATE.md` 指导手动/网页执行。|加 CI：Push→自动跑 `php artisan migrate`；或 GitHub Actions 部署前验证。|
|**数据清洗**|AI 助手虚拟用户 `ai@example.com`；上线前需脚本迁移或标记系统账号。|

```
<?php
// update_db.php -- 数据库脚本更新
require_once 'includes/db.php';          // 复用 PDO 配置

$mysqli = new mysqli($host, $username, $password, $database);
$sql    = file_get_contents('update.sql');

if ($mysqli->multi_query($sql)) {
    // 逐条执行并统计结果 …
} else {
    echo "<p style='color: red;'>Database update failed: " . $mysqli->error . "</p>";
}
$mysqli->close();&#8203;:contentReference[oaicite:6]{index=6}&#8203;:contentReference[oaicite:7]{index=7}
```
---

### 6 安全与合规

* **SQL 注入**：站点全用 PDO 预处理；后台/AI 接口同样防护。
* **数据脱敏**：日志不输出邮箱/手机号明文；生产备份应 AES 加密。
* **合规**：若在中国大陆上线，`users` 表须预留实名字段（加密）；涉及 EU 用户需支持 GDPR 删除请求。
* **会话**：登录态存 `PHPSESSID` Cookie；建议 `SameSite=Lax` + HTTPS。

---

### 7 性能与扩展规划

1. **读写分离**：MySQL 主从或云 RDS；写主读从。
2. **搜索服务**：帖子数 > 100 W 接入 **ElasticSearch**；正文入 ES，实现全文检索&推荐。
3. **冷数据归档**：一年以上帖子/私信归档 S3+Athena；MySQL 仅保元数据。
4. **消息队列**：点赞/通知异步入队（RabbitMQ/Kafka），提升峰值 TPS。

---

### 8 改进 Roadmap（优先级）
| # | 动作 | 价值 |
|---|------|------|
|1|凭据 & DeepSeek Key 移入 `.env` 并统一读取|消除泄露风险|
|2|补充 `users.email` 等索引|加速高频查询|
|3|引入 Redis 缓存层|平均响应 -30 %|
|4|Phinx 迁移 + GitHook|可回滚，易审计|
|5|`messages` 哈希分片 PoC|验证横向扩展|
|6|文件上传迁 OSS+CDN|带宽/存储弹性|

---

**结语**  
当前 Campus Forum 数据层沿用经典 LAMP+MySQL，并借助 PDO 预处理保证安全与可维护性。随规模增长，可按上方路线图迭代到“LAMP”的成熟架构，平滑支撑 10 倍流量。
