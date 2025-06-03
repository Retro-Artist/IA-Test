<?php
/**
 * Unified SystemAPI - Enhanced with OpenAI Tool Calls for Multi-Agent Systems
 * 
 * Now properly implements OpenAI's recommended tool_calls pattern for agents
 */

declare(strict_types=1);

/**
 * SystemAPI class - Enhanced for true multi-agent tool calling
 */
class SystemAPI
{
    private string $apiKey;
    private string $model;
    private array $instructions = [];
    private array $tools = [];
    private array $guardrails = [];
    private array $context = [];
    private int $maxTokens;
    private float $temperature;
    
    // Multi-agent properties
    private array $agents = [];
    private bool $isMultiAgent = false;
    private ?object $managerAgent = null;
    private array $managerInstructions = [];

    /**
     * Initialize the System with configuration
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->maxTokens = $config['max_tokens'] ?? 1024;
        $this->temperature = $config['temperature'] ?? 0.7;
        
        // Set default config for future agents
        if (!empty($config)) {
            $this->setDefaultAgentConfig($config);
        }
    }

    /**
     * Add an instruction to the system
     */
    public function addInstruction(string $instruction): self
    {
        $this->instructions[] = $instruction;
        return $this;
    }

    /**
     * Add manager-specific instructions (visible and explicit)
     */
    public function addManagerInstruction(string $instruction): self
    {
        $this->managerInstructions[] = $instruction;
        return $this;
    }

    /**
     * Add a tool to the system
     */
    public function addTool(object $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * Add a guardrail to the system
     */
    public function addGuardrail(object $guardrail): self
    {
        $this->guardrails[] = $guardrail;
        return $this;
    }

    /**
     * Add context to the system
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }
    
    /**
     * Add agents to enable multi-agent mode
     * This switches from single to multi-agent automatically
     */
    public function addAgents(array $agents): self
    {
        $this->agents = array_merge($this->agents, $agents);
        
        // Switch to multi-agent mode
        if (!$this->isMultiAgent) {
            $this->switchToMultiAgent();
        }
        
        return $this;
    }

    /**
     * Run the system with clean output (production mode)
     */
    public function run(string $userInput): string
    {
        // Apply guardrails to input
        foreach ($this->guardrails as $guardrail) {
            $result = $guardrail->validateInput($userInput);
            if (!$result['valid']) {
                return $result['message'];
            }
        }

        if ($this->isMultiAgent) {
            // Multi-agent mode: use tool calls
            return $this->executeMultiAgentToolCalls($userInput);
        } else {
            // Single-agent mode: direct API call
            return $this->runDirect($userInput);
        }
    }

    /**
     * Get full OpenAI payload for debugging (debug mode)
     */
    public function payload(string $userInput): array
    {
        // Apply guardrails to input
        foreach ($this->guardrails as $guardrail) {
            $result = $guardrail->validateInput($userInput);
            if (!$result['valid']) {
                return ['error' => $result['message']];
            }
        }

        if ($this->isMultiAgent) {
            // For multi-agent, show the first call payload (for simple debugging)
            // Use getFullConversationPayload() for complete ReAct loop debugging
            return $this->getMultiAgentPayload($userInput);
        } else {
            return $this->getSingleAgentPayload($userInput);
        }
    }

    /**
     * Get complete ReAct conversation for advanced debugging
     */
    public function fullPayload(string $userInput): array
    {
        // Apply guardrails to input
        foreach ($this->guardrails as $guardrail) {
            $result = $guardrail->validateInput($userInput);
            if (!$result['valid']) {
                return ['error' => $result['message']];
            }
        }

        if ($this->isMultiAgent) {
            return $this->getFullConversationPayload($userInput);
        } else {
            return ['single_agent_payload' => $this->getSingleAgentPayload($userInput)];
        }
    }

    /**
     * Switch system to multi-agent mode
     */
    private function switchToMultiAgent(): void
    {
        $this->isMultiAgent = true;
        
        // Find or create manager agent
        $this->managerAgent = $this->findManagerAgent();
        if (!$this->managerAgent) {
            throw new \Exception('Manager Agent not found. Please ensure you have a "Manager Agent" in your agents list.');
        }
    }

    /**
     * Execute multi-agent workflow using OpenAI tool calls with ReAct loop
     */
    private function executeMultiAgentToolCalls(string $userInput): string
    {
        $systemPrompt = $this->buildManagerSystemPrompt();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userInput]
        ];
        
        $maxIterations = 5; // Prevent infinite loops
        $iteration = 0;
        $finalResult = '';

        while ($iteration < $maxIterations) {
            $iteration++;
            
            // Call LLM with current message history
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $this->buildAgentToolDefinitions(),
                'tool_choice' => 'auto',
                'temperature' => 0,
                'max_tokens' => $this->maxTokens
            ];

            $response = $this->callOpenAI($payload);
            $choice = $response['choices'][0] ?? null;
            
            if (!$choice) {
                break;
            }

            $message = $choice['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];
            $content = $message['content'] ?? '';

            // Add assistant message to conversation
            $messages[] = [
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $toolCalls ?: null
            ];

            if (empty($toolCalls)) {
                // No more tool calls, we're done
                $finalResult = $content ?: $finalResult;
                break;
            }

            // Execute each tool call and add results to conversation
            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? '';
                $functionName = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                
                // Execute the agent tool
                $toolResult = $this->executeAgentTool($functionName, $arguments);
                $finalResult = $toolResult; // Keep track of latest result
                
                // Add tool result to conversation history
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $functionName,
                    'content' => $toolResult
                ];
            }
        }

        return $finalResult ?: 'No result generated.';
    }

    /**
     * Get multi-agent payload with tool calls (for debugging)
     */
    private function getMultiAgentPayload(string $userInput): array
    {
        $systemPrompt = $this->buildManagerSystemPrompt();
        
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userInput]
            ],
            'tools' => $this->buildAgentToolDefinitions(),
            'tool_choice' => 'auto',
            'temperature' => 0,
            'max_tokens' => $this->maxTokens
        ];

        return $this->callOpenAI($payload);
    }

    /**
     * Get full conversation payload for debugging (shows complete ReAct loop)
     */
    public function getFullConversationPayload(string $userInput): array
    {
        $systemPrompt = $this->buildManagerSystemPrompt();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userInput]
        ];
        
        $conversationLog = [];
        $maxIterations = 5;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $this->buildAgentToolDefinitions(),
                'tool_choice' => 'auto',
                'temperature' => 0,
                'max_tokens' => $this->maxTokens
            ];

            $response = $this->callOpenAI($payload);
            $conversationLog[] = [
                'iteration' => $iteration,
                'request' => $payload,
                'response' => $response
            ];

            $choice = $response['choices'][0] ?? null;
            if (!$choice) {
                break;
            }

            $message = $choice['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];
            $content = $message['content'] ?? '';

            $messages[] = [
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $toolCalls ?: null
            ];

            if (empty($toolCalls)) {
                break;
            }

            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? '';
                $functionName = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                
                $toolResult = $this->executeAgentTool($functionName, $arguments);
                
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $functionName,
                    'content' => $toolResult
                ];
            }
        }

        return [
            'total_iterations' => $iteration,
            'conversation_log' => $conversationLog,
            'final_messages' => $messages
        ];
    }

    /**
     * Build system prompt for manager agent (using explicit instructions)
     */
    private function buildManagerSystemPrompt(): string
    {
        $baseInstructions = implode(' ', $this->instructions);
        
        $availableAgents = array_map(function($agent) {
            return $agent->getName() . ': ' . $agent->getRole();
        }, $this->getWorkerAgents());

        // Use explicit manager instructions if provided
        $managerInstructions = '';
        if (!empty($this->managerInstructions)) {
            $managerInstructions = implode("\n", $this->managerInstructions);
        } else {
            // Minimal fallback if no explicit instructions provided
            $managerInstructions = "You are a Manager Agent that coordinates specialized agents. " .
                "Available agents: " . implode(', ', $availableAgents) . ". " .
                "Use tool calls to delegate tasks to appropriate agents.";
        }

        return $baseInstructions . "\n\n" . $managerInstructions;
    }

    /**
     * Build tool definitions for OpenAI API
     */
    private function buildAgentToolDefinitions(): array
    {
        $toolDefs = [];
        
        foreach ($this->getWorkerAgents() as $agent) {
            $agentName = strtolower(str_replace(' ', '_', $agent->getName()));
            
            if (stripos($agent->getName(), 'weather') !== false) {
                $toolDefs[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $agentName,
                        'description' => 'Get current weather information for any location worldwide',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'The city/location to get weather for (e.g., "Madrid", "New York", "Tokyo")'
                                ]
                            ],
                            'required' => ['location']
                        ]
                    ]
                ];
            } else if (stripos($agent->getName(), 'spanish') !== false || stripos($agent->getName(), 'translate') !== false) {
                $toolDefs[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $agentName,
                        'description' => 'Translate text from English to Spanish or Spanish to English',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'text' => [
                                    'type' => 'string',
                                    'description' => 'The text to translate'
                                ],
                                'direction' => [
                                    'type' => 'string',
                                    'description' => 'Translation direction: "to_spanish" or "to_english"',
                                    'enum' => ['to_spanish', 'to_english']
                                ]
                            ],
                            'required' => ['text']
                        ]
                    ]
                ];
            } else if (stripos($agent->getName(), 'math') !== false) {
                $toolDefs[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $agentName,
                        'description' => 'Perform mathematical calculations and arithmetic operations',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'expression' => [
                                    'type' => 'string',
                                    'description' => 'The mathematical expression to calculate (e.g., "25 * 8", "15 + 27")'
                                ]
                            ],
                            'required' => ['expression']
                        ]
                    ]
                ];
            } else {
                // Generic agent
                $toolDefs[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $agentName,
                        'description' => $agent->getRole(),
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'task' => [
                                    'type' => 'string',
                                    'description' => 'The specific task for this agent'
                                ]
                            ],
                            'required' => ['task']
                        ]
                    ]
                ];
            }
        }

        return $toolDefs;
    }

    /**
     * Execute a specific agent tool
     */
    private function executeAgentTool(string $functionName, array $arguments): string
    {
        $agentName = str_replace('_', ' ', ucwords($functionName, '_'));
        
        foreach ($this->getWorkerAgents() as $agent) {
            if (strcasecmp($agent->getName(), $agentName) === 0) {
                
                // Handle different agent types with proper argument mapping
                if (stripos($agent->getName(), 'weather') !== false) {
                    $location = $arguments['location'] ?? 'New York';
                    return $agent->execute("Get weather for " . $location);
                }
                
                if (stripos($agent->getName(), 'spanish') !== false || stripos($agent->getName(), 'translate') !== false) {
                    $text = $arguments['text'] ?? '';
                    $direction = $arguments['direction'] ?? 'to_spanish';
                    
                    if (empty($text)) {
                        return "Error: No text provided for translation.";
                    }
                    
                    $task = $direction === 'to_english' ? 
                        "Translate to English: " . $text : 
                        "Translate to Spanish: " . $text;
                    
                    return $agent->execute($task);
                }
                
                if (stripos($agent->getName(), 'math') !== false) {
                    $expression = $arguments['expression'] ?? $arguments['task'] ?? '';
                    return $agent->execute("Calculate: " . $expression);
                }
                
                // Generic task handling
                $task = $arguments['task'] ?? '';
                if (empty($task)) {
                    $task = implode(' ', array_filter($arguments));
                }

                try {
                    return $agent->execute($task);
                } catch (Exception $e) {
                    return "Error executing {$agent->getName()}: " . $e->getMessage();
                }
            }
        }
        
        return "Agent '{$agentName}' not found.";
    }

    /**
     * Direct single-agent execution
     */
    private function runDirect(string $userInput): string
    {
        $thread = [['role' => 'user', 'content' => $userInput]];
        return $this->runWithThread($thread);
    }

    /**
     * Get single-agent payload
     */
    private function getSingleAgentPayload(string $userInput): array
    {
        $thread = [['role' => 'user', 'content' => $userInput]];
        return $this->getSingleAgentThreadPayload($thread);
    }

    /**
     * Run with conversation thread (for web interface compatibility)
     */
    public function runWithThread(array $thread, string $notesContext = ''): string
    {
        if ($this->isMultiAgent) {
            // In multi-agent mode, extract latest message
            $latestMessage = end($thread);
            if ($latestMessage && $latestMessage['role'] === 'user') {
                return $this->executeMultiAgentToolCalls($latestMessage['content']);
            }
            return 'No user message found.';
        }
        
        // Single-agent mode: process thread normally
        return $this->processSingleAgentThread($thread, $notesContext);
    }

    /**
     * Process thread for single-agent mode
     */
    private function processSingleAgentThread(array $thread, string $notesContext = ''): string
    {
        // Apply guardrails to the latest user input
        $latestMessage = end($thread);
        if ($latestMessage && $latestMessage['role'] === 'user') {
            foreach ($this->guardrails as $guardrail) {
                $result = $guardrail->validateInput($latestMessage['content']);
                if (!$result['valid']) {
                    return $result['message'];
                }
            }
        }

        $systemPrompt = implode(' ', $this->instructions);
        
        if (!empty($notesContext)) {
            $systemPrompt .= "\n\n" . $notesContext;
        }
        
        if (!empty($this->tools)) {
            $systemPrompt .= "\n\nAvailable tools:\n";
            foreach ($this->tools as $tool) {
                $systemPrompt .= $tool->getDefinition() . "\n\n";
            }
            $systemPrompt .= "Use tools when appropriate to help answer questions or complete tasks.";
        }

        $messages = $this->buildMessages($thread, $systemPrompt);
        $payload = $this->buildSingleAgentPayload($messages);
        $response = $this->callOpenAI($payload);
        
        return $this->processResponse($response);
    }

    /**
     * Get single-agent thread payload
     */
    private function getSingleAgentThreadPayload(array $thread, string $notesContext = ''): array
    {
        $systemPrompt = implode(' ', $this->instructions);
        
        if (!empty($notesContext)) {
            $systemPrompt .= "\n\n" . $notesContext;
        }
        
        if (!empty($this->tools)) {
            $systemPrompt .= "\n\nAvailable tools:\n";
            foreach ($this->tools as $tool) {
                $systemPrompt .= $tool->getDefinition() . "\n\n";
            }
        }

        $messages = $this->buildMessages($thread, $systemPrompt);
        $payload = $this->buildSingleAgentPayload($messages);
        
        return $this->callOpenAI($payload);
    }

    /**
     * Build single-agent payload
     */
    private function buildSingleAgentPayload(array $messages): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        ];
        
        if (!empty($this->tools)) {
            $payload['tools'] = [];
            foreach ($this->tools as $tool) {
                $payload['tools'][] = $tool->getFormat();
            }
            $payload['tool_choice'] = 'auto';
        }
        
        return $payload;
    }

    /**
     * Build OpenAI messages array
     */
    private function buildMessages(array $thread, string $systemPrompt): array
    {
        $messages = [];
        
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
        
        foreach ($thread as $message) {
            if (isset($message['role']) && isset($message['content'])) {
                $messages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
        }
        
        return $messages;
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(array $payload): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP error: $httpCode";
            throw new Exception("OpenAI API error: " . $errorMessage);
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parse error: " . json_last_error_msg());
        }
        
        return $decoded;
    }

    /**
     * Process OpenAI response and handle tool calls
     */
    private function processResponse(array $response): string
    {
        $choice = $response['choices'][0] ?? null;
        if (!$choice) {
            return 'No response generated.';
        }

        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? '';
        $toolCalls = $message['tool_calls'] ?? [];

        if (!empty($toolCalls)) {
            $toolResults = [];
            
            foreach ($toolCalls as $toolCall) {
                $functionName = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                
                $toolResult = $this->executeTool($functionName, $arguments);
                $toolResults[] = "Tool: $functionName\nResult: $toolResult";
            }
            
            $finalResponse = $content;
            if (!empty($toolResults)) {
                $finalResponse .= "\n\nExecuted tool calls:\n" . implode("\n\n", $toolResults);
            }
            
            return $finalResponse;
        }

        return $content ?: 'No response generated.';
    }

    /**
     * Execute a specific tool by name (for single-agent mode)
     */
    private function executeTool(string $toolName, array $arguments): string
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $toolName) {
                try {
                    return $tool->execute($arguments);
                } catch (Exception $e) {
                    return "Tool execution error: " . $e->getMessage();
                }
            }
        }
        
        return "Tool '$toolName' not found.";
    }

    /**
     * Find manager agent
     */
    private function findManagerAgent(): ?object
    {
        foreach ($this->agents as $agent) {
            if (stripos($agent->getName(), 'manager') !== false) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * Get worker agents (non-manager agents)
     */
    private function getWorkerAgents(): array
    {
        return array_filter($this->agents, function($agent) {
            return stripos($agent->getName(), 'manager') === false;
        });
    }
    
    /**
     * Set default configuration for future agents
     */
    private function setDefaultAgentConfig(array $config): void
    {
        if (class_exists('Agent')) {
            Agent::setDefaultConfig($config);
        }
    }

    /**
     * Get all tools (for debugging)
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get all instructions (for debugging)
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }
    
    /**
     * Check if system is in multi-agent mode
     */
    public function isMultiAgentMode(): bool
    {
        return $this->isMultiAgent;
    }
    
    /**
     * Get all manager instructions (for debugging)
     */
    public function getManagerInstructions(): array
    {
        return $this->managerInstructions;
    }

    /**
     * Get all agents (for debugging)
     */
    public function getAgents(): array
    {
        return $this->agents;
    }
}
