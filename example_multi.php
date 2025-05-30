<?php
/**
 * Multi-Agent System Example - Simplified Approach
 * 
 * This example demonstrates that we don't need System.php at all.
 * ModelContextProtocol + Agent with handoffs is all we need.
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/Agent.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Model/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

if (empty($config['api_key'])) {
    fwrite(STDERR, "❌ Error: OpenAI API key not found. Please check your .env file\n");
    exit(1);
}

// Set default config for all agents
Agent::setDefaultConfig($config);

// Instantiate tools for agents
$translateTool = new TranslateTool();
$weatherTool   = new WeatherTool();
$divideTool    = new DivideTool();
$multiplyTool  = new MultiplyTool();
$subtractTool  = new SubtractTool();

// Instantiate specialized agents with their tools
$spanishAgent = new Agent(
    "Spanish Agent",
    "You translate text between English and Spanish, correct Spanish grammar, and explain idioms.",
    $translateTool
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

// Create manager agent with handoffs (this IS our system!)
$manager = new Agent(
    "Manager Agent",
    "You are a helpful assistant. Be polite and concise. Help the user with their questions and delegate to appropriate specialist agents when needed."
);

// Add handoffs to the manager
$manager->addHandoffs([$spanishAgent, $weatherAgent, $mathAgent]);

// Add system-wide guardrails and instructions to the manager
$manager->addInstruction("Always determine which specialist agent can best help the user and delegate appropriately.");
$manager->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$manager->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// Add context to the manager (this will work properly!)
$manager->addContext('username', 'Ryan');
$manager->addContext('current_date', date('Y-m-d'));
$manager->addContext('session_id', uniqid());

// CLI Interface loop
echo "Welcome to the Simplified Multi-Agent System!\n";
echo "============================================\n";
echo "Available specialist agents:\n";
echo "- Spanish Agent: Translates and works with Spanish text\n";
echo "- Weather Agent: Provides weather information\n";
echo "- Math Agent: Performs arithmetic calculations\n\n";
echo "Type 'exit' to quit.\n\n";
echo "Examples:\n";
echo "- \"What's my name?\" (should know you're Ryan)\n";
echo "- \"Hola, ¿cómo estás?\"\n";
echo "- \"What's the weather in Madrid?\"\n";
echo "- \"Calculate 25 / 5\"\n\n";

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
        $response = $manager->execute($input);
        echo "\n{$response}\n\n";
    } catch (Throwable $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
}