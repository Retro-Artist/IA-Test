<?php
/**
 * Multi-Agent System Example with Handoffs
 * 
 * This example demonstrates the OpenAI-style handoff pattern
 * where a triage agent can transfer control to specialized agents.
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/Agent.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Model/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

// Check if API key is configured
if (empty($config['api_key'])) {
    echo "❌ Error: OpenAI API key not found. Please check your .env file\n";
    die();
}

// Initialize specialized agents with tools
$spanishAgent = new Agent(
    "Spanish Agent", 
    "You only speak Spanish. Always respond in Spanish. You are an expert in Spanish language and culture.",
    $config
);

$weatherAgent = new Agent(
    "Weather Agent", 
    "You provide weather information and forecasts. You are an expert meteorologist. Use the weather tool when asked about weather.",
    $config
);
$weatherAgent->addTool(new WeatherTool());

$mathAgent = new Agent(
    "Math Agent", 
    "You are a mathematics expert. Help with calculations and math problems. Use the calculator tool for mathematical operations.",
    $config
);
$mathAgent->addTool(new CalculatorTool());

// Create triage agent with handoffs
$triageAgent = new Agent(
    "Triage Agent",
    "Help the user with their questions. " .
    "If they ask about weather, handoff to the Weather Agent. " .
    "If they ask math questions, handoff to the Math Agent. " .
    "If they write in Spanish or ask for Spanish translation, handoff to the Spanish Agent.",
    $config,
    [$spanishAgent, $weatherAgent, $mathAgent] // handoffs
);

// Add system-wide guardrails
$triageAgent->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$triageAgent->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// CLI interaction loop
echo "OpenAI-Style Multi-Agent System\n";
echo "==============================\n";
echo "Available specialist agents via handoffs:\n";
echo "- Spanish Agent: Handles Spanish language tasks\n";
echo "- Weather Agent: Provides weather information\n";
echo "- Math Agent: Solves mathematical problems\n\n";
echo "The Triage Agent will handoff to specialists as needed.\n";
echo "Type 'exit' to quit\n\n";

echo "Try these examples:\n";
echo "- 'Hola, ¿cómo estás?' (Spanish handoff)\n";
echo "- 'What's the weather in Paris?' (Weather handoff)\n";
echo "- 'Calculate 25 times 47' (Math handoff)\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    
    if ($input === 'exit') {
        break;
    }
    
    if (empty($input)) {
        echo "Please enter a question or command.\n\n";
        continue;
    }
    
    try {
        echo "\n🤖 Processing your request...\n";
        $response = $triageAgent->execute($input);
        echo "\n" . $response . "\n\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "¡Adiós! Goodbye! 👋\n";