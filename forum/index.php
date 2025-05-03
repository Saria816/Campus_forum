<?php
// 包含头部
require_once 'includes/header.php';

// 获取所有帖子
$stmt = $pdo->query("
    SELECT p.*, u.username, u.avatar, c.name as category_name, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC
");
$allPosts = $stmt->fetchAll();

// 获取分类
$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as post_count
    FROM categories c
    LEFT JOIN posts p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY 
        CASE 
            WHEN c.name = 'General' THEN 2
            WHEN c.name = 'Housing' THEN 1
            ELSE 0
        END, 
        c.name
");
$categories = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Welcome to Campus Forum</h5>
            </div>
            <div class="card-body">
                <p class="lead">A place for students to connect, share ideas, and engage in meaningful discussions.</p>
                <p>Join our community to:</p>
                <ul>
                    <li>Discuss academic topics and share resources</li>
                    <li>Connect with fellow students</li>
                    <li>Stay updated on campus events</li>
                    <li>Get help with assignments and projects</li>
                </ul>
                
                <?php if (isLoggedIn()): ?>
                <div class="mt-3 mb-3">
                    <a href="forum.php" class="btn btn-primary">Browse Forum</a>
                </div>
                
                <hr>
                
                <h5 class="mb-3">Quick Actions</h5>
                <div class="d-grid gap-2">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <a href="create_post.php" class="btn btn-outline-primary w-100">Create New Post</a>
                        </div>
                        <div class="col-md-6 mb-2">
                            <a href="chat.php" class="btn btn-outline-primary w-100">Chat with Friends</a>
                        </div>
                        <div class="col-md-6 mb-2">
                            <a href="ai_chat.php" class="btn btn-outline-primary w-100">Ask AI Assistant</a>
                        </div>
                        <div class="col-md-6 mb-2">
                            <a href="staff_message.php" class="btn btn-outline-primary w-100">Contact Staff</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="mt-3">
                    <a href="register.php" class="btn btn-primary me-2">Register</a>
                    <a href="login.php" class="btn btn-outline-primary">Login</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Posts</h5>
                <?php if (isLoggedIn()): ?>
                <a href="create_post.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> New Post
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allPosts)): ?>
                <div class="p-4 text-center">
                    <p>No posts yet. Be the first to create a post!</p>
                </div>
                <?php else: ?>
                <div class="post-list">
                    <?php foreach ($allPosts as $post): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <?php if ($post['is_anonymous']): ?>
                                    <div class="avatar-placeholder">
                                        <i class="fas fa-user-secret"></i>
                                    </div>
                                    <?php else: ?>
                                    <a href="profile.php?id=<?php echo $post['user_id']; ?>">
                                        <?php if (!empty($post['avatar'])): ?>
                                        <img src="uploads/avatars/<?php echo h($post['avatar']); ?>" class="rounded-circle" width="40" height="40" alt="User Avatar">
                                        <?php else: ?>
                                        <?php echo getInitialsAvatar($post['username'], 40); ?>
                                        <?php endif; ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title">
                                        <a href="post.php?id=<?php echo $post['id']; ?>" class="text-decoration-none"><?php echo h($post['title']); ?></a>
                                    </h5>
                                    <p class="card-text"><?php echo substr(h($post['content']), 0, 150) . (strlen($post['content']) > 150 ? '...' : ''); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Posted by 
                                            <?php if ($post['is_anonymous']): ?>
                                            Anonymous
                                            <?php else: ?>
                                            <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none">
                                                <?php echo h($post['username']); ?>
                                            </a>
                                            <?php endif; ?>
                                            in 
                                            <a href="forum.php?category=<?php echo $post['category_id']; ?>" class="text-decoration-none">
                                                <?php echo h($post['category_name']); ?>
                                            </a>
                                            on <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
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
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="forum.php" class="btn btn-sm btn-outline-primary">View All Posts</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Categories</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($categories as $category): ?>
                    <a href="forum.php?category=<?php echo $category['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <?php echo h($category['name']); ?>
                        <span class="badge bg-primary rounded-pill"><?php echo $category['post_count']; ?></span>
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