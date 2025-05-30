<?php
/**
 * Single-Agent System Example
 * 
 * Direct API calls through unified System class
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/SystemAPI.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Tools/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

if (empty($config['api_key'])) {
    echo "❌ Error: OpenAI API key not found. Please check your .env file\n";
    die();
}

// Create unified SystemAPI instance (single-agent mode)
$system = new SystemAPI($config);

// Add instructions
$system->addInstruction("You are a helpful assistant that provides information and performs tasks.");
$system->addInstruction("Always be polite and concise in your responses.");
$system->addInstruction("Use the tools available to you when appropriate to answer questions.");

// Add tools
$system->addTool(new WeatherTool());
$system->addTool(new SearchTool());
$system->addTool(new CalculusTool());

// Add guardrails
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// Add context
$system->addContext('username', 'Ryan');
$system->addContext('current_date', date('Y-m-d'));
$system->addContext('session_id', uniqid());

// CLI Interface
echo "Welcome to the Single-Agent System!\n";
echo "==================================\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN) ?: '');
    
    if (strtolower($input) === 'exit') {
        echo "Good Bye, Ryan!\n";
        break;
    }
    
    if ($input === '') {
        echo "Please enter a question or command.\n\n";
        continue;
    }
    
    echo "\n🤖 Processing your request...\n";
    try {
        $response = $system->run($input);
        echo "\n{$response}\n\n";
    } catch (Throwable $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
}