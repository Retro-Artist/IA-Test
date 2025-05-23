<?php
/**
 * Model Context Protocol (MCP) for AI Systems
 *
 * Based on the agent design philosophy from OpenAI's "A practical guide to building agents"
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
     * Run the agent with a user input
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

        // Prepare message for the LLM
        $messages = $this->prepareMessages($userInput);
        
        // Call OpenAI API
        $response = $this->callLLM($messages);
        
        // Process the response, looking for tool calls
        return $this->processResponse($response);
    }

    /**
     * Prepare messages for the LLM with system instructions and tools
     */
    private function prepareMessages(string $userInput): array
    {
        $messages = [];
        
        // Add system message with instructions
        $systemContent = implode("\n\n", $this->instructions);
        
        // Add tool definitions if available
        if (!empty($this->tools)) {
            $toolDefinitions = array_map(fn($tool) => $tool->getDefinition(), $this->tools);
            $systemContent .= "\n\nYou have access to the following tools:\n";
            $systemContent .= implode("\n", $toolDefinitions);
        }
        
        $messages[] = ['role' => 'system', 'content' => $systemContent];
        
        // Add context if available
        if (!empty($this->context)) {
            $contextMessage = "Here is some additional context:\n";
            foreach ($this->context as $key => $value) {
                $contextMessage .= "$key: $value\n";
            }
            $messages[] = ['role' => 'system', 'content' => $contextMessage];
        }
        
        // Add user message
        $messages[] = ['role' => 'user', 'content' => $userInput];
        
        return $messages;
    }

    /**
     * Call the LLM API
     */
    private function callLLM(array $messages): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature
        ];
        
        // If tools are available, add them to the payload
        if (!empty($this->tools)) {
            $toolFormats = [];
            foreach ($this->tools as $tool) {
                $toolFormats[] = $tool->getFormat();
            }
            $payload['tools'] = $toolFormats;
            $payload['tool_choice'] = 'auto';
        }
        
        // Prepare API call
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        // Execute API call
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Handle curl errors
        if ($response === false) {
            throw new Exception("cURL error: " . $curlError);
        }
        
        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP error: $httpCode";
            throw new Exception($errorMessage);
        }
        
        // Parse the response
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parse error: " . json_last_error_msg());
        }
        
        return $decoded;
    }

    /**
     * Process the LLM response, handling tool calls if needed
     */
    private function processResponse(array $response): string
    {
        // Debug the response if needed
        // error_log("Response: " . json_encode($response));
        
        // Extract the response content
        $message = $response['choices'][0]['message'] ?? null;
        
        if (!$message) {
            throw new Exception("No message in the model response: " . json_encode($response));
        }
        
        // Check if the response contains tool calls
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            $results = [];
            
            // Process each tool call
            foreach ($message['tool_calls'] as $toolCall) {
                $toolId = $toolCall['id'] ?? 'unknown-id';
                $functionName = $toolCall['function']['name'] ?? 'unknown-function';
                
                // Safely decode the arguments
                $argumentsJson = $toolCall['function']['arguments'] ?? '{}';
                $arguments = json_decode($argumentsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $results[] = "Error parsing arguments for $functionName: " . json_last_error_msg();
                    continue;
                }
                
                // Find the matching tool
                $tool = $this->findTool($functionName);
                
                if ($tool) {
                    try {
                        // Execute the tool and get the result
                        $result = $tool->execute($arguments);
                        $results[] = "$functionName: $result";
                    } catch (Exception $e) {
                        $results[] = "Error executing $functionName: " . $e->getMessage();
                    }
                } else {
                    $results[] = "Tool '$functionName' not found.";
                }
            }
            
            return "Executed tool calls:\n" . implode("\n", $results);
        }
        
        // Return the content if no tool calls
        return $message['content'] ?? "No content in response.";
    }

    /**
     * Find a tool by name
     */
    private function findTool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        return null;
    }


/**
 * Print text character by character with a delay
 * 
 * @param string $text Text to print
 * @param int $delay Delay in microseconds between characters (default: 30000 = 30ms)
 */
public function streamOutput(string $text, int $delay = 30000): void
{
    // Loop through each character in the text
    for ($i = 0; $i < mb_strlen($text); $i++) {
        // Output a single character
        echo mb_substr($text, $i, 1);
        
        // Flush the output buffer to ensure immediate display
        flush();
        
        // Small delay between characters
        usleep($delay);
    }
    
    // Add a new line at the end
    echo PHP_EOL;
}

/**
 * Run the agent with streaming output
 */
public function runWithStreaming(string $userInput, int $delay = 10000): string
{
    // Apply guardrails to input (same as in run method)
    foreach ($this->guardrails as $guardrail) {
        $result = $guardrail->validateInput($userInput);
        if (!$result['valid']) {
            // Stream the error message
            $this->streamOutput($result['message'], $delay);
            return $result['message'];
        }
    }

    // Prepare message for the LLM
    $messages = $this->prepareMessages($userInput);
    
    // Call OpenAI API
    $response = $this->callLLM($messages);
    
    // Process the response, looking for tool calls
    $output = $this->processResponse($response);
    
    // Stream the output
    $this->streamOutput($output, $delay);
    
    return $output;
}


/**
 * Call the LLM API with streaming and tool support
 */
private function callLLMWithAdvancedStreaming(array $messages): array
{
    $payload = [
        'model' => $this->model,
        'messages' => $messages,
        'max_tokens' => $this->maxTokens,
        'temperature' => $this->temperature,
        'stream' => true // Enable streaming
    ];
    
    // If tools are available, add them to the payload
    if (!empty($this->tools)) {
        $toolFormats = [];
        foreach ($this->tools as $tool) {
            $toolFormats[] = $tool->getFormat();
        }
        $payload['tools'] = $toolFormats;
        $payload['tool_choice'] = 'auto';
    }
    
    // Prepare API call
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->apiKey
    ]);
    
    // Arrays to collect response data
    $content = '';
    $toolCalls = [];
    $currentToolCall = null;
    
    // Set up streaming callback
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$content, &$toolCalls, &$currentToolCall) {
        // Each chunk from OpenAI API is prefixed with "data: "
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            // Skip empty lines or lines that don't contain data
            if (empty($line) || strpos($line, 'data: ') !== 0) {
                continue;
            }
            
            // Extract the JSON data
            $jsonData = substr($line, 6); // Remove "data: " prefix
            
            // Check for the end of the stream marker
            if ($jsonData === '[DONE]') {
                return strlen($data);
            }
            
            // Decode the JSON
            $decoded = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            // Extract content delta and print it immediately
            $contentDelta = $decoded['choices'][0]['delta']['content'] ?? '';
            if (!empty($contentDelta)) {
                $content .= $contentDelta;
                echo $contentDelta;
                flush();
            }
            
            // Check for tool calls
            $toolCallDelta = $decoded['choices'][0]['delta']['tool_calls'] ?? [];
            if (!empty($toolCallDelta)) {
                foreach ($toolCallDelta as $delta) {
                    $index = $delta['index'] ?? 0;
                    
                    // Initialize tool call if not exists
                    if (!isset($toolCalls[$index])) {
                        $toolCalls[$index] = [
                            'id' => '',
                            'function' => [
                                'name' => '',
                                'arguments' => ''
                            ]
                        ];
                    }
                    
                    // Update tool call ID
                    if (isset($delta['id'])) {
                        $toolCalls[$index]['id'] = $delta['id'];
                    }
                    
                    // Update function name
                    if (isset($delta['function']['name'])) {
                        $toolCalls[$index]['function']['name'] = $delta['function']['name'];
                    }
                    
                    // Update function arguments
                    if (isset($delta['function']['arguments'])) {
                        $toolCalls[$index]['function']['arguments'] .= $delta['function']['arguments'];
                    }
                }
                
                // When tool calls are detected, print a notice
                if (!$currentToolCall && count($toolCalls) > 0) {
                    echo "\n[Preparing to execute tool...]\n";
                    $currentToolCall = true;
                }
            }
        }
        
        return strlen($data);
    });
    
    // Execute API call
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle curl errors
    if ($httpCode !== 200) {
        echo "\nError: HTTP status $httpCode - $curlError\n";
    }
    
    // Add a newline after the streamed output
    echo PHP_EOL;
    
    // Return collected data
    return [
        'content' => $content,
        'tool_calls' => $toolCalls
    ];
}

/**
 * Run the agent with advanced OpenAI streaming and tool support
 */
public function runWithAdvancedStreaming(string $userInput): void
{
    // Apply guardrails to input
    foreach ($this->guardrails as $guardrail) {
        $result = $guardrail->validateInput($userInput);
        if (!$result['valid']) {
            echo $result['message'] . PHP_EOL;
            return;
        }
    }

    // Prepare message for the LLM
    $messages = $this->prepareMessages($userInput);
    
    // Call OpenAI API with streaming and collect response
    $response = $this->callLLMWithAdvancedStreaming($messages);
    
    // Handle tool calls if they exist
    if (!empty($response['tool_calls'])) {
        echo "\nExecuting tools...\n";
        
        $results = [];
        foreach ($response['tool_calls'] as $toolCall) {
            $functionName = $toolCall['function']['name'] ?? 'unknown-function';
            
            // Safely decode the arguments
            $argumentsJson = $toolCall['function']['arguments'] ?? '{}';
            $arguments = json_decode($argumentsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $results[] = "Error parsing arguments for $functionName: " . json_last_error_msg();
                continue;
            }
            
            // Find the matching tool
            $tool = $this->findTool($functionName);
            
            if ($tool) {
                try {
                    // Execute the tool and get the result
                    $result = $tool->execute($arguments);
                    $results[] = "$functionName: $result";
                } catch (Exception $e) {
                    $results[] = "Error executing $functionName: " . $e->getMessage();
                }
            } else {
                $results[] = "Tool '$functionName' not found.";
            }
        }
        
        // Stream the results character by character
        $this->streamOutput("\nTool Results:\n" . implode("\n", $results));
        
        // After tools are executed, we could follow up with another LLM call
        // to process the results, but that's beyond the scope of this example
    }


}
}