<?php
// 包含头部
require_once 'includes/header.php';

// 获取分类ID（如果有）
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;

// 获取搜索关键词（如果有）
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询
$query = "
    SELECT p.*, u.username, u.avatar, c.name as category_name, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
";

$params = [];

// 如果有分类ID，添加到查询
if ($categoryId) {
    $query .= " WHERE p.category_id = ?";
    $params[] = $categoryId;
}

// 如果有搜索关键词，添加到查询
if (!empty($search)) {
    if ($categoryId) {
        $query .= " AND (p.title LIKE ? OR p.content LIKE ?)";
    } else {
        $query .= " WHERE (p.title LIKE ? OR p.content LIKE ?)";
    }
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 添加排序
$query .= " ORDER BY p.created_at DESC";

// 准备并执行查询
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// 获取所有分类
$stmt = $pdo->query("SELECT * FROM categories ORDER BY 
    CASE 
        WHEN name = 'General' THEN 2
        WHEN name = 'Housing' THEN 1
        ELSE 0
    END, 
    name");
$categories = $stmt->fetchAll();

// 获取当前分类名称（如果有）
$currentCategory = null;
if ($categoryId) {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $currentCategory = $stmt->fetchColumn();
}

// 获取最新帖子
$stmt = $pdo->query("
    SELECT p.*, u.username, c.name as category_name, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$latestPosts = $stmt->fetchAll();

// 获取热门帖子
$stmt = $pdo->query("
    SELECT p.*, u.username, c.name as category_name, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    ORDER BY like_count DESC, comment_count DESC
    LIMIT 5
");
$popularPosts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Campus Forum</title>
    <!-- Bootstrap CSS - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/twitter-bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - 使用国内CDN -->
    <link href="https://cdn.staticfile.org/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if ($currentCategory): ?>
                    Posts in <?php echo h($currentCategory); ?>
                    <?php elseif (!empty($search)): ?>
                    Search Results for "<?php echo h($search); ?>"
                    <?php else: ?>
                    All Posts
                    <?php endif; ?>
                </h5>
                <?php if (isLoggedIn()): ?>
                <a href="create_post.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> New Post
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($posts)): ?>
                <div class="p-4 text-center">
                    <?php if (!empty($search)): ?>
                    <p>No posts found matching "<?php echo h($search); ?>".</p>
                    <?php elseif ($currentCategory): ?>
                    <p>No posts in this category yet.</p>
                    <?php else: ?>
                    <p>No posts yet. Be the first to create a post!</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($posts as $post): ?>
                    <div class="list-group-item <?php echo $post['is_anonymous'] ? 'anonymous-post' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="text-decoration-none">
                                    <?php echo h($post['title']); ?>
                                </a>
                            </h5>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo substr(h($post['content']), 0, 200) . (strlen($post['content']) > 200 ? '...' : ''); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Posted by: 
                                <?php if ($post['is_anonymous']): ?>
                                Anonymous
                                <?php if (!empty($post['anonymous_gender']) || !empty($post['anonymous_grade']) || !empty($post['anonymous_major'])): ?>
                                <span class="text-muted">
                                    (
                                    <?php 
                                    $info = [];
                                    if (!empty($post['anonymous_gender'])) $info[] = h($post['anonymous_gender']);
                                    if (!empty($post['anonymous_grade'])) $info[] = h($post['anonymous_grade']);
                                    if (!empty($post['anonymous_major'])) $info[] = h($post['anonymous_major']);
                                    echo implode(', ', $info);
                                    ?>
                                    )
                                </span>
                                <?php endif; ?>
                                <?php else: ?>
                                    <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none d-inline-flex align-items-center">
                                        <span class="me-1">
                                            <?php if (!empty($post['avatar'])): ?>
                                            <img src="uploads/avatars/<?php echo h($post['avatar']); ?>" class="rounded-circle" width="20" height="20" alt="User Avatar">
                                            <?php else: ?>
                                            <?php echo getInitialsAvatar($post['username'], 20); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo h($post['username']); ?>
                                    </a>
                                <?php endif; ?>
                                in 
                                <a href="forum.php?category=<?php echo $post['category_id']; ?>" class="text-decoration-none">
                                    <?php echo h($post['category_name']); ?>
                                </a>
                            </small>
                            <div>
                                <span class="badge bg-primary rounded-pill" title="Likes">
                                    <i class="fas fa-thumbs-up"></i> <?php echo $post['like_count']; ?>
                                </span>
                                <span class="badge bg-secondary rounded-pill" title="Comments">
                                    <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Search</h5>
            </div>
            <div class="card-body">
                <form action="forum.php" method="get">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search posts..." value="<?php echo h($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Latest Posts</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($latestPosts)): ?>
                <div class="p-4 text-center">
                    <p>No posts yet.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($latestPosts as $post): ?>
                    <a href="post.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo h($post['title']); ?></h6>
                            <small class="text-muted"><?php echo date('M d', strtotime($post['created_at'])); ?></small>
                        </div>
                        <small class="text-muted">
                            by <?php echo $post['is_anonymous'] ? 'Anonymous' : h($post['username']); ?>
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Popular Posts</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($popularPosts)): ?>
                <div class="p-4 text-center">
                    <p>No popular posts yet.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($popularPosts as $post): ?>
                    <a href="post.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo h($post['title']); ?></h6>
                            <small class="text-muted"><?php echo $post['like_count']; ?> <i class="fas fa-thumbs-up"></i></small>
                        </div>
                        <small class="text-muted">
                            <?php echo $post['comment_count']; ?> comments
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Categories</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="forum.php" class="list-group-item list-group-item-action <?php echo !$categoryId ? 'active' : ''; ?>">
                        All Categories
                    </a>
                    <?php foreach ($categories as $category): ?>
                    <a href="forum.php?category=<?php echo $category['id']; ?>" class="list-group-item list-group-item-action <?php echo $categoryId == $category['id'] ? 'active' : ''; ?>">
                        <?php echo h($category['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 