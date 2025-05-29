<?php
/**
 * Multi-Agent System Example with Manager Pattern
 * 
 * This example demonstrates the OpenAI-style manager pattern
 * where a central manager coordinates specialized agents via tool calls.
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/Agent.php';
require_once __DIR__ . '/src/Model/System.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Model/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

$system = new System($config);

$translateTool = new TranslateTool();
$weatherTool   = new WeatherTool();
$multiplyTool  = new MultiplyTool();

$spanishAgent = new Agent(
    "Spanish Agent", 
    "You translate text between English and Spanish, correct Spanish grammar, and explain idioms."
);

$weatherAgent = new Agent(
    "Weather Agent",
    "You provide current weather information and forecasts via a weather API.",
    $weatherTool
);

$mathAgent = new Agent(
    "Math Agent",
    "You perform simple arithmetic: add, subtract, multiply, and divide.",
    $multiplyTool,
);

// Add agents to the system (manager pattern)
$system->addAgents([$spanishAgent, $weatherAgent, $mathAgent]);
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));


// CLI Interface loop for testing the multi-agent system
echo "Welcome to the OpenAI-Style Multi-Agent System!\n";
echo "===============================================\n";
echo "Type 'exit' to quit.\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN) ?: '');
    
    if (strtolower($input) === 'exit') {
        echo "Good Bye\n";
        break;
    }
    
    if ($input === '') {
        echo "Please enter a question or command.\n\n";
        continue;
    }
    
    echo "\n🤖 Processing your request...\n";
    try {
        $response = $system->execute($input);
        echo "\n{$response}\n\n";
    } catch (Throwable $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
}