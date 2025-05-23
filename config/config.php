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

// Function to fetch notes from database
function fetchNotes() {
    $db = getDatabaseConnection();
    if (!$db) return [];
    
    try {
        $stmt = $db->query("SELECT * FROM notes ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch notes: " . $e->getMessage());
        return [];
    }
}

// Function to get or create active conversation thread
function getActiveConversation($usuarioId) {
    $db = getDatabaseConnection();
    if (!$db) return null;
    
    try {
        // Busca conversa ativa (últimos 30 minutos)
        $stmt = $db->prepare("
            SELECT id, thread 
            FROM conversas 
            WHERE usuario_id = ? 
            AND timestamp_inicio > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND timestamp_fim IS NULL
            ORDER BY timestamp_inicio DESC 
            LIMIT 1
        ");
        $stmt->execute([$usuarioId]);
        $conversa = $stmt->fetch();
        
        if ($conversa) {
            return [
                'id' => $conversa['id'],
                'thread' => json_decode($conversa['thread'], true) ?: []
            ];
        }
        
        // Cria nova conversa se não encontrou ativa
        $stmt = $db->prepare("INSERT INTO conversas (usuario_id, thread) VALUES (?, ?)");
        $stmt->execute([$usuarioId, json_encode([])]);
        
        return [
            'id' => $db->lastInsertId(),
            'thread' => []
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to get conversation: " . $e->getMessage());
        return null;
    }
}

// Function to update conversation thread
function updateConversationThread($conversaId, $thread) {
    $db = getDatabaseConnection();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("UPDATE conversas SET thread = ? WHERE id = ?");
        return $stmt->execute([json_encode($thread), $conversaId]);
    } catch (PDOException $e) {
        error_log("Failed to update thread: " . $e->getMessage());
        return false;
    }
}

// Function to close conversation (end session)
function closeConversation($conversaId) {
    $db = getDatabaseConnection();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("UPDATE conversas SET timestamp_fim = NOW() WHERE id = ?");
        return $stmt->execute([$conversaId]);
    } catch (PDOException $e) {
        error_log("Failed to close conversation: " . $e->getMessage());
        return false;
    }
}

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
        'connection' => getDatabaseConnection(),
        'notes' => fetchNotes()
    ]
];