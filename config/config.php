<?php
/**
 * Configuration file for OpenAI API and Database access
 * 
 * Environment variables are loaded from .env file
 */

// Load environment variables
require_once __DIR__ . '/load_env.php';
$envLoaded = loadEnv(__DIR__ . '/../.env');  // Load from root directory

if (!$envLoaded) {
    echo "WARNING: .env file could not be loaded!\n";
}

// Get OpenAI API values from environment variables with fallbacks
$apiKey = getenv('OPENAI_API_KEY');
$apiKey = $apiKey !== false ? $apiKey : '';

$modelName = getenv('OPENAI_MODEL');
$modelName = $modelName !== false ? $modelName : 'gpt-4o-mini';

$maxTokens = getenv('OPENAI_MAX_TOKENS');
$maxTokens = $maxTokens !== false ? (int)$maxTokens : 1024;

$temperature = getenv('OPENAI_TEMPERATURE');
$temperature = $temperature !== false ? (float)$temperature : 0.7;

// Get Database values from environment variables with fallbacks
$dbHost = getenv('DB_HOST');
$dbHost = $dbHost !== false ? $dbHost : 'localhost';

$dbPort = getenv('DB_PORT');
$dbPort = $dbPort !== false ? (int)$dbPort : 3306;

$dbDatabase = getenv('DB_DATABASE');
$dbDatabase = $dbDatabase !== false ? $dbDatabase : 'ai_php';

$dbUsername = getenv('DB_USERNAME');
$dbUsername = $dbUsername !== false ? $dbUsername : 'root';

$dbPassword = getenv('DB_PASSWORD');
$dbPassword = $dbPassword !== false ? $dbPassword : '';

return [
    // OpenAI Configuration
    'api_key' => $apiKey,
    'model' => $modelName,
    'max_tokens' => $maxTokens,
    'temperature' => $temperature,
    'use_moderation' => true,
    
    // Database Configuration
    'database' => [
        'host' => $dbHost,
        'port' => $dbPort,
        'database' => $dbDatabase,
        'username' => $dbUsername,
        'password' => $dbPassword,
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ]
];