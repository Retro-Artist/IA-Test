<?php
/**
 * Model Context Protocol (MCP) for AI Systems
 *
 * Updated to follow OpenAI's official documentation and best practices
 * This implementation follows the core components approach:
 * 1. Model - The LLM powering the agent's reasoning
 * 2. Tools - Functions the agent can use to take action
 * 3. Instructions - Guidelines defining how the agent behaves
 */

declare(strict_types=1);

/**
 * Base class for the Model Context Protocol
 */
class ModelContextProtocol
{
    private string $apiKey;
    private string $model;
    private array $instructions = [];
    private array $tools = [];
    private array $guardrails = [];
    private array $context = [];
    private int $maxTokens;
    private float $temperature;

    /**
     * Initialize the MCP with configuration
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->maxTokens = $config['max_tokens'] ?? 1024;
        $this->temperature = $config['temperature'] ?? 0.7;
    }

    /**
     * Add an instruction to the agent
     */
    public function addInstruction(string $instruction): self
    {
        $this->instructions[] = $instruction;
        return $this;
    }

    /**
     * Add a tool the agent can use
     */
    public function addTool(Tool $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * Add a guardrail to protect the agent's operation
     */
    public function addGuardrail(Guardrail $guardrail): self
    {
        $this->guardrails[] = $guardrail;
        return $this;
    }

    /**
     * Add context for the conversation
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Run the agent with conversation thread (new method for web interface)
     */
    public function runWithThread(array $thread, string $notesContext = ''): string
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

        // Build messages array following OpenAI format
        $messages = $this->buildMessages($thread, $systemPrompt);
        
        // Call OpenAI API
        $response = $this->callOpenAI($messages);
        
        return $this->extractResponse($response);
    }

    /**
     * Build OpenAI messages array following official format
     */
    private function buildMessages(array $thread, string $systemPrompt): array
    {
        $messages = [];
        
        // 1. System message MUST be first (OpenAI best practice)
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
        
        // 2. Add conversation history - thread is already in OpenAI format
        foreach ($thread as $message) {
            // Ensure the message has the correct OpenAI format
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
     * Call OpenAI API following official documentation
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
     * Extract response following official OpenAI format
     */
    private function extractResponse(array $response): string
    {
        $assistantMessage = $response['choices'][0]['message']['content'] ?? 'No response generated.';
        $finishReason = $response['choices'][0]['finish_reason'] ?? 'unknown';
        
        // Log usage information (optional, good practice)
        if (isset($response['usage'])) {
            $usage = $response['usage'];
            error_log("OpenAI API Usage - Prompt: {$usage['prompt_tokens']}, Completion: {$usage['completion_tokens']}, Total: {$usage['total_tokens']}");
        }
        
        return $assistantMessage;
    }

    /**
     * Legacy run method for backward compatibility (example1.php)
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

        // Create a simple thread for legacy support using OpenAI format
        $thread = [['role' => 'user', 'content' => $userInput]];
        
        return $this->runWithThread($thread);
    }
}