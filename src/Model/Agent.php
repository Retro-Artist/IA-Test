<?php
/**
 * Enhanced Agent class following OpenAI's pattern with default configuration
 * 
 * This implements handoff-based multi-agent system similar to OpenAI's Python SDK
 * with simplified usage and automatic configuration loading.
 */

declare(strict_types=1);

require_once 'ModelContextProtocol.php';
require_once 'Tool.php';

/**
 * Enhanced Agent class with default configuration and flexible tool support
 */
class Agent extends ModelContextProtocol
{
    private static ?array $defaultConfig = null;
    private string $name;
    private string $role;
    private array $tools = [];
    private array $handoffs = [];
    
    /**
     * Set the default configuration for all agents
     * 
     * @param array $config The default configuration to use
     */
    public static function setDefaultConfig(array $config): void
    {
        self::$defaultConfig = $config;
    }
    
    /**
     * Create a new Agent with flexible tool support
     * 
     * @param string $name The name of the agent
     * @param string $role The role/instructions for the agent
     * @param Tool ...$tools Variable number of tools for the agent
     */
    public function __construct(string $name, string $role, Tool ...$tools)
    {
        // Use default config if available, otherwise empty config
        $config = self::$defaultConfig ?? [];
        
        parent::__construct($config);
        $this->name = $name;
        $this->role = $role;
        
        // Add agent identity and role
        $this->addInstruction("You are {$this->name}.");
        $this->addInstruction($this->role);
        
        // Add all provided tools
        foreach ($tools as $tool) {
            $this->addTool($tool);
            $this->tools[] = $tool;
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
     * Get the agent's role/instructions
     * 
     * @return string The agent's role
     */
    public function getRole(): string
    {
        return $this->role;
    }
    
    /**
     * Get the agent's tools
     * 
     * @return array The agent's tools
     */
    public function getAgentTools(): array
    {
        return $this->tools;
    }
    
    /**
     * Add handoffs to this agent (for creating manager agents)
     * 
     * @param array $agents Array of agents this agent can handoff to
     * @return self
     */
    public function addHandoffs(array $agents): self
    {
        $this->handoffs = $agents;
        
        if (!empty($this->handoffs)) {
            $handoffNames = array_map(fn($agent) => $agent->getName(), $this->handoffs);
            $this->addInstruction("You can handoff to these specialist agents: " . implode(', ', $handoffNames));
            $this->addInstruction("Use handoffs when the user's request is better suited for a specialist agent.");
            
            // Create handoff tools
            foreach ($this->handoffs as $agent) {
                $this->addTool(new HandoffTool($agent));
            }
        }
        
        return $this;
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
    
    /**
     * Override addContext to ensure it's properly handled
     * 
     * @param string $key The context key
     * @param mixed $value The context value
     * @return self
     */
    public function addContext(string $key, $value): self
    {
        parent::addContext($key, $value);
        return $this;
    }
}