<?php
/**
 * Agent class following OpenAI's pattern
 * 
 * This implements handoff-based multi-agent system similar to OpenAI's Python SDK
 */

declare(strict_types=1);

require_once 'ModelContextProtocol.php';
require_once 'Tool.php';

/**
 * Agent class following OpenAI's pattern
 */
class Agent extends ModelContextProtocol
{
    private string $name;
    private string $instructions;
    private array $handoffs = [];
    
    /**
     * Create a new Agent()
     * 
     * @param string $name The name of the agent
     * @param string $instructions The instructions for the agent
     * @param array $config Configuration for the agent (API keys, etc.)
     * @param array $handoffs Array of other agents this agent can handoff to
     */
    public function __construct(string $name, string $instructions, array $config = [], array $handoffs = [])
    {
        parent::__construct($config);
        $this->name = $name;
        $this->instructions = $instructions;
        $this->handoffs = $handoffs;
        
        // Add agent identity and instructions
        $this->addInstruction("You are {$this->name}.");
        $this->addInstruction($this->instructions);
        
        // Add handoff instructions if handoffs are available
        if (!empty($this->handoffs)) {
            $handoffNames = array_map(fn($agent) => $agent->getName(), $this->handoffs);
            $this->addInstruction("You can handoff to these agents: " . implode(', ', $handoffNames));
            $this->addInstruction("Use handoffs when the user's request is better suited for a specialist agent.");
            
            // Create handoff tools
            foreach ($this->handoffs as $agent) {
                $this->addTool(new HandoffTool($agent,));
            }
        }
    }
    
    /**
     * Get the agent's name
     * 
     * @return string The agent's name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the agent's instructions
     * 
     * @return string The agent's instructions
     */
    public function getAgentInstructions(): string
    {
        return $this->instructions;
    }
    
    /**
     * Execute the agent (wrapper around parent run method)
     * 
     * @param string $input The user input to process
     * @return string The agent's response
     */
    public function execute(string $input): string
    {
        return $this->run($input);
    }
}