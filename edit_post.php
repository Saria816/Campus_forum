<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to edit a post.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
}

// 检查是否提供了帖子ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = 'Invalid post ID.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

$postId = (int)$_GET['id'];

// 获取帖子详情
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM posts p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");

$stmt->execute([$postId]);
$post = $stmt->fetch();

// 检查帖子是否存在
if (!$post) {
    $_SESSION['flash_message'] = 'Post not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('forum.php');
}

// 检查用户是否有权限编辑帖子
if ($post['user_id'] != getCurrentUserId() && !isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to edit this post.';
    $_SESSION['flash_type'] = 'danger';
    redirect('post.php?id=' . $postId);
}

// 获取所有分类
$stmt = $pdo->query("SELECT * FROM categories ORDER BY 
    CASE 
        WHEN name = 'General' THEN 2
        WHEN name = 'Housing' THEN 1
        ELSE 0
    END, 
    name");
$categories = $stmt->fetchAll();

// 处理表单提交
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $isAnonymous = isset($_POST['anonymous']) ? 1 : 0;
    
    // 验证输入
    if (empty($title) || empty($content) || $categoryId <= 0) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($title) > 200) {
        $error = 'Title must be less than 200 characters.';
    } else {
        // 过滤敏感词
        $filteredContent = filterSensitiveWords($content);
        
        // 更新帖子
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET category_id = ?, title = ?, content = ?, is_anonymous = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $categoryId,
            $title,
            $filteredContent,
            $isAnonymous,
            $postId
        ]);
        
        if ($result) {
            $_SESSION['flash_message'] = 'Your post has been updated successfully.';
            $_SESSION['flash_type'] = 'success';
            
            redirect('post.php?id=' . $postId);
        } else {
            $error = 'An error occurred while updating your post. Please try again.';
        }
    }
}
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit Post</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo h($post['title']); ?>" required>
                        <div class="form-text">Maximum 200 characters.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($post['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required><?php echo h($post['content']); ?></textarea>
                        <div class="form-text">Your post will be automatically filtered for sensitive words.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="anonymous-checkbox" name="anonymous" <?php echo ($post['is_anonymous']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="anonymous-checkbox">Post anonymously</label>
                        <div class="form-text">Your username will be hidden when posting anonymously.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Post</button>
                        <a href="post.php?id=<?php echo $postId; ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 