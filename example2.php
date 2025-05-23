<?php
/**
 * Procedural example of the Model Context Protocol
 * 
 * This version has minimal dependencies and simpler error handling.
 */

declare(strict_types=1);

// Load configuration
$config = require_once 'src/config.php';

// Simple function to call the OpenAI API
function callOpenAI(string $apiKey, string $userInput): string {
    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant that responds concisely.'],
            ['role' => 'user', 'content' => $userInput]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    // Prepare API call
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // Execute API call
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle curl errors
    if ($response === false) {
        return "cURL error: " . $curlError;
    }
    
    // Handle HTTP errors
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = isset($errorData['error']['message']) 
            ? $errorData['error']['message'] 
            : "HTTP error: $httpCode";
        return "API error: " . $errorMessage;
    }
    
    // Parse the response
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "JSON parse error: " . json_last_error_msg();
    }
    
    // Extract and return the content
    return $decoded['choices'][0]['message']['content'] ?? "No content in response";
}

// CLI interaction loop
echo "Simple Model Context Protocol Example\n";
echo "Type 'exit' to quit\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    
    if ($input === 'exit') {
        break;
    }
    
    try {
        $response = callOpenAI($config['api_key'], $input);
        echo "\n$response\n\n";
    } catch (Exception $e) {
        echo "\nError: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";