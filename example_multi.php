<?php
/**
 * Example of simple agent initialization and usage
 * Multi-agent system with clean routing and handoffs
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/ModelContextProtocol.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Tools/SampleTools.php';
require_once __DIR__ . '/src/Model/Agent.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

// Initialize system (ModelContextProtocol serves as System)
$system = new ModelContextProtocol($config);

// Add system-wide instructions, context, and guardrails
$system->addInstruction("You are a helpful chat with multiple agent capability. Always be helpful and professional.");
$system->addInstruction("Respond concisely and clearly.");
$system->addContext('current_date', date('Y-m-d'));
$system->addContext('user', 'Jhon Doe');
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// Initialize agents
$spanishAgent = new Agent("Spanish Agent", "You only speak Spanish. Always respond in Spanish.");
$weatherAgent = new Agent("Weather Agent", "You provide weather information and forecasts.");
$mathAgent = new Agent("Math Agent", "You are a mathematics expert. Help with calculations and math problems.");

// Initialize tools to appropriate agents
$weatherTool = new WeatherTool();
$calculatorTool = new CalculatorTool();

$weatherAgent->addTool($weatherTool);
$mathAgent->addTool($calculatorTool);

// Define a triage agent that can route requests to the appropriate agent
$triageAgent = new Agent("Triage Agent", "Analyze the user's request and determine the best agent to handle it.", [ $spanishAgent, $weatherAgent, $mathAgent]);

// CLI interaction loop for testing multi-agent system
echo "Multi-Agent System Example\n";
echo "Available agents: Spanish Agent, Weather Agent, Math Agent\n";
echo "Type 'exit' to quit\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    
    if ($input === 'exit') {
        break;
    }
    
    try {
        // Use triage agent to route and handle the request
        $response = $triageAgent->execute($input);
        echo "\n" . $response . "\n\n";
        
    } catch (Exception $e) {
        echo "\nError: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";