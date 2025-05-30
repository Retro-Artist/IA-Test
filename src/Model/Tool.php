<?php
/**
 * Tool class for Model Context Protocol
 * 
 * Tools extend the agent's capabilities by allowing it to perform actions.
 * Each tool has a name, description, parameters, and an execute function.
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

/**
 * HandoffTool - Implements handoff functionality for multi-agent SystemAPIs
 * 
 * This follows OpenAI's handoff pattern where agents can transfer control
 * to other specialized agents.
 */
class HandoffTool extends Tool
{
    private Agent $targetAgent;
    
    /**
     * Create a new HandoffTool
     * 
     * @param Agent $targetAgent The agent to handoff to
     */
    public function __construct(Agent $targetAgent)
    {
        $this->targetAgent = $targetAgent;
        $this->name = 'transfer_to_' . strtolower(str_replace([' ', 'Agent'], ['_', ''], $targetAgent->getName()));
        $this->description = "Transfer the conversation to {$targetAgent->getName()}: {$targetAgent->getRole()}";
        $this->parameters = [
            'message' => [
                'type' => 'string',
                'description' => 'The message or task to transfer to the agent',
                'required' => true
            ]
        ];
    }
    
    /**
     * Execute the handoff
     * 
     * Transfers control to the target agent with the given message
     * 
     * @param array $arguments The arguments containing the message
     * @return string The result from the target agent
     */
    public function execute(array $arguments): string
    {
        $message = $arguments['message'] ?? '';
        
        if (empty($message)) {
            return "Error: No message provided for handoff to {$this->targetAgent->getName()}.";
        }
        
        try {
            // Execute the handoff - transfer control to the target agent
            echo "🔄 Transferring to {$this->targetAgent->getName()}...\n";
            return $this->targetAgent->execute($message);
        } catch (Exception $e) {
            return "Error during handoff to {$this->targetAgent->getName()}: " . $e->getMessage();
        }
    }
}