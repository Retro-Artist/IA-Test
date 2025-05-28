<?php
/**
 * Example usage of the Model Context Protocol with Advanced Streaming
 */

declare(strict_types=1);

// Include necessary files with correct paths
require_once __DIR__ . '/src/Model/ModelContextProtocol.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Model/SampleTools.php';

// Load configuration with correct path
$config = require_once __DIR__ . '/config/config.php';

// Check if API key is configured
if (empty($config['api_key'])) {
    echo "Error: OpenAI API key not found. Please check your .env file\n";
    echo "Make sure you have:\n";
    echo "1. Copied .env.example to .env\n";
    echo "2. Added your OpenAI API key to the .env file\n";
    echo "3. The .env file is in the root directory\n\n";
    die();
}

// Create a new ModelContextProtocol instance
$mcp = new ModelContextProtocol($config);

// Add instructions to define the agent's behavior
$mcp->addInstruction("You are a helpful assistant that provides information and performs tasks.");
$mcp->addInstruction("Always be polite and concise in your responses.");
$mcp->addInstruction("If you're unsure about something, acknowledge your uncertainty.");
$mcp->addInstruction("Use the tools available to you when appropriate to answer questions.");

// Add tools to extend the agent's capabilities
$mcp->addTool(new WeatherTool());
$mcp->addTool(new CalculatorTool());
$mcp->addTool(new SearchTool());

// Add guardrails to protect the agent's operation
$mcp->addGuardrail(new InputLengthGuardrail(60, "Input is too long. Please keep it under 60 characters."));
$mcp->addGuardrail(new KeywordGuardrail(
    ['hack', 'exploit', 'bypass', 'jailbreak', 'prompt injection', 'profanity', 'violence'], 
    "I cannot process requests related to system exploitation or unauthorized access."
));

// Add context to the thread
$mcp->addContext('username', 'User');
$mcp->addContext('current_date', date('Y-m-d'));
$mcp->addContext('session_id', uniqid());

// CLI interaction loop
echo "Model Context Protocol Example with Advanced Streaming\n";
echo "Type 'exit' to quit\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    
    if ($input === 'exit') {
        break;
    }
    
    try {
        // Use the run method for single interactions
        $response = $mcp->run($input);
        echo "\n" . $response . "\n\n";
        
    } catch (Exception $e) {
        echo "\nError: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";