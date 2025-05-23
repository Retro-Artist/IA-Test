<?php
/**
 * Tool class for Model Context Protocol
 * 
 * Tools extend the agent's capabilities by allowing it to perform actions.
 * Each tool has a name, description, parameters, and an execute function.
 */

declare(strict_types=1);

/**
 * Abstract Tool class
 */
abstract class Tool
{
    protected string $name;
    protected string $description;
    protected array $parameters = [];
    
    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the tool description
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * Get the tool definition for use in instructions
     */
    public function getDefinition(): string
    {
        $parameterDesc = [];
        foreach ($this->parameters as $name => $info) {
            $parameterDesc[] = "- $name: {$info['description']} ({$info['type']})";
        }
        
        $paramStr = empty($parameterDesc) ? "No parameters" : implode("\n", $parameterDesc);
        
        return "Tool: {$this->name}\nDescription: {$this->description}\nParameters:\n$paramStr";
    }
    
    /**
     * Get the tool format for the OpenAI API
     */
    public function getFormat(): array
    {
        $properties = [];
        $required = [];
        
        foreach ($this->parameters as $name => $info) {
            $propertyType = $this->mapType($info['type']);
            $properties[$name] = [
                'type' => $propertyType,
                'description' => $info['description']
            ];
            
            // Add items property for arrays
            if ($propertyType === 'array' && isset($info['items'])) {
                $properties[$name]['items'] = $info['items'];
            }
            
            if ($info['required'] ?? false) {
                $required[] = $name;
            }
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required
                ]
            ]
        ];
    }
    
    /**
     * Map PHP types to JSON Schema types
     */
    private function mapType(string $type): string
    {
        return match ($type) {
            'int', 'integer', 'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string'
        };
    }
    
    /**
     * Execute the tool with the given arguments
     * 
     * @param array $arguments The arguments to pass to the tool
     * @return string The result of the tool execution
     */
    abstract public function execute(array $arguments): string;
}