**Campus Forum Data Storage & Architecture White Paper (V1.0)**
> The following content is based on the full source code, *Campus Forum Code Framework.docx*, *README_UPDATE.md*, *update_db.php*, and the MySQL database structure generated after the application was actually deployed on the server. 

---

### 1 Overall Storage Scheme Overview
| Dimension | Key Points |
|-----------|-----------|
|**Basic Stack**|LAMP (Linux + Apache/Nginx + MySQL + PHP); the database is currently deployed as a single instance on the same cloud host, and can later be split into read–write or migrated to managed RDS.|
|**Data Model**|Standard relational model; business objects are mapped to **MySQL tables** and referential integrity is maintained through foreign keys / triggers.|
|**Access Method**|PHP **PDO** + prepared statements, all CRUD is wrapped in functions / controllers, eliminating the risk of hand-written SQL injection. Example: the `prepare + execute` call in `notifications.php`.|
|**Persistent Medium**|Structured data → MySQL; unstructured data (avatar files) → local file system `assets/images/uploads/` (can be migrated to OSS in the future).|
|**Development Tools**|Logical modeling & DDL: MySQL Workbench; daily O&M & visualization: Navicat 17.|

```
// notifications.php (fragment)-- Access Method
$query  = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();
```
---

### 2 Logical Data Model
> Adopts a layered design of “core business tables + auxiliary tables”, each table corresponds to a function, and ENUM fields are used to reduce joins when necessary.

| Domain | Key Tables | Description |
|--------|------------|-------------|
|**User & Permission**|`users`|Stores account, role (`role` ENUM), status (`status` ENUM), hashed password, etc.|
|**Forum Content**|`categories`, `posts`, `comments`, `post_likes`|Posts and comments both carry an `is_anonymous` flag; `post_likes` has a composite unique index `(post_id, user_id)` to prevent duplicate likes.|
|**Private Message & Notification**|`messages`, `notifications`|`messages.is_read` tinyint marks read status; `notifications.type` ENUM + `related_id` implements polymorphic association. The update script migrated inconsistent column names.|
|**Operation & Content Safety**|`reports`, `sensitive_words`, `staff_messages`|Violation reports, sensitive words, communication with administrators; the backend can create, delete and update these tables.|
|**AI Assistant**|`ai_chat_messages` (visible in Workbench Schema)|Records human–machine conversation history, laying a foundation for corpus analysis or training in the future.|

---

### 3 Physical Design Key Points

| Item | Practice |
|------|----------|
|**Character Set**|All databases and tables use `utf8mb4_general_ci`, supporting emoji & multi-language.|
|**Engine**|Default InnoDB; supports transactions & row-level locks, convenient for later distributed deployment.|
|**Index**|Primary keys are auto-increment BIGINT; all foreign key columns (`*_id`) have normal indexes; high-frequency query columns add extra composite indexes, e.g.:<br>`messages (receiver_id, is_read, created_at)` — supports “unread message” query.|
|**Constraint**|Mandatory fields `NOT NULL`; cascade delete strategy: deleting a post synchronously deletes comments/likes, while reports adopt soft delete to avoid legal disputes.|
|**Partitioning / Sharding**|Current data volume < 1 M, single table is sufficient. When there are > 500 k posts or 1 M messages, you can:<br>• Shard `messages` by `sender_id % 8`<br>• Partition `posts` by year-and-month (primary key carries timestamp).|
|**File Storage**|Avatars and other images fall to `assets/images/uploads/{userId}_{rand}.{ext}`; the directory uses Nginx `location` to prohibit executing PHP, preventing web-shell.|
|**Backup Strategy**|Navicat scheduled SQL export + Alibaba Cloud disk snapshot; production is recommended to use `mysqldump --single-transaction` daily incremental + weekly full.|

---

### 4 Data Access Layer (DAL) Implementation

1. **Connection Pool**: `includes/db.php` returns a global `$pdo` as singleton; connection parameters (`host`,`dbname`,`charset`…) are hard-coded and need to be moved to `.env`.
2. **DAO/Service**: Most business logic is written directly in pages or controllers; it can be abstracted into `UserRepository`, `PostRepository` to improve testability.
3. **Transaction**: Currently needed only when cascading deletion of users/posts; it is recommended to provide `beginTransaction()` wrapper in `functions.php`.
4. **Cache**: Sensitive words and home page hot posts are not cached yet; **Redis + APCu** can be introduced — Redis stores global KV, APCu acts as PHP-FPM local cache to reduce frequent queries.

```
// includes/db.php -- Connection Pool
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
// functions.php（filterSensitiveWords）-- Sensitive word filtering
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

### 5 Data Migration & Version Control

| Phase | Status | Recommendation |
|-------|--------|---------------|
|**DDL Change**|`update.sql` + `update_db.php` support one-shot script execution; the script reads SQL and outputs execution result.|Adopt **Phinx/Laravel Migrations** and other versioned tools to record each migration ID and support rollback.|
|**Hotfix**|`README_UPDATE.md` guides developers to execute updates manually or via web.|Add CI step: Push → automatically run `php artisan migrate`; or use GitHub Actions to verify before deployment.|
|**Data Cleansing**|The AI assistant creates a virtual user `ai@example.com`; before go-live, a unified script needs to migrate data to an official account or mark as system account.|

```
<?php
// update_db.php -- Database script update
require_once 'includes/db.php';          // Reuse the PDO configuration

$mysqli = new mysqli($host, $username, $password, $database);
$sql    = file_get_contents('update.sql');

if ($mysqli->multi_query($sql)) {
    // Carry out each item one by one and count the results
 …
} else {
    echo "<p style='color: red;'>Database update failed: " . $mysqli->error . "</p>";
}
$mysqli->close();&#8203;:contentReference[oaicite:6]{index=6}&#8203;:contentReference[oaicite:7]{index=7}
```
---

### 6 Security & Compliance

* **SQL Injection**: Site-wide PDO prepared statements; admin backend and AI interface are protected as well.
* **Data Masking**: Avoid plaintext email/phone output in logs; production backups should be AES-encrypted.
* **Compliance**: If launching in mainland China, a real-name field (encrypted storage) must be reserved in the `users` table; if EU users are involved, GDPR erasure requests must be supported.
* **Session**: The login state of `users` table is stored in `PHPSESSID` cookie; it is recommended to enable `SameSite=Lax` + strict HTTPS transmission.

---

### 7 Performance & Scalability Plan

1. **Read–Write Split**: MySQL master–slave or cloud RDS read–write instances; writes on master, reads from read-only nodes.
2. **Search Service**: When there are > 1 M posts, connect **ElasticSearch**; the `posts` table keeps only ID + index columns, body goes to ES for full-text search & recommendation.
3. **Cold Data Archive**: Posts/messages older than one year archived to S3 + Athena; MySQL historical partitions keep only metadata, saving hot-disk space.
4. **Message Queue**: Likes/notifications are enqueued asynchronously (RabbitMQ / Kafka) to improve peak TPS.

---

### 8 Improvement Roadmap (Priority Order)
| # | Action | Value |
|---|--------|-------|
|1|Move DB credentials & DeepSeek Key to `.env` and read uniformly|Eliminate key-leak risk|
|2|Add indexes on `users.email`, `messages.receiver_id`, `notifications.user_id` …|Speed up high-freq queries|
|3|Implement Redis cache layer (Sensitive Words / Session)|Avg response -30 %|
|4|Introduce Phinx migrations + GitHook|Rollback-able & auditable|
|5|Sharding PoC: `messages` hash shard|Verify horizontal scalability|
|6|Replace file-system upload with OSS + CDN|Infinite bandwidth & storage|

---

**Closing Remarks**  
The current Campus Forum data layer follows the classic LAMP + MySQL architecture and, together with PDO & prepared statements, already offers basic security and maintainability. As user scale grows, can further gradually evolve along the roadmap above to a mature architecture of “cache + queue + search + shard & partition”, smoothly supporting more than ten-times traffic growth. 
