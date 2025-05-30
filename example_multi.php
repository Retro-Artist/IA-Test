<?php
/**
 * Multi-Agent System Example - Perfect Progression
 * 
 * Same unified System class, just add agents to enable multi-agent mode!
 */

declare(strict_types=1);

// Include necessary files (same as single-agent!)
require_once __DIR__ . '/src/Model/SystemAPI.php';
require_once __DIR__ . '/src/Model/Agent.php';// ← ONLY ADDITION
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Tools/SampleTools.php';

// Load configuration (same as single-agent!)
$config = require_once __DIR__ . '/config/config.php';

if (empty($config['api_key'])) {
    echo "❌ Error: OpenAI API key not found. Please check your .env file\n";
    die();
}

// Create unified SystemAPI instance (same as single-agent!)
$system = new SystemAPI($config);

/*==================================
    PROGRESSION: Add Specialized Agents
===================================*/
$translateTool = new TranslateTool();
$calculusTool = new CalculusTool();
$weatherTool = new WeatherTool();


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
    $calculusTool
);


// ✨ THE MAGIC LINE: Switch to multi-agent mode!
$system->addAgents([$spanishAgent, $weatherAgent, $mathAgent]);

// Add instructions (same as single-agent!)
$system->addInstruction("You are a helpful assistant that provides information and performs tasks.");
$system->addInstruction("Always be polite and concise in your responses.");
$system->addInstruction("Use the tools available to you when appropriate to answer questions.");

// Add tools (same as single-agent!)
$system->addTool(new WeatherTool());
$system->addTool(new CalculusTool());
$system->addTool(new SearchTool());

// Add guardrails (same as single-agent!)
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// Add context (same as single-agent!)
$system->addContext('username', 'Ryan');
$system->addContext('current_date', date('Y-m-d'));
$system->addContext('session_id', uniqid());

/*==================================
           CLI Interface
===================================*/

echo "Welcome to the Multi-Agent System!\n";
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
        // Same interface as single-agent!
        $response = $system->run($input);
        echo "\n{$response}\n\n";
    } catch (Throwable $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
}