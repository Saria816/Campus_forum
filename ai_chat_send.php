<?php
// 包含函数文件
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You must be logged in to use AI chat.']);
    exit;
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// 获取用户消息
$userMessage = trim($_POST['message'] ?? '');

if (empty($userMessage)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

// 过滤敏感词
$filteredMessage = filterSensitiveWords($userMessage);

// 保存用户消息到数据库
$stmt = $pdo->prepare("
    INSERT INTO ai_chat_messages (user_id, content, is_user, created_at)
    VALUES (?, ?, 1, NOW())
");
$stmt->execute([getCurrentUserId(), $filteredMessage]);

// 获取DeepSeek API密钥
$apiKey = ''; // 在实际使用中，应该从配置文件或环境变量中获取

// 调用DeepSeek API
$response = callDeepSeekAPI($filteredMessage, $apiKey);

// 保存AI回复到数据库
$stmt = $pdo->prepare("
    INSERT INTO ai_chat_messages (user_id, content, is_user, created_at)
    VALUES (?, ?, 0, NOW())
");
$stmt->execute([getCurrentUserId(), $response]);

// 返回结果
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => $response
]);
exit;

/**
 * 调用DeepSeek API
 * 
 * @param string $message 用户消息
 * @param string $apiKey API密钥
 * @return string AI回复
 */
function callDeepSeekAPI($message, $apiKey) {
    // 如果没有API密钥，返回模拟回复
    if (empty($apiKey)) {
        return getSimulatedResponse($message);
    }
    
    // DeepSeek API URL
    $url = 'https://api.deepseek.com/v1/chat/completions';
    
    // 准备请求数据
    $data = [
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant for university students.'],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
    
    // 初始化cURL
    $ch = curl_init($url);
    
    // 设置cURL选项
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // 执行请求
    $response = curl_exec($ch);
    
    // 检查是否有错误
    if (curl_errno($ch)) {
        curl_close($ch);
        return 'Sorry, I encountered an error while processing your request. Please try again later.';
    }
    
    // 关闭cURL
    curl_close($ch);
    
    // 解析响应
    $responseData = json_decode($response, true);
    
    // 检查响应是否有效
    if (isset($responseData['choices'][0]['message']['content'])) {
        return $responseData['choices'][0]['message']['content'];
    } else {
        return 'Sorry, I encountered an error while processing your request. Please try again later.';
    }
}

/**
 * 获取模拟回复（当没有API密钥时使用）
 * 
 * @param string $message 用户消息
 * @return string 模拟回复
 */
function getSimulatedResponse($message) {
    $responses = [
        'hello' => 'Hello! How can I help you today?',
        'hi' => 'Hi there! What can I do for you?',
        'how are you' => 'I\'m just a computer program, but I\'m functioning well! How can I assist you?',
        'thank' => 'You\'re welcome! Is there anything else I can help you with?',
        'bye' => 'Goodbye! Feel free to come back if you have more questions.',
        'help' => 'I\'m here to help! You can ask me questions about academic topics, get explanations, or seek advice.',
        'what can you do' => 'I can answer questions, provide explanations, help with research, offer suggestions, and much more. What do you need help with?'
    ];
    
    // 检查是否有匹配的关键词
    foreach ($responses as $keyword => $response) {
        if (stripos($message, $keyword) !== false) {
            return $response;
        }
    }
    
    // 默认回复
    $defaultResponses = [
        'That\'s an interesting question. Let me think about it...',
        'I understand you\'re asking about "' . substr($message, 0, 30) . '...". Could you provide more details?',
        'Thank you for your question. In a real implementation, I would connect to DeepSeek API to provide a comprehensive answer.',
        'I\'m designed to help with questions like this. With a proper API connection, I could give you a detailed response.',
        'This is a simulated response. In production, the DeepSeek API would generate a helpful answer to your query.'
    ];
    
    return $defaultResponses[array_rand($defaultResponses)];
} 