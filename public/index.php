<?php
/**
 * AI-Powered PHP Environment - Clean Integration
 * Environment loading handled by .env only
 */

// Include necessary files with absolute paths
require_once __DIR__ . '/../src/Model/ModelContextProtocol.php';
require_once __DIR__ . '/../src/Model/Tool.php';
require_once __DIR__ . '/../src/Model/Guardrail.php';
require_once __DIR__ . '/../src/Model/SampleTools.php';

// Load configuration
$config = require_once __DIR__ . '/../config/config.php';

// Handle AJAX requests for AI chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
    header('Content-Type: application/json');
    
    $userInput = $_POST['message'] ?? '';
    
    if (empty($userInput)) {
        echo json_encode(['error' => 'No message provided']);
        exit;
    }
    
    try {
        // Create ModelContextProtocol instance (exactly like example1.php)
        $mcp = new ModelContextProtocol($config);

        // Add instructions (same as example1.php)
        $mcp->addInstruction("You are a helpful assistant that provides information and performs tasks.");
        $mcp->addInstruction("Always be polite and concise in your responses.");
        $mcp->addInstruction("If you're unsure about something, acknowledge your uncertainty.");
        $mcp->addInstruction("Use the tools available to you when appropriate to answer questions.");

        // Add tools (same as example1.php)
        $mcp->addTool(new WeatherTool());
        $mcp->addTool(new CalculatorTool());
        $mcp->addTool(new SearchTool());

        // Add guardrails (same as example1.php)
        $mcp->addGuardrail(new InputLengthGuardrail(500, "Input is too long. Please keep it under 500 characters."));
        $mcp->addGuardrail(new KeywordGuardrail(
            ['hack', 'exploit', 'bypass', 'jailbreak', 'prompt injection'], 
            "I cannot process requests related to system exploitation or unauthorized access."
        ));

        // Add context (similar to example1.php)
        $mcp->addContext('interface', 'web');
        $mcp->addContext('current_date', date('Y-m-d'));

        // Get response (same as example1.php)
        $response = $mcp->run($userInput);
        
        echo json_encode(['response' => $response]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Simple database status check
$dbStatus = 'Not configured';
if (getenv('DB_HOST')) {
    try {
        $dsn = sprintf("mysql:host=%s;dbname=%s", getenv('DB_HOST'), getenv('DB_DATABASE'));
        $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
        $dbStatus = 'Connected';
    } catch (PDOException $e) {
        $dbStatus = 'Connection failed';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered PHP Environment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <header class="text-center pb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-robot text-blue-600 mr-3"></i>
                AI-Powered PHP Environment
            </h1>
            <p class="text-lg text-gray-600">PHP <?= phpversion() ?> with OpenAI Integration</p>
        </header>

        <!-- AI Chat Interface -->
        <div class="bg-white rounded-xl shadow-lg mb-8 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4">
                <h2 class="text-xl font-semibold flex items-center">
                    <i class="fas fa-comments mr-3"></i>
                    AI Assistant
                </h2>
                <p class="text-blue-100 text-sm mt-1">Chat with your AI assistant - try asking about weather, calculations, or searches</p>
            </div>
            
            <div class="p-6">
                <div id="chatMessages" class="h-96 overflow-y-auto border rounded-lg p-4 mb-4 bg-gray-50">
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-robot text-4xl mb-4 text-gray-400"></i>
                        <p>Hello! I'm your AI assistant with weather, calculator, and search tools.</p>
                        <p class="text-sm mt-2">Try: "What's the weather in Paris?" or "Calculate 25 * 4"</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <input type="text" id="messageInput" placeholder="Type your message here..." 
                           class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button onclick="sendMessage()" id="sendBtn" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-cog text-blue-500 mr-2"></i>
                    Environment
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">PHP Version:</span>
                        <span class="font-medium"><?= phpversion() ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">OpenAI Model:</span>
                        <span class="font-medium"><?= htmlspecialchars($config['model']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-server text-green-500 mr-2"></i>
                    Server Status
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Web Server:</span>
                        <span class="font-medium text-green-600">Running</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Environment:</span>
                        <span class="font-medium">Development</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-database text-purple-500 mr-2"></i>
                    Database
                </h3>
                <div class="flex items-center">
                    <span class="<?= $dbStatus === 'Connected' ? 'text-green-600' : 'text-red-600' ?> font-medium">
                        <i class="fas fa-<?= $dbStatus === 'Connected' ? 'check-circle' : 'times-circle' ?> mr-2"></i>
                        <?= $dbStatus ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Available Tools -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-tools text-orange-500 mr-2"></i>
                Available AI Tools
            </h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                    <i class="fas fa-cloud-sun text-blue-500 mr-3"></i>
                    <span class="text-gray-700">Weather Information</span>
                </div>
                <div class="flex items-center p-3 bg-green-50 rounded-lg">
                    <i class="fas fa-calculator text-green-500 mr-3"></i>
                    <span class="text-gray-700">Mathematical Calculator</span>
                </div>
                <div class="flex items-center p-3 bg-purple-50 rounded-lg">
                    <i class="fas fa-search text-purple-500 mr-3"></i>
                    <span class="text-gray-700">Search Functionality</span>
                </div>
            </div>
        </div>

        <footer class="text-center pt-8 mt-8 border-t text-gray-500">
            <p>&copy; <?= date('Y') ?> AI-Powered PHP Environment</p>
        </footer>
    </div>

    <script>
        function addMessage(content, isUser = false) {
            let chatMessages = document.getElementById('chatMessages');
            let messageDiv = document.createElement('div');
            messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`;
            
            messageDiv.innerHTML = `
                <div class="max-w-md px-4 py-2 rounded-lg ${
                    isUser 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-white border text-gray-800'
                }">
                    <div class="text-sm whitespace-pre-wrap">${content}</div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        async function sendMessage() {
            let messageInput = document.getElementById('messageInput');
            let sendBtn = document.getElementById('sendBtn');
            let message = messageInput.value.trim();
            
            if (!message) return;

            messageInput.value = '';
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            addMessage(message, true);

            try {
                let formData = new FormData();
                formData.append('action', 'chat');
                formData.append('message', message);

                let response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                let data = await response.json();
                addMessage(data.error || data.response);

            } catch (error) {
                addMessage('Error: Failed to send message. Please try again.');
            }

            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            messageInput.focus();
        }

        document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });

        document.getElementById('messageInput')?.focus();
    </script>
</body>

</html>