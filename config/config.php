<?php
/**
 * Configuration file for OpenAI API access
 * 
 * Environment variables are loaded from .env file
 */

// Load environment variables
require_once __DIR__ . '/load_env.php';
$envLoaded = loadEnv(__DIR__ . '/../.env');  // Carrega do diretório raiz

if (!$envLoaded) {
    echo "AVISO: Arquivo .env não pôde ser carregado!\n";
}

// Obter valores das variáveis de ambiente com fallbacks
$apiKey = getenv('OPENAI_API_KEY');
$apiKey = $apiKey !== false ? $apiKey : '';  // Converte false para string vazia

$modelName = getenv('OPENAI_MODEL');
$modelName = $modelName !== false ? $modelName : 'gpt-4o-mini';

$maxTokens = getenv('OPENAI_MAX_TOKENS');
$maxTokens = $maxTokens !== false ? (int)$maxTokens : 1024;

$temperature = getenv('OPENAI_TEMPERATURE');
$temperature = $temperature !== false ? (float)$temperature : 0.7;

return [
    'api_key' => $apiKey,
    'model' => $modelName,
    'max_tokens' => $maxTokens,
    'temperature' => $temperature,
    'use_moderation' => true,
];