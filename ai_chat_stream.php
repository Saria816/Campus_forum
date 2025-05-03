<?php
// 禁用任何可能的输出缓冲
@ob_end_clean();
while (ob_get_level()) {
    @ob_end_clean();
}

// 设置响应头 - 必须在任何输出之前设置
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // 禁用Nginx缓冲

// 包含必要的文件，但不包含可能输出HTML的header
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/ai_chat.php';

// 确保输出立即发送到客户端
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ob_implicit_flush(true);

// 发送SSE事件函数
function sendSSEEvent($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// 检查用户是否已登录
if (!isLoggedIn()) {
    sendSSEEvent('error', ['error' => 'You must be logged in to use AI chat.']);
    exit;
}

// 获取当前用户ID
$currentUserId = getCurrentUserId();

// DeepSeek API Key - In production, store this securely in environment variables
$apiKey = 'sk-9759ef1d61564923862cce2cbf64c84d'; // Replace with your actual API key

// 获取与AI助手的聊天记录
$stmt = $pdo->prepare("
    SELECT u_ai.id as ai_id
    FROM users u_ai
    WHERE u_ai.username = 'AI Assistant'
    LIMIT 1
");
$stmt->execute();
$aiUser = $stmt->fetch();

$messages = [];
$aiUserId = null;

if ($aiUser) {
    $aiUserId = $aiUser['ai_id'];
    
    // 获取聊天记录（最近10条消息）
    $stmt = $pdo->prepare("
        SELECT m.*, 
            CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sent
        FROM messages m
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([
        $currentUserId, 
        $currentUserId, 
        $aiUserId, 
        $aiUserId, 
        $currentUserId
    ]);
    
    $messages = array_reverse($stmt->fetchAll());
} else {
    // Create AI Assistant user if it doesn't exist
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, created_at, updated_at) 
        VALUES ('AI Assistant', 'ai@example.com', ?, 'admin', NOW(), NOW())
    ");
    $stmt->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $aiUserId = $pdo->lastInsertId();
}

// 获取用户消息 - 优先检查GET参数，这样EventSource可以正确工作
$userMessage = '';
if (isset($_GET['message']) && !empty($_GET['message'])) {
    $userMessage = trim($_GET['message']);
} elseif (isset($_POST['message']) && !empty($_POST['message'])) {
    $userMessage = trim($_POST['message']);
}

if (empty($userMessage)) {
    sendSSEEvent('error', ['error' => 'Message cannot be empty.']);
    exit;
}

// 如果是POST请求，保存用户消息到数据库
// 如果是GET请求（来自EventSource），则假设消息已经通过之前的POST请求保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, content, created_at, is_read) 
            VALUES (?, ?, ?, NOW(), 1)
        ");
        $stmt->execute([$currentUserId, $aiUserId, $userMessage]);
        
        // 对于POST请求，返回成功状态
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// 开始流式响应
sendSSEEvent('start', ['message' => 'Starting AI response...']);

// 获取流式响应
$fullResponse = '';

// 定义回调函数来处理API返回的数据块
$callback = function($ch, $chunk) use (&$fullResponse) {
    $fullResponse .= $chunk;
    
    // 尝试解析JSON行
    $lines = explode("\n", $chunk);
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $jsonData = substr($line, 6); // 移除 "data: " 前缀
            if ($jsonData === '[DONE]') {
                sendSSEEvent('done', ['message' => 'Response complete']);
            } else {
                try {
                    $data = json_decode($jsonData, true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $content = $data['choices'][0]['delta']['content'];
                        sendSSEEvent('message', ['content' => $content]);
                    }
                } catch (Exception $e) {
                    // 忽略解析错误
                }
            }
        }
    }
    
    return strlen($chunk);
};

// 调用流式API
try {
    // 准备API请求
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    
    // 准备消息数组
    $apiMessages = [];
    
    // 添加系统消息
    $apiMessages[] = [
        'role' => 'system',
        'content' => 'You are a helpful AI assistant that provides accurate and concise information.'
    ];
    
    // 添加聊天历史
    foreach ($messages as $msg) {
        $role = $msg['is_sent'] ? 'user' : 'assistant';
        $apiMessages[] = [
            'role' => $role,
            'content' => $msg['content']
        ];
    }
    
    // 添加当前用户消息
    $apiMessages[] = [
        'role' => 'user',
        'content' => $userMessage
    ];
    
    // 准备请求数据
    $data = [
        'model' => 'deepseek-chat',
        'messages' => $apiMessages,
        'stream' => true
    ];
    
    // 设置cURL选项
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
    
    // 执行请求
    curl_exec($ch);
    
    // 检查错误
    $error = curl_error($ch);
    if ($error) {
        sendSSEEvent('error', ['error' => 'API Error: ' . $error]);
    }
    
    // 关闭cURL会话
    curl_close($ch);
} catch (Exception $e) {
    sendSSEEvent('error', ['error' => 'Exception: ' . $e->getMessage()]);
}

// 保存完整的AI响应到数据库
if (!empty($fullResponse)) {
    // 解析完整响应
    $completeResponse = '';
    $lines = explode("\n", $fullResponse);
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $jsonData = substr($line, 6);
            if ($jsonData !== '[DONE]') {
                try {
                    $data = json_decode($jsonData, true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $completeResponse .= $data['choices'][0]['delta']['content'];
                    }
                } catch (Exception $e) {
                    // 忽略解析错误
                }
            }
        }
    }
    
    // 保存AI响应
    if (!empty($completeResponse)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, content, created_at, is_read) 
                VALUES (?, ?, ?, NOW(), 0)
            ");
            $stmt->execute([$aiUserId, $currentUserId, $completeResponse]);
        } catch (Exception $e) {
            sendSSEEvent('error', ['error' => 'Database Error: ' . $e->getMessage()]);
        }
    }
}

// 发送结束事件
sendSSEEvent('close', ['message' => 'Stream closed']);
?> 