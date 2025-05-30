<?php
/**
 * Unified SystemAPI - Single & Multi-Agent Support
 * 
 * One class that seamlessly handles both single-agent (direct API calls)
 * and multi-agent (delegation) paradigms with the same clean interface.
 */

declare(strict_types=1);

/**
 * SystemAPI class - Unified AI client for single and multi-agent paradigms
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
        
        // If multi-agent mode, also add to manager
        if ($this->isMultiAgent && $this->managerAgent) {
            $this->managerAgent->addInstruction($instruction);
        }
        
        return $this;
    }

    /**
     * Add a tool to the system
     */
    public function addTool(object $tool): self
    {
        $this->tools[] = $tool;
        
        // If multi-agent mode, add to manager
        if ($this->isMultiAgent && $this->managerAgent) {
            $this->managerAgent->addTool($tool);
        }
        
        return $this;
    }

    /**
     * Add a guardrail to the system
     */
    public function addGuardrail(object $guardrail): self
    {
        $this->guardrails[] = $guardrail;
        
        // If multi-agent mode, add to manager
        if ($this->isMultiAgent && $this->managerAgent) {
            $this->managerAgent->addGuardrail($guardrail);
        }
        
        return $this;
    }

    /**
     * Add context to the system
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        
        // If multi-agent mode, add to manager
        if ($this->isMultiAgent && $this->managerAgent) {
            $this->managerAgent->addContext($key, $value);
        }
        
        return $this;
    }
    
    /**
     * Add agents to enable multi-agent mode
     * This is the key method that switches from single to multi-agent!
     */
    public function addAgents(array $agents): self
    {
        $this->agents = array_merge($this->agents, $agents);
        
        // Switch to multi-agent mode
        if (!$this->isMultiAgent) {
            $this->switchToMultiAgent();
        }
        
        // Update manager with new agents
        if ($this->managerAgent) {
            $this->managerAgent->addHandoffs($this->agents);
        }
        
        return $this;
    }

    /**
     * Run the system (works for both single and multi-agent)
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
            // Multi-agent mode: delegate to manager
            return $this->managerAgent->execute($userInput);
        } else {
            // Single-agent mode: direct API call
            return $this->runDirect($userInput);
        }
    }
    
    /**
     * Execute method (alias for run)
     */
    public function execute(string $input): string
    {
        return $this->run($input);
    }

    /**
     * Switch system to multi-agent mode
     */
    private function switchToMultiAgent(): void
    {
        $this->isMultiAgent = true;
        
        // Create manager agent with existing configuration
        require_once 'Agent.php';
        
        $this->managerAgent = new Agent(
            "System Manager",
            "You are a central coordinator that delegates tasks to specialized agents. " .
            "Analyze user requests and handoff to the most appropriate specialist agent. " .
            "Always use handoffs for specialized tasks rather than attempting them yourself."
        );
        
        // Transfer existing configuration to manager
        foreach ($this->instructions as $instruction) {
            $this->managerAgent->addInstruction($instruction);
        }
        
        foreach ($this->tools as $tool) {
            $this->managerAgent->addTool($tool);
        }
        
        foreach ($this->guardrails as $guardrail) {
            $this->managerAgent->addGuardrail($guardrail);
        }
        
        foreach ($this->context as $key => $value) {
            $this->managerAgent->addContext($key, $value);
        }
    }

    /**
     * Direct single-agent execution (original ModelContextProtocol logic)
     */
    private function runDirect(string $userInput): string
    {
        // Create a simple thread for single-agent support
        $thread = [['role' => 'user', 'content' => $userInput]];
        
        return $this->runWithThread($thread);
    }

    /**
     * Run with conversation thread (for web interface compatibility)
     */
    public function runWithThread(array $thread, string $notesContext = ''): string
    {
        if ($this->isMultiAgent) {
            // In multi-agent mode, delegate to manager
            $latestMessage = end($thread);
            if ($latestMessage && $latestMessage['role'] === 'user') {
                return $this->managerAgent->execute($latestMessage['content']);
            }
            return 'No user message found.';
        }
        
        // Single-agent mode: direct API call logic
        return $this->processSingleAgentThread($thread, $notesContext);
    }
    
    /**
     * Process thread for single-agent mode (original logic)
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

        // Build system prompt
        $systemPrompt = implode(' ', $this->instructions);
        
        // Add notes context if provided
        if (!empty($notesContext)) {
            $systemPrompt .= "\n\n" . $notesContext;
        }
        
        // Add tool definitions to system prompt if tools are available
        if (!empty($this->tools)) {
            $systemPrompt .= "\n\nAvailable tools:\n";
            foreach ($this->tools as $tool) {
                $systemPrompt .= $tool->getDefinition() . "\n\n";
            }
            $systemPrompt .= "Use tools when appropriate to help answer questions or complete tasks.";
        }

        // Build messages array following OpenAI format
        $messages = $this->buildMessages($thread, $systemPrompt);
        
        // Call OpenAI API
        $response = $this->callOpenAI($messages);
        
        return $this->processResponse($response);
    }

    /**
     * Build OpenAI messages array
     */
    private function buildMessages(array $thread, string $systemPrompt): array
    {
        $messages = [];
        
        // System message first
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
        
        // Add conversation history
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
    private function callOpenAI(array $messages): array
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
        
        // Add tools to payload if available
        if (!empty($this->tools)) {
            $payload['tools'] = [];
            foreach ($this->tools as $tool) {
                $payload['tools'][] = $tool->getFormat();
            }
            $payload['tool_choice'] = 'auto';
        }
        
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

        // If there are tool calls, execute them
        if (!empty($toolCalls)) {
            $toolResults = [];
            
            foreach ($toolCalls as $toolCall) {
                $functionName = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                
                // Find and execute the tool
                $toolResult = $this->executeTool($functionName, $arguments);
                $toolResults[] = "Tool: $functionName\nResult: $toolResult";
            }
            
            // Combine content with tool results
            $finalResponse = $content;
            if (!empty($toolResults)) {
                $finalResponse .= "\n\nExecuted tool calls:\n" . implode("\n\n", $toolResults);
            }
            
            return $finalResponse;
        }

        return $content ?: 'No response generated.';
    }

    /**
     * Execute a specific tool by name
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
     * Set default configuration for future agents
     */
    private function setDefaultAgentConfig(array $config): void
    {
        // This will be called when Agent class is loaded
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
     * Get all agents (for debugging)
     */
    public function getAgents(): array
    {
        return $this->agents;
    }
}