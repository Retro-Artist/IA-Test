<?php
/**
 * Sample Tool implementations for Model Context Protocol
 */

declare(strict_types=1);

require_once 'Tool.php';

/**
 * Weather Tool
 * 
 * Simulates retrieving weather information for a location
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
    
    public function execute(array $arguments): string
    {
        $location = $arguments['location'] ?? 'Unknown';
        
        // In a real implementation, this would call a weather API
        // For demo purposes, we'll just return some mock data
        $weatherConditions = ['sunny', 'partly cloudy', 'cloudy', 'rainy', 'stormy', 'snowy'];
        $temperatures = [69, 420];
        
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