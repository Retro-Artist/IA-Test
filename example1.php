<?php
/**
 * Example usage of the Model Context Protocol with Advanced Streaming
 */

declare(strict_types=1);

// Include necessary files com caminhos absolutos
require_once __DIR__ . '/src/Model/ModelContextProtocol.php';
require_once __DIR__ . '/src/Model/Tool.php';  // Adicione esta linha se necessário
require_once __DIR__ . '/src/Model/Guardrail.php';
require_once __DIR__ . '/src/Model/SampleTools.php';

// Load configuration com caminho absoluto
$config = require_once __DIR__ . '/src/config.php';

// Opcional: verificação rápida de configuração
if (empty($config['api_key'])) {
    die("Erro: Chave API não encontrada. Verifique seu arquivo .env\n");
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

// Add context to the conversation
$mcp->addContext('username', 'Rhuan');
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
        // Option 3: Advanced streaming with tool support (best option)
        $mcp->runWithAdvancedStreaming($input);
        
    } catch (Exception $e) {
        echo "\nError: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";