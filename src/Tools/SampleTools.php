<?php
/**
 * Sample Tool implementations for Model Context Protocol
 */

require_once __DIR__ . '/../Model/Tool.php';

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
    
    public function execute(array $arguments): string
    {
        $location = $arguments['location'] ?? 'Unknown';
        
        // In a real implementation, this would call a weather API
        // For demo purposes, we'll just return some mock data
        $weatherConditions = ['sunny', 'partly cloudy', 'cloudy', 'rainy', 'stormy', 'snowy'];
        $temperatures = [15, 22, 28, 5, 35, -2, 18, 25];
        
        $condition = $weatherConditions[array_rand($weatherConditions)];
        $temperature = $temperatures[array_rand($temperatures)];
        
        return "The weather in $location is currently $condition with a temperature of {$temperature}°C.";
    }
}

/**
 * Calculator Tool
 * 
 * Performs basic arithmetic operations
 */
class CalculatorTool extends Tool
{
    public function __construct()
    {
        $this->name = 'calculate';
        $this->description = 'Perform arithmetic calculations';
        $this->parameters = [
            'operation' => [
                'type' => 'string',
                'description' => 'The operation to perform: add, subtract, multiply, or divide',
                'required' => true
            ],
            'numbers' => [
                'type' => 'array',
                'description' => 'The numbers to operate on',
                'required' => true,
                'items' => [
                    'type' => 'number',
                    'description' => 'A numeric value'
                ]
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $operation = $arguments['operation'] ?? '';
        $numbers = $arguments['numbers'] ?? [];
        
        if (empty($numbers) || !is_array($numbers)) {
            return "Error: No numbers provided for calculation.";
        }
        
        // Convert all elements to numbers
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
 * Search Tool
 * 
 * Simulates a search operation
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
    
    public function execute(array $arguments): string
    {
        $query = $arguments['query'] ?? '';
        
        // In a real implementation, this would call a search API
        // For demo purposes, we'll just return mock data
        return "Here are some simulated search results for: '$query'\n" .
               "1. Wikipedia article: About $query\n" .
               "2. Latest news on $query\n" .
               "3. Academic papers related to $query";
    }
}


/**
 * Additional specialized tools for the multi-agent system
 * To be added to the existing SampleTools.php file
 */

/**
 * Translation Tool
 * 
 * Handles text translation between languages
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
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $text = $arguments['text'] ?? '';
        $task = $arguments['task'] ?? 'translate';
        
        if (empty($text)) {
            return "Error: No text provided for translation.";
        }
        
        // Simple translation simulation
        $translations = [
            'hello' => 'hola',
            'goodbye' => 'adiós',
            'thank you' => 'gracias',
            'please' => 'por favor',
            'how are you' => '¿cómo estás?',
            'good morning' => 'buenos días',
            'good night' => 'buenas noches',
            'hola' => 'hello',
            'adiós' => 'goodbye',
            'gracias' => 'thank you'
        ];
        
        $lowerText = strtolower($text);
        
        // Check for direct translations
        foreach ($translations as $from => $to) {
            if (str_contains($lowerText, $from)) {
                return "Translation: '{$text}' → '{$to}'";
            }
        }
        
        // Default response
        return "Translation service: I can help translate between English and Spanish. Text: '{$text}'";
    }
}

/**
 * Divide Tool
 * 
 * Performs division operations
 */
class DivideTool extends Tool
{
    public function __construct()
    {
        $this->name = 'divide';
        $this->description = 'Perform division operations';
        $this->parameters = [
            'dividend' => [
                'type' => 'number',
                'description' => 'The number to be divided',
                'required' => true
            ],
            'divisor' => [
                'type' => 'number',
                'description' => 'The number to divide by',
                'required' => true
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $dividend = $arguments['dividend'] ?? 0;
        $divisor = $arguments['divisor'] ?? 0;
        
        if ($divisor == 0) {
            return "Error: Cannot divide by zero.";
        }
        
        $result = $dividend / $divisor;
        return "{$dividend} ÷ {$divisor} = {$result}";
    }
}

/**
 * Multiply Tool
 * 
 * Performs multiplication operations
 */
class MultiplyTool extends Tool
{
    public function __construct()
    {
        $this->name = 'multiply';
        $this->description = 'Perform multiplication operations';
        $this->parameters = [
            'factor1' => [
                'type' => 'number',
                'description' => 'The first number to multiply',
                'required' => true
            ],
            'factor2' => [
                'type' => 'number',
                'description' => 'The second number to multiply',
                'required' => true
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $factor1 = $arguments['factor1'] ?? 0;
        $factor2 = $arguments['factor2'] ?? 0;
        
        $result = $factor1 * $factor2;
        return "{$factor1} × {$factor2} = {$result}";
    }
}

/**
 * Subtract Tool
 * 
 * Performs subtraction operations
 */
class SubtractTool extends Tool
{
    public function __construct()
    {
        $this->name = 'subtract';
        $this->description = 'Perform subtraction operations';
        $this->parameters = [
            'minuend' => [
                'type' => 'number',
                'description' => 'The number to subtract from',
                'required' => true
            ],
            'subtrahend' => [
                'type' => 'number',
                'description' => 'The number to subtract',
                'required' => true
            ]
        ];
    }
    
    public function execute(array $arguments): string
    {
        $minuend = $arguments['minuend'] ?? 0;
        $subtrahend = $arguments['subtrahend'] ?? 0;
        
        $result = $minuend - $subtrahend;
        return "{$minuend} - {$subtrahend} = {$result}";
    }
}