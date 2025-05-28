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

if (empty($config['api_key'])) {
    fwrite(STDERR, "❌ Error: OpenAI API key not found. Please check your .env file\n");
    exit(1);
}

// Initialize the system with configuration
$system = new System($config);

// Instantiate tools for agents
$translateTool = new TranslateTool();
$weatherTool   = new WeatherTool();
$divideTool    = new DivideTool();
$multiplyTool  = new MultiplyTool();
$subtractTool  = new SubtractTool();

// Instantiate specialized agents with their tools
$spanishAgent = new Agent(
    "Spanish Agent", // name
    "You translate text between English and Spanish, correct Spanish grammar, and explain idioms.", // role
    $translateTool // tools
);

$weatherAgent = new Agent(
    "Weather Agent",
    "You provide current weather information and forecasts via a weather API.",
    $weatherTool
);

$mathAgent = new Agent(
    "Math Agent",
    "You perform simple arithmetic: add, subtract, multiply, and divide.",
    $divideTool,
    $multiplyTool,
    $subtractTool
);

// Add agents to the system (manager pattern)
$system->addAgents([$spanishAgent, $weatherAgent, $mathAgent]);

// System-wide guardrails and instructions
$system->addInstruction("You are a helpful assistant. Be polite and concise. Help the user with their questions and designate the appropriate agent for specialized tasks.");
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// CLI Interface loop for testing the multi-agent system
echo "Welcome to the OpenAI-Style Multi-Agent System!\n";
echo "===============================================\n";
echo "Available specialist agents via manager coordination:\n";
echo "- Spanish Agent: Translates and works with Spanish text\n";
echo "- Weather Agent: Provides weather information\n";
echo "- Math Agent: Performs arithmetic calculations\n\n";
echo "Type 'exit' to quit.\n\n";
echo "Examples:\n";
echo "- \"Hola, ¿cómo estás?\"\n";
echo "- \"What's the weather in Madrid?\"\n";
echo "- \"Calculate 25 / 5\"\n\n";

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