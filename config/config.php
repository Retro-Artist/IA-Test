<?php
/**
 * Configuration file for OpenAI API and Database access
 * 
 * Environment variables are loaded from .env file
 */

// Load environment variables
require_once __DIR__ . '/load_env.php';
$envLoaded = loadEnv(__DIR__ . '/../.env');

if (!$envLoaded) {
    error_log("WARNING: .env file could not be loaded!");
}

// Get OpenAI API values from environment variables with fallbacks
$apiKey = getenv('OPENAI_API_KEY') ?: '';
$modelName = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
$maxTokens = (int)(getenv('OPENAI_MAX_TOKENS') ?: 1024);
$temperature = (float)(getenv('OPENAI_TEMPERATURE') ?: 0.7);

// Get Database values from environment variables with fallbacks
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);
$dbDatabase = getenv('DB_DATABASE') ?: 'ai_php';
$dbUsername = getenv('DB_USERNAME') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';

return [
    // OpenAI Configuration
    'api_key' => $apiKey,
    'model' => $modelName,
    'max_tokens' => $maxTokens,
    'temperature' => $temperature,
    
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