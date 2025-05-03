<?php
// 包含头部
require_once 'includes/header.php';

// 检查用户是否是管理员
if (!isAdmin()) {
    $_SESSION['flash_message'] = 'You do not have permission to access this page.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
}

// 处理添加敏感词
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $word = trim($_POST['word'] ?? '');
        
        if (empty($word)) {
            $error = 'Please enter a word.';
        } else {
            // 检查敏感词是否已存在
            $stmt = $pdo->prepare("SELECT id FROM sensitive_words WHERE word = ?");
            $stmt->execute([$word]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'This word is already in the sensitive words list.';
            } else {
                // 添加敏感词
                $stmt = $pdo->prepare("INSERT INTO sensitive_words (word, added_by, created_at) VALUES (?, ?, NOW())");
                $result = $stmt->execute([$word, getCurrentUserId()]);
                
                if ($result) {
                    $success = 'The word has been added to the sensitive words list.';
                } else {
                    $error = 'An error occurred while adding the word.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $wordId = (int)($_POST['word_id'] ?? 0);
        
        if ($wordId <= 0) {
            $error = 'Invalid word ID.';
        } else {
            // 删除敏感词
            $stmt = $pdo->prepare("DELETE FROM sensitive_words WHERE id = ?");
            $result = $stmt->execute([$wordId]);
            
            if ($result) {
                $success = 'The word has been removed from the sensitive words list.';
            } else {
                $error = 'An error occurred while removing the word.';
            }
        }
    }
}

// 获取所有敏感词
$stmt = $pdo->query("
    SELECT sw.*, u.username as added_by_username
    FROM sensitive_words sw
    LEFT JOIN users u ON sw.added_by = u.id
    ORDER BY sw.word
");
$sensitiveWords = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Sensitive Words Management</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <p>Sensitive words are automatically filtered in posts, comments, messages, and other user-generated content.</p>
                
                <form method="post" action="" class="mb-4">
                    <input type="hidden" name="action" value="add">
                    <div class="input-group">
                        <input type="text" class="form-control" name="word" placeholder="Enter a new sensitive word..." required>
                        <button type="submit" class="btn btn-primary">Add Word</button>
                    </div>
                </form>
                
                <h6>Current Sensitive Words:</h6>
                
                <?php if (empty($sensitiveWords)): ?>
                <p class="text-muted">No sensitive words have been added yet.</p>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($sensitiveWords as $word): ?>
                    <div class="col-md-4 mb-2">
                        <div class="sensitive-word d-flex justify-content-between align-items-center">
                            <span><?php echo h($word['word']); ?></span>
                            <form method="post" action="" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="word_id" value="<?php echo $word['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger ms-2" onclick="return confirm('Are you sure you want to remove this word?')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Sensitive Words Guidelines</h5>
            </div>
            <div class="card-body">
                <p>When adding sensitive words, please consider the following guidelines:</p>
                <ul>
                    <li>Add words that are offensive, inappropriate, or violate community standards.</li>
                    <li>Consider adding common misspellings or variations of offensive words.</li>
                    <li>Be careful not to add common words that might be part of legitimate discussions.</li>
                    <li>Review the list periodically to ensure it remains effective and appropriate.</li>
                </ul>
                <p class="mb-0 text-muted">Note: The sensitive words filter is case-insensitive and matches whole words only.</p>
            </div>
        </div>
    </div>
</div>

<?php
// 包含底部
require_once 'includes/footer.php';
?> 