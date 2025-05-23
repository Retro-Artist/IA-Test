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

// Database connection function
function getDatabaseConnection() {
    global $dbHost, $dbPort, $dbDatabase, $dbUsername, $dbPassword;
    
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

return [
    // OpenAI Configuration (following official documentation)
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
        'charset' => 'utf8mb4'
    ]
];