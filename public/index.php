<?php

/**
 * AI-Powered PHP Environment - Clean Integration
 * Environment loading handled by .env only
 */

require_once __DIR__ . '/../src/Model/SystemAPI.php';
require_once __DIR__ . '/../src/Model/Tool.php';
require_once __DIR__ . '/../src/Model/Guardrail.php';
require_once __DIR__ . '/../src/Tools/SampleTools.php';
require_once __DIR__ . '/../src/Model/Thread.php';

// Load configuration
$config = require_once __DIR__ . '/../config/config.php';

// Initialize models
$thread = new Thread();
$notes = $thread->getNotes();

// Handle AJAX requests for AI chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'chat') {
        $userInput = $_POST['message'] ?? '';
        $userId = $_POST['user_id'] ?? 'default_user';

        if (empty($userInput)) {
            echo json_encode(['error' => 'No message provided']);
            exit;
        }

        try {
            // Get or create active thread thread
            $threadData = $thread->getActiveThread($userId);
            if (!$threadData) {
                echo json_encode(['error' => 'Failed to access thread']);
                exit;
            }

            // Add user message to thread using OpenAI format
            $thread->addUserMessage($threadData['thread'], $userInput);

            // Create SystemAPI instance
            $mcp = new SystemAPI($config);


            $mcp->addInstruction("Você é um assistente útil que fornece informações e realiza tarefas.");
            $mcp->addInstruction("Seja sempre educado e conciso em suas respostas.");
            $mcp->addInstruction("Se não tiver certeza sobre algo, admita sua incerteza.");
            $mcp->addInstruction("Use as ferramentas disponíveis quando apropriado para responder perguntas.");
            $mcp->addInstruction("Você pode puxar notas do banco de dados para adicionar contexto às suas respostas.");
            $mcp->addInstruction("Você pode responder perguntas baseadas no conteúdo da conversa e nos arquivos fornecidos.");
            $mcp->addInstruction("Você deve formular as respostas sobre tópicos apresentados em suas notas de forma clara e acessível.");
            $mcp->addInstruction("Você pode expandir as respostas com informações adicionais e exemplos, se necessário.");

            $mcp->addGuardrail(new InputLengthGuardrail(500, "Input is too long. Please keep it under 500 characters."));
            $mcp->addGuardrail(new KeywordGuardrail(
                ['hack', 'exploit', 'bypass', 'jailbreak', 'prompt injection'],
                "I cannot process requests related to SystemAPI exploitation or unauthorized access."
            ));

            // Build notes context
            $notesContext = '';
            if (!empty($notes)) {
                $notesContext = "Available notes from database:\n";
                foreach ($notes as $i => $note) {
                    $notesContext .= ($i + 1) . ". **{$note['title']}** - {$note['content']}\n";
                }
            }

            // Get response using thread-based method
            $response = $mcp->runWithThread($threadData['thread'], $notesContext);

            // Add bot response to thread using OpenAI format
            $thread->addAssistantMessage($threadData['thread'], $response);

            // Update thread thread in database
            $thread->updateThread($threadData['id'], $threadData['thread']);

            echo json_encode(['response' => $response]);
        } catch (Exception $e) {
            error_log("Chat error: " . $e->getMessage());
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }

        exit;
    }

    if ($_POST['action'] === 'reset_thread') {
        $userId = $_POST['user_id'] ?? 'default_user';

        try {
            $threadData = $thread->getActiveThread($userId);
            if ($threadData) {
                $thread->closethread($threadData['id']);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to reset thread']);
        }

        exit;
    }
}

// Simple database status check
$dbStatus = 'Not configured';
$dbConnected = false;
if (getDatabaseConnection()) {
    $dbStatus = 'Connected';
    $dbConnected = true;
} elseif (getenv('DB_HOST')) {
    $dbStatus = 'Connection failed';
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
                <p class="text-blue-100 text-sm mt-1">Chat with your AI assistant - it has access to <?= $dbConnected && !empty($notes) ? count($notes) . ' notes from your database, plus' : '' ?> weather, calculator, and search tools</p>
            </div>

            <div class="p-6">
                <div id="chatMessages" class="h-96 overflow-y-auto border rounded-lg p-4 mb-4 bg-gray-50">
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-robot text-4xl mb-4 text-gray-400"></i>
                        <p>Hello! I'm your AI assistant with access to your database notes<?= $dbConnected && !empty($notes) ? ' (' . count($notes) . ' notes)' : '' ?>.</p>
                        <p class="text-sm mt-2">Try: "What notes do I have?" or "Calculate 25 * 4" or "What's the weather in Paris?"</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <input type="text" id="messageInput" placeholder="Type your message here..."
                        class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button onclick="sendMessage()" id="sendBtn"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <button onclick="resetThread()" id="resetBtn"
                        class="px-4 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        title="Reset thread">
                        <i class="fas fa-redo"></i>
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
                <?php if ($dbConnected && !empty($notes)): ?>
                    <div class="text-xs text-gray-500 mt-2">
                        <?= count($notes) ?> notes available for AI
                    </div>
                <?php endif; ?>
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
                formData.append('user_id', getUserId());

                let response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                let data = await response.json();

                if (data.error) {
                    addMessage('Error: ' + data.error);
                } else {
                    addMessage(data.response);
                }

            } catch (error) {
                console.error('Send message error:', error);
                addMessage('Error: Failed to send message. Please try again.');
            }

            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            messageInput.focus();
        }

        // Simple user ID generation/storage
        function getUserId() {
            let userId = localStorage.getItem('chatUserId');
            if (!userId) {
                userId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('chatUserId', userId);
            }
            return userId;
        }

        // Reset thread thread
        async function resetThread() {
            let resetBtn = document.getElementById('resetBtn');
            let chatMessages = document.getElementById('chatMessages');

            resetBtn.disabled = true;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                let formData = new FormData();
                formData.append('action', 'reset_thread');
                formData.append('user_id', getUserId());

                let response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                let data = await response.json();

                if (data.success) {
                    // Clear chat interface
                    chatMessages.innerHTML = `
                        <div class="text-gray-500 text-center py-8">
                            <i class="fas fa-robot text-4xl mb-4 text-gray-400"></i>
                            <p>thread reset! Hello again! I'm your AI assistant with access to your database notes.</p>
                            <p class="text-sm mt-2">Try: "What notes do I have?" or "Calculate 25 * 4" or "What's the weather in Paris?"</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Reset failed:', error);
            }

            resetBtn.disabled = false;
            resetBtn.innerHTML = '<i class="fas fa-redo"></i>';
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            let messageInput = document.getElementById('messageInput');
            let sendBtn = document.getElementById('sendBtn');
            let resetBtn = document.getElementById('resetBtn');

            // Enter key listener
            if (messageInput) {
                messageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
                messageInput.focus();
            }

            // Send button listener
            if (sendBtn) {
                sendBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sendMessage();
                });
            }

            // Reset button listener
            if (resetBtn) {
                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    resetThread();
                });
            }
        });
    </script>
</body>

</html>