<?php
// Including the head
require_once 'includes/header.php';

// Check whether the user has logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'You must be logged in to create a post.';
    $_SESSION['flash_type'] = 'danger';
    redirect('login.php');
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
    
    // 获取匿名发帖时的个人信息
    $anonymousGender = $isAnonymous && isset($_POST['anonymous_gender']) ? trim($_POST['anonymous_gender']) : null;
    $anonymousGrade = $isAnonymous && isset($_POST['anonymous_grade']) ? trim($_POST['anonymous_grade']) : null;
    $anonymousMajor = $isAnonymous && isset($_POST['anonymous_major']) ? trim($_POST['anonymous_major']) : null;
    
    // 验证输入
    if (empty($title) || empty($content) || $categoryId <= 0) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($title) > 200) {
        $error = 'Title must be less than 200 characters.';
    } else {
        // 过滤敏感词
        $filteredContent = filterSensitiveWords($content);
        
        // create post
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, category_id, title, content, is_anonymous, anonymous_gender, anonymous_grade, anonymous_major, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            getCurrentUserId(),
            $categoryId,
            $title,
            $filteredContent,
            $isAnonymous,
            $anonymousGender,
            $anonymousGrade,
            $anonymousMajor
        ]);
        
        if ($result) {
            $postId = $pdo->lastInsertId();
            
            $_SESSION['flash_message'] = 'Your post has been created successfully.';
            $_SESSION['flash_type'] = 'success';
            
            redirect('post.php?id=' . $postId);
        } else {
            $error = 'An error occurred while creating your post. Please try again.';
        }
    }
}
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Create New Post</h5>
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
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $_POST['title'] ?? ''; ?>" required>
                        <div class="form-text">Maximum 200 characters.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required><?php echo $_POST['content'] ?? ''; ?></textarea>
                        <div class="form-text">Your post will be automatically filtered for sensitive words.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="anonymous-checkbox" name="anonymous" <?php echo (isset($_POST['anonymous'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="anonymous-checkbox">Post anonymously</label>
                        <div class="form-text">Your username will be hidden when posting anonymously.</div>
                    </div>
                    
                    <div id="anonymous-info" class="card mb-3 p-3" style="display: none;">
                        <h6 class="mb-3">Optional Anonymous Information</h6>
                        <p class="text-muted small mb-3">You can choose to display some information about yourself while still remaining anonymous.</p>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="anonymous_gender" class="form-label">Gender</label>
                                <select class="form-select" id="anonymous_gender" name="anonymous_gender">
                                    <option value="">Don't display</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="anonymous_grade" class="form-label">Grade/Year</label>
                                <select class="form-select" id="anonymous_grade" name="anonymous_grade">
                                    <option value="">Don't display</option>
                                    <option value="Freshman">Freshman</option>
                                    <option value="Sophomore">Sophomore</option>
                                    <option value="Junior">Junior</option>
                                    <option value="Senior">Senior</option>
                                    <option value="Graduate">Graduate</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="anonymous_major" class="form-label">Major</label>
                                <input type="text" class="form-control" id="anonymous_major" name="anonymous_major" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Create Post</button>
                        <a href="forum.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const anonymousCheckbox = document.getElementById('anonymous-checkbox');
    const anonymousInfo = document.getElementById('anonymous-info');
    
    // 初始化显示/隐藏匿名信息区域
    anonymousInfo.style.display = anonymousCheckbox.checked ? 'block' : 'none';
    
    // 监听匿名复选框的变化
    anonymousCheckbox.addEventListener('change', function() {
        anonymousInfo.style.display = this.checked ? 'block' : 'none';
    });
});
</script>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 