<?php
/**
 * Cleaned Agent class - Essential functions for multi-agent system
 */

declare(strict_types=1);

require_once 'SystemAPI.php';
require_once 'Tool.php';

/**
 * Agent class with core functionality only
 */
class Agent extends SystemAPI
{
    private static ?array $defaultConfig = null;
    private string $name;
    private string $role;
    private array $tools = [];
    
    /**
     * Set the default configuration for all agents
     */
    public static function setDefaultConfig(array $config): void
    {
        self::$defaultConfig = $config;
    }
    
    /**
     * Create a new Agent
     */
    public function __construct(string $name, string $role, Tool ...$tools)
    {
        // Use default config if available
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
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the agent's role/instructions
     */
    public function getRole(): string
    {
        return $this->role;
    }
    
    /**
     * Get the agent's tools
     */
    public function getAgentTools(): array
    {
        return $this->tools;
    }
    
    /**
     * Execute the agent with a specific task
     */
    public function execute(string $task): string
    {
        // For worker agents with tools, execute the tool directly
        if (!empty($this->tools)) {
            $tool = $this->tools[0]; // Use the first tool (agents are specialized)
            try {
                // Let the tool handle its own argument parsing
                return $tool->execute(['task' => $task]);
            } catch (Exception $e) {
                return "Error executing {$this->name}: " . $e->getMessage();
            }
        }
        
        // For manager agents or agents without tools, use LLM
        return $this->run($task);
    }
}