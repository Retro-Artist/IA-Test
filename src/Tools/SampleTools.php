<?php
/**
 * Enhanced Sample Tools with Intelligent Self-Parsing
 * 
 * Each tool handles its own argument parsing and logic - proper separation of concerns
 */

require_once __DIR__ . '/../Model/Tool.php';

/**
 * Weather Tool - Handles all weather-related logic
 */
class WeatherTool extends Tool
{
    public function __construct()
    {
        $this->name = 'get_weather';
        $this->description = 'Get the current weather for a location';
        $this->parameters = [
            'location' => [
                'type' => 'string',
                'description' => 'The city and country, e.g., "Paris, France"',
                'required' => true
            ]
        ];
    }
    
    /**
     * Get OpenAI tool definition for this tool
     */
    public function getOpenAIDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'weather_agent',
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
    }
    
    public function execute(array $arguments): string
    {
        $location = $this->extractLocation($arguments);
        
        // Mock weather data
        $weatherConditions = ['sunny', 'partly cloudy', 'cloudy', 'rainy', 'stormy', 'snowy'];
        $temperatures = [15, 22, 28, 5, 35, -2, 18, 25];
        
        $condition = $weatherConditions[array_rand($weatherConditions)];
        $temperature = $temperatures[array_rand($temperatures)];
        
        return "The weather in $location is currently $condition with a temperature of {$temperature}°C.";
    }
    
    /**
     * Extract location from various argument formats
     */
    private function extractLocation(array $arguments): string
    {
        // Direct location argument
        if (!empty($arguments['location'])) {
            return $arguments['location'];
        }
        
        // Extract from task description
        $task = $arguments['task'] ?? '';
        
        // Weather-specific patterns
        $patterns = [
            '/weather\s+(?:in|for)\s+([^,.\n?]+)/i',
            '/(?:get|tell|show).*weather.*(?:in|for)\s+([^,.\n?]+)/i',
            '/(?:in|for)\s+([A-Za-z\s]+)(?:\s|$)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $task, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Default location
        return 'New York';
    }
}

/**
 * Calculator Tool - Handles all mathematical operations and parsing
 */
class CalculusTool extends Tool
{
    public function __construct()
    {
        $this->name = 'calculate';
        $this->description = 'Perform arithmetic calculations';
        $this->parameters = [
            'operation' => [
                'type' => 'string',
                'description' => 'The operation to perform: add, subtract, multiply, or divide',
                'required' => false
            ],
            'numbers' => [
                'type' => 'array',
                'description' => 'The numbers to operate on',
                'required' => false,
                'items' => [
                    'type' => 'number',
                    'description' => 'A numeric value'
                ]
            ],
            'expression' => [
                'type' => 'string',
                'description' => 'Mathematical expression like "25 * 8" or "15 + 27"',
                'required' => false
            ]
        ];
    }
    
    /**
     * Get OpenAI tool definition for this tool
     */
    public function getOpenAIDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'math_agent',
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
    }
    
    public function execute(array $arguments): string
    {
        // Try to parse the expression or task
        $result = $this->parseAndCalculate($arguments);
        
        if ($result === null) {
            return "Error: Unable to parse mathematical expression from input.";
        }
        
        return $result;
    }
    
    /**
     * Parse and calculate from various input formats
     */
    private function parseAndCalculate(array $arguments): ?string
    {
        // Direct expression
        if (!empty($arguments['expression'])) {
            return $this->evaluateExpression($arguments['expression']);
        }
        
        // Direct operation and numbers
        if (!empty($arguments['operation']) && !empty($arguments['numbers'])) {
            return $this->performOperation($arguments['operation'], $arguments['numbers']);
        }
        
        // Parse from task description
        $task = $arguments['task'] ?? '';
        if (!empty($task)) {
            return $this->parseTaskExpression($task);
        }
        
        return null;
    }
    
    /**
     * Parse mathematical expressions from natural language
     */
    private function parseTaskExpression(string $task): ?string
    {
        // Remove common prefixes
        $task = preg_replace('/^(?:calculate|compute|what\'?s|find)\s*/i', '', $task);
        $task = preg_replace('/\s*[?.]?\s*$/', '', $task); // Remove trailing punctuation
        
        // Basic arithmetic patterns
        $patterns = [
            // Addition: "25 + 8", "add 25 and 8"
            '/(?:add\s+)?(\d+(?:\.\d+)?)\s*(?:\+|and|plus)\s*(\d+(?:\.\d+)?)/i' => 'add',
            // Subtraction: "25 - 8", "subtract 8 from 25"
            '/(?:subtract\s+(\d+(?:\.\d+)?)\s+from\s+(\d+(?:\.\d+)?)|(\d+(?:\.\d+)?)\s*(?:\-|minus)\s*(\d+(?:\.\d+)?))/i' => 'subtract',
            // Multiplication: "25 * 8", "25 times 8", "multiply 25 by 8"
            '/(?:multiply\s+(\d+(?:\.\d+)?)\s+by\s+(\d+(?:\.\d+)?)|(\d+(?:\.\d+)?)\s*(?:\*|×|times)\s*(\d+(?:\.\d+)?))/i' => 'multiply',
            // Division: "25 / 8", "25 divided by 8", "divide 25 by 8"
            '/(?:divide\s+(\d+(?:\.\d+)?)\s+by\s+(\d+(?:\.\d+)?)|(\d+(?:\.\d+)?)\s*(?:\/|÷|divided\s+by)\s*(\d+(?:\.\d+)?))/i' => 'divide'
        ];
        
        foreach ($patterns as $pattern => $operation) {
            if (preg_match($pattern, $task, $matches)) {
                // Extract numbers (handles different capture group arrangements)
                $numbers = array_filter($matches, function($match, $index) {
                    return $index > 0 && is_numeric($match);
                }, ARRAY_FILTER_USE_BOTH);
                
                if (count($numbers) >= 2) {
                    $nums = array_values($numbers);
                    return $this->performOperation($operation, [(float)$nums[0], (float)$nums[1]]);
                }
            }
        }
        
        // Try simple expression evaluation
        return $this->evaluateExpression($task);
    }
    
    /**
     * Evaluate simple mathematical expressions
     */
    private function evaluateExpression(string $expression): ?string
    {
        // Basic safety check - only allow numbers and basic operators
        if (!preg_match('/^[\d\s+\-*\/().]+$/', $expression)) {
            return null;
        }
        
        try {
            // Simple evaluation for basic expressions
            $result = eval("return $expression;");
            return "$expression = $result";
        } catch (Throwable $e) {
            return null;
        }
    }
    
    /**
     * Perform specific mathematical operations
     */
    private function performOperation(string $operation, array $numbers): string
    {
        if (empty($numbers) || !is_array($numbers)) {
            return "Error: No numbers provided for calculation.";
        }
        
        $numbers = array_map('floatval', $numbers);
        
        switch ($operation) {
            case 'add':
                $result = array_sum($numbers);
                $equation = implode(' + ', $numbers) . ' = ' . $result;
                break;
                
            case 'subtract':
                $result = $numbers[0];
                $equation = $numbers[0];
                for ($i = 1; $i < count($numbers); $i++) {
                    $result -= $numbers[$i];
                    $equation .= ' - ' . $numbers[$i];
                }
                $equation .= ' = ' . $result;
                break;
                
            case 'multiply':
                $result = array_reduce($numbers, fn($carry, $item) => $carry * $item, 1);
                $equation = implode(' × ', $numbers) . ' = ' . $result;
                break;
                
            case 'divide':
                if (count($numbers) < 2) {
                    return "Error: Division requires at least two numbers.";
                }
                
                if (in_array(0, array_slice($numbers, 1))) {
                    return "Error: Cannot divide by zero.";
                }
                
                $result = $numbers[0];
                $equation = $numbers[0];
                for ($i = 1; $i < count($numbers); $i++) {
                    $result /= $numbers[$i];
                    $equation .= ' ÷ ' . $numbers[$i];
                }
                $equation .= ' = ' . $result;
                break;
                
            default:
                return "Error: Unknown operation '$operation'. Use add, subtract, multiply, or divide.";
        }
        
        return $equation;
    }
}

/**
 * Translation Tool - Handles all translation logic and parsing
 */
class TranslateTool extends Tool
{
    public function __construct()
    {
        $this->name = 'translate';
        $this->description = 'Translate text between English and Spanish, correct grammar, and explain idioms';
        $this->parameters = [
            'text' => [
                'type' => 'string',
                'description' => 'The text to translate or work with',
                'required' => true
            ],
            'task' => [
                'type' => 'string',
                'description' => 'The task: translate_to_spanish, translate_to_english, correct_grammar, or explain_idiom',
                'required' => false
            ],
            'direction' => [
                'type' => 'string',
                'description' => 'Translation direction: to_spanish or to_english',
                'required' => false
            ]
        ];
    }
    
    /**
     * Get OpenAI tool definition for this tool
     */
    public function getOpenAIDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'spanish_agent',
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
    }
    
    public function execute(array $arguments): string
    {
        $text = $this->extractText($arguments);
        $direction = $this->determineDirection($arguments);
        
        if (empty($text)) {
            return "Error: No text provided for translation.";
        }
        
        return $this->performTranslation($text, $direction);
    }
    
    /**
     * Extract text to translate from various argument formats
     */
    private function extractText(array $arguments): string
    {
        // Direct text argument
        if (!empty($arguments['text'])) {
            return $arguments['text'];
        }
        
        // Extract from task description
        $task = $arguments['task'] ?? '';
        
        // Translation-specific patterns
        $patterns = [
            '/translate\s+["\']([^"\']+)["\'](?:\s+to\s+\w+)?/i',
            '/translate\s+(?:the\s+)?(?:text\s+)?["\']?([^"\']+?)["\']?(?:\s+to\s+\w+)?$/i',
            '/["\']([^"\']+)["\'].*translate/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $task, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // If no pattern matches, assume the whole task is the text to translate
        $cleanTask = preg_replace('/^(?:translate|to spanish|to english)\s*/i', '', $task);
        return trim($cleanTask);
    }
    
    /**
     * Determine translation direction from arguments and context
     */
    private function determineDirection(array $arguments): string
    {
        // Direct direction argument
        if (!empty($arguments['direction'])) {
            return $arguments['direction'];
        }
        
        // Check task for direction indicators
        $task = strtolower($arguments['task'] ?? '');
        
        if (strpos($task, 'to english') !== false || strpos($task, 'english') !== false) {
            return 'to_english';
        }
        
        if (strpos($task, 'to spanish') !== false || strpos($task, 'spanish') !== false) {
            return 'to_spanish';
        }
        
        // Try to detect language of input text
        $text = $arguments['text'] ?? '';
        if ($this->isSpanish($text)) {
            return 'to_english';
        }
        
        // Default to Spanish translation
        return 'to_spanish';
    }
    
    /**
     * Simple Spanish language detection
     */
    private function isSpanish(string $text): bool
    {
        $spanishWords = ['el', 'la', 'es', 'de', 'que', 'en', 'un', 'con', 'por', 'está', 'hace', 'tiempo'];
        $text = strtolower($text);
        
        foreach ($spanishWords as $word) {
            if (strpos($text, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Perform the actual translation
     */
    private function performTranslation(string $text, string $direction): string
    {
        // Simple translation simulation with common phrases
        $translations = [
            'hello' => 'hola',
            'goodbye' => 'adiós',
            'thank you' => 'gracias',
            'please' => 'por favor',
            'how are you' => '¿cómo estás?',
            'good morning' => 'buenos días',
            'good night' => 'buenas noches',
            'yes' => 'sí',
            'no' => 'no',
            'water' => 'agua',
            'food' => 'comida',
            'house' => 'casa',
            'car' => 'coche',
            'beautiful' => 'hermoso',
            'love' => 'amor',
            // Reverse mappings
            'hola' => 'hello',
            'adiós' => 'goodbye',
            'gracias' => 'thank you',
            'por favor' => 'please',
            '¿cómo estás?' => 'how are you?',
            'buenos días' => 'good morning',
            'buenas noches' => 'good night',
            'sí' => 'yes',
            'agua' => 'water',
            'comida' => 'food',
            'casa' => 'house',
            'coche' => 'car',
            'hermoso' => 'beautiful',
            'amor' => 'love'
        ];
        
        $lowerText = strtolower($text);
        
        // Check for direct translations
        foreach ($translations as $from => $to) {
            if ($lowerText === $from || str_contains($lowerText, $from)) {
                $directionText = $direction === 'to_english' ? 'English' : 'Spanish';
                return "Translation to $directionText: '$text' → '$to'";
            }
        }
        
        // Default response for unknown phrases
        $directionText = $direction === 'to_english' ? 'English' : 'Spanish';
        return "Translation service: I can help translate '$text' to $directionText. (This is a simplified demo translation.)";
    }
}

/**
 * Search Tool - Handles search operations
 */
class SearchTool extends Tool
{
    public function __construct()
    {
        $this->name = 'search';
        $this->description = 'Search for information on a topic';
        $this->parameters = [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
                'required' => true
            ]
        ];
    }
    
    /**
     * Get OpenAI tool definition for this tool
     */
    public function getOpenAIDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search_agent',
                'description' => 'Search for information on any topic',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query or topic to search for'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $query = $arguments['query'] ?? $arguments['task'] ?? '';
        
        if (empty($query)) {
            return "Error: No search query provided.";
        }
        
        // Remove common search prefixes
        $query = preg_replace('/^(?:search for|find|look up)\s*/i', '', $query);
        
        return "Here are some simulated search results for: '$query'\n" .
               "1. Wikipedia article: About $query\n" .
               "2. Latest news on $query\n" .
               "3. Academic papers related to $query";
    }
}