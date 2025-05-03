<?php
/**
 * DeepSeek API Integration for AI Chat
 * This file handles communication with the DeepSeek API
 */

// Function to send a message to DeepSeek API and get a response
function getDeepSeekResponse($message, $apiKey, $chatHistory = []) {
    // API endpoint for DeepSeek
    $url = 'https://api.deepseek.com/chat/completions';
    
    // Prepare messages array with system message
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant for a campus forum. Provide concise, accurate, and helpful responses to student questions.'
        ]
    ];
    
    // Add chat history to messages
    foreach ($chatHistory as $chat) {
        $role = $chat['is_sent'] ? 'user' : 'assistant';
        $messages[] = [
            'role' => $role,
            'content' => $chat['content']
        ];
    }
    
    // Add the current user message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Prepare the request data
    $data = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'stream' => false
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }
    
    curl_close($ch);
    
    // Process the response
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMessage = 'Unknown error';
        if (isset($responseData['error'])) {
            if (is_array($responseData['error'])) {
                $errorMessage = isset($responseData['error']['message']) ? $responseData['error']['message'] : json_encode($responseData['error']);
            } else {
                $errorMessage = $responseData['error'];
            }
        }
        return [
            'success' => false,
            'error' => 'API Error (Code: ' . $httpCode . '): ' . $errorMessage
        ];
    }
    
    // Extract the AI's response
    if (isset($responseData['choices']) && isset($responseData['choices'][0]['message']['content'])) {
        $aiResponse = $responseData['choices'][0]['message']['content'];
    } else {
        // Log the full response for debugging
        error_log('Unexpected DeepSeek API response format: ' . json_encode($responseData));
        $aiResponse = 'Sorry, I could not generate a response. The API returned an unexpected format.';
    }
    
    return [
        'success' => true,
        'response' => $aiResponse
    ];
}

// Function to save AI chat message to database
function saveAiChatMessage($userId, $userMessage, $aiResponse) {
    global $pdo;
    
    // Get the AI assistant user ID (you might need to create a special user for the AI)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'AI Assistant' LIMIT 1");
    $stmt->execute();
    $aiUser = $stmt->fetch();
    
    if (!$aiUser) {
        // Create AI user if it doesn't exist
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, created_at, updated_at) 
            VALUES ('AI Assistant', 'ai@example.com', ?, 'admin', NOW(), NOW())
        ");
        $stmt->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $aiUserId = $pdo->lastInsertId();
    } else {
        $aiUserId = $aiUser['id'];
    }
    
    // Save user message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 1)
    ");
    $stmt->execute([$userId, $aiUserId, $userMessage]);
    
    // Save AI response
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->execute([$aiUserId, $userId, $aiResponse]);
    
    return true;
}

// Function to send a message to DeepSeek API and get a streaming response
function getDeepSeekStreamingResponse($message, $apiKey, $chatHistory = []) {
    // API endpoint for DeepSeek
    $url = 'https://api.deepseek.com/chat/completions';
    
    // Prepare messages array with system message
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant for a campus forum. Provide concise, accurate, and helpful responses to student questions.'
        ]
    ];
    
    // Add chat history to messages
    foreach ($chatHistory as $chat) {
        $role = $chat['is_sent'] ? 'user' : 'assistant';
        $messages[] = [
            'role' => $role,
            'content' => $chat['content']
        ];
    }
    
    // Add the current user message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Prepare the request data
    $data = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'stream' => true
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // This is important for streaming
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
        echo $data;
        ob_flush();
        flush();
        return strlen($data);
    });
    
    // Execute the request
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo json_encode([
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ]);
        return;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'error' => 'API Error (Code: ' . $httpCode . ')'
        ]);
        return;
    }
}
?> 