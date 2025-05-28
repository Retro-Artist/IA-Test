
We have to build an easy to understand and flexible multi-agent automation framework following the “manager pattern.” While multi-agent systems can be designed in numerous ways for specific workflows and requirements, our experience with customers highlights that a decentralized approach like Crew AI (agent-to-agent) can be applied. However, we will use the Manager approach for better and clearer visualization:

**Manager (agents as tools):**
A central “manager” agent coordinates multiple specialized agents via tool calls, each handling a specific task or domain. The manager pattern empowers a central LLM—the “manager”—to orchestrate a network of specialized agents seamlessly through tool calls. Instead of losing context or control, the manager intelligently delegates tasks to the right agent at the right time, effortlessly synthesizing the results into a cohesive interaction. This ensures a smooth, unified user experience, with specialized capabilities always available on demand.

This pattern is ideal for workflows where you only want one agent to control workflow execution and have access to the user.
Now, what if our agents did not have to have config set every time as a parameter but have this value initialized by default so that we don’t have to add `$config` in their parameters.
and our agents could have tools as an additional parameter, and we could add one or more tools for them to work with. (Would it work?)

Example of usage:

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



if (empty($config['api_key'])) {
    fwrite(STDERR, "❌ Error: OpenAI API key not found. Please check your .env file\n");
    exit(1);
}

// Instantiate tools agents
$translateTool = new TranslateTool();
$weatherTool   = new WeatherTool();
$divideTool    = new DivideTool();
$multiplyTool  = new MultiplyTool();
$subtractTool  = new SubtractTool();

// Instantiate agents

$spanishAgent = new Agent(
    "Spanish Agent", //name
    "You translate text between English and Spanish, correct Spanish grammar, and explain idioms.", //role
    $translateTool //tools
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


// System‐wide guardrails and instructions
$system->addAgents([$spanishAgent, $weatherAgent, $mathAgent]);
$system->addInstruction("You are a helpful assistant. Be polite and concise. Help the user with their questions and designate the appropriate agent for specialized tasks.");
$system->addGuardrail(new InputLengthGuardrail(1000, "Input too long."));
$system->addGuardrail(new KeywordGuardrail(['spam', 'abuse'], "Inappropriate content."));

// CLI Interface loop for testing the multi-agent system
echo "Welcome to the OpenAI-Style Multi-Agent System!\n";
echo "===============================================\n";
echo "Type 'exit' to quit.\n\n";
echo "Examples:\n";
echo "- \"Hola, ¿cómo estás?\"\n";
echo "- \"What's the weather in Madrid?\"\n";
echo "- \"Calculate 25 / 5\"\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN) ?: '');
    if (strtolower($input) === 'exit') {
        echo " Good Bye\n";
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