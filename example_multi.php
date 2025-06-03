<?php
/**
 * Multi-Agent Chat System - Explicit Instructions Design
 * 
 * All prompts and instructions are explicitly defined here for maximum maintainability
 */

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/src/Model/SystemAPI.php';
require_once __DIR__ . '/src/Model/Agent.php';
require_once __DIR__ . '/src/Model/Tool.php';
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Tools/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

if (empty($config['api_key'])) {
    echo "❌ Error: OpenAI API key not found. Please check your .env file\n";
    die();
}

// Initialize SystemAPI
$system = new SystemAPI($config);

/*==================================
            Tools Setup
===================================*/
$weatherTool = new WeatherTool();
$translateTool = new TranslateTool();
$calculusTool = new CalculusTool();

/*==================================
            Agents Setup
===================================*/
$spanishAgent = new Agent(
    "Spanish Agent",
    "You are a specialized translation agent. You translate text between English and Spanish, correct grammar, and explain idioms with cultural context.",
    $translateTool
);

$weatherAgent = new Agent(
    "Weather Agent",
    "You are a weather specialist agent. You provide current weather information, forecasts, and weather-related advice for any location worldwide.",
    $weatherTool
);

$mathAgent = new Agent(
    "Math Agent",
    "You are a mathematics specialist agent. You perform arithmetic calculations, solve equations, and explain mathematical concepts clearly.",
    $calculusTool
);

$managerAgent = new Agent(
    "Manager Agent",
    "You are the central coordinator that delegates tasks to specialized agents. CRITICAL INSTRUCTIONS FOR MULTI-STEP REQUESTS: 1. For requests with 'AND' (like 'weather AND translate'), you MUST make sequential tool calls. 2. FIRST: Make the initial tool call (e.g., weather_agent with location). 3. WAIT: The system will give you the result. 4. THEN: Use that result in your next tool call (e.g., spanish_agent with the weather text). 5. NEVER try to do everything in one tool call. EXAMPLE FLOWS: User: 'Weather in Madrid and translate to Spanish' - First call: weather_agent({\"location\": \"Madrid\"}) - Wait for result: 'Sunny 25°C' - Second call: spanish_agent({\"text\": \"Sunny 25°C\", \"direction\": \"to_spanish\"}). User: 'Calculate 15 + 27 and tell me weather in Tokyo' - First call: math_agent({\"expression\": \"15 + 27\"}) - Wait for result: '42' - Second call: weather_agent({\"location\": \"Tokyo\"}). REMEMBER: When you receive tool results, check if more steps are needed to complete the user's request!"
);

// Add agents to the system
$system->addAgents([$managerAgent, $spanishAgent, $weatherAgent, $mathAgent]);

/*==================================
        System-Wide Instructions
===================================*/
$system->addInstruction("You are a helpful, polite, and efficient multi-agent assistant system. You coordinate specialized agents to provide accurate and comprehensive responses. Always prioritize accuracy and helpfulness in your responses. If you're unsure about something, clearly communicate that uncertainty.");

/*==================================
        Quality & Safety Controls
===================================*/
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long. Please keep it under 1000 characters."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse', 'hack'], "Inappropriate content detected."));

/*==================================
           Session Context
===================================*/
$system->addContext('username', 'Ryan');
$system->addContext('current_date', date('Y-m-d'));
$system->addContext('session_id', uniqid());

/*==================================
           CLI Interface
===================================*/
echo "🤖 Enhanced Multi-Agent Chat System\n";
echo "===================================\n";
echo "Using Explicit Instructions for Maximum Maintainability\n\n";

echo "Debug commands:\n";
echo "- 'debug' - Toggle simple debug mode (first payload only)\n";
echo "- 'full-debug' - Toggle full ReAct loop debugging\n";
echo "- 'show-instructions' - Display all system instructions and agent roles\n";
echo "- 'exit' - Quit\n\n";

$debugMode = false;
$fullDebugMode = false;

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN) ?: '');
    
    if (strtolower($input) === 'exit') {
        echo "Goodbye, Ryan! 👋\n";
        break;
    }
    
    if (strtolower($input) === 'debug') {
        $debugMode = !$debugMode;
        $fullDebugMode = false;
        echo "Simple debug mode " . ($debugMode ? "enabled" : "disabled") . "\n\n";
        continue;
    }
    
    if (strtolower($input) === 'full-debug') {
        $fullDebugMode = !$fullDebugMode;
        $debugMode = false;
        echo "Full ReAct debug mode " . ($fullDebugMode ? "enabled" : "disabled") . "\n\n";
        continue;
    }
    
    if (strtolower($input) === 'show-instructions') {
        echo "\n📋 SYSTEM INSTRUCTIONS:\n";
        echo "========================\n";
        foreach ($system->getInstructions() as $i => $instruction) {
            echo ($i + 1) . ". " . $instruction . "\n";
        }
        
        echo "\n📋 AGENT ROLES:\n";
        echo "================\n";
        foreach ($system->getAgents() as $agent) {
            if (method_exists($agent, 'getName') && method_exists($agent, 'getRole')) {
                echo "• " . $agent->getName() . ": " . $agent->getRole() . "\n";
            }
        }
        echo "\n";
        continue;
    }
    
    if ($input === '') {
        echo "Please enter a question or command.\n";
        echo "Available commands: 'debug', 'full-debug', 'show-instructions', 'exit'\n\n";
        continue;
    }
    
    echo "\n🤖 Processing your request...\n";
    
    try {
        if ($fullDebugMode) {
            // Full ReAct loop debugging
            echo "\n📋 FULL DEBUG: Complete ReAct Conversation Loop:\n";
            echo "==============================================\n";
            $fullPayload = $system->fullPayload($input);
            echo json_encode($fullPayload, JSON_PRETTY_PRINT) . "\n";
            echo "==============================================\n\n";
            
            echo "📤 Final Result:\n";
            $response = $system->run($input);
            echo $response . "\n\n";
        } else if ($debugMode) {
            // Simple debug mode
            echo "\n📋 DEBUG: First OpenAI API Call:\n";
            echo "=================================\n";
            $payload = $system->payload($input);
            echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
            echo "=================================\n\n";
            
            echo "📤 Final Result:\n";
            $response = $system->run($input);
            echo $response . "\n\n";
        } else {
            // Production mode - clean output only
            $response = $system->run($input);
            echo "\n💬 " . $response . "\n\n";
        }
    } catch (Throwable $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        
        if ($debugMode || $fullDebugMode) {
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
        }
    }
}
