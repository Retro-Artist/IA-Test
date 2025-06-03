<?php
/**
 * Enhanced Agent class for OpenAI Tool Calls Multi-Agent System
 * 
 * This implements the proper agent pattern following OpenAI's recommendations
 * with clean separation between manager and worker agents.
 */

declare(strict_types=1);

require_once 'SystemAPI.php';
require_once 'Tool.php';

/**
 * Enhanced Agent class with tool calls support
 */
class Agent extends SystemAPI
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
     * Execute the agent with a specific task
     * 
     * @param string $task The task for this agent to perform
     * @return string The agent's response
     */
    public function execute(string $task): string
    {
        // For worker agents with tools, execute the tool directly
        if (!empty($this->tools)) {
            $tool = $this->tools[0]; // Use the first tool (agents are specialized)
            try {
                // Parse task for tool execution
                $arguments = $this->parseTaskForTool($task);
                return $tool->execute($arguments);
            } catch (Exception $e) {
                return "Error executing {$this->name}: " . $e->getMessage();
            }
        }
        
        // For manager agents or agents without tools, use LLM
        return $this->run($task);
    }
    
    /**
     * Parse task string into tool arguments
     * 
     * @param string $task The task description
     * @return array Arguments for the tool
     */
    private function parseTaskForTool(string $task): array
    {
        // Simple parsing logic - can be enhanced based on tool requirements
        if (stripos($this->name, 'weather') !== false) {
            // Weather agent - extract location
            $location = $this->extractLocation($task);
            return ['location' => $location ?: 'New York'];
        }
        
        if (stripos($this->name, 'spanish') !== false || stripos($this->name, 'translate') !== false) {
            // Translation agent - extract text to translate
            return ['text' => $task, 'task' => 'translate'];
        }
        
        if (stripos($this->name, 'math') !== false) {
            // Math agent - extract mathematical expression
            return $this->extractMathOperation($task);
        }
        
        // Default: pass task as general input
        return ['input' => $task];
    }
    
    /**
     * Extract location from task description
     * 
     * @param string $task The task description
     * @return string|null The extracted location
     */
    private function extractLocation(string $task): ?string
    {
        // Simple location extraction patterns
        $patterns = [
            '/weather\s+in\s+([^,.\n]+)/i',
            '/weather\s+for\s+([^,.\n]+)/i',
            '/in\s+([A-Za-z\s]+)$/i',
            '/for\s+([A-Za-z\s]+)$/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $task, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Extract mathematical operation from task description
     * 
     * @param string $task The task description
     * @return array Arguments for math tool
     */
    private function extractMathOperation(string $task): array
    {
        // Look for basic arithmetic patterns
        if (preg_match('/(\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)/', $task, $matches)) {
            return ['operation' => 'add', 'numbers' => [(float)$matches[1], (float)$matches[2]]];
        }
        
        if (preg_match('/(\d+(?:\.\d+)?)\s*\-\s*(\d+(?:\.\d+)?)/', $task, $matches)) {
            return ['operation' => 'subtract', 'numbers' => [(float)$matches[1], (float)$matches[2]]];
        }
        
        if (preg_match('/(\d+(?:\.\d+)?)\s*[\*x×]\s*(\d+(?:\.\d+)?)/', $task, $matches)) {
            return ['operation' => 'multiply', 'numbers' => [(float)$matches[1], (float)$matches[2]]];
        }
        
        if (preg_match('/(\d+(?:\.\d+)?)\s*[\/÷]\s*(\d+(?:\.\d+)?)/', $task, $matches)) {
            return ['operation' => 'divide', 'numbers' => [(float)$matches[1], (float)$matches[2]]];
        }
        
        // Extract all numbers for general calculation
        if (preg_match_all('/\d+(?:\.\d+)?/', $task, $matches)) {
            $numbers = array_map('floatval', $matches[0]);
            if (count($numbers) >= 2) {
                return ['operation' => 'add', 'numbers' => $numbers];
            }
        }
        
        // Default: pass as general calculation request
        return ['expression' => $task];
    }
    
    /**
     * Check if this agent can handle a specific task
     * 
     * @param string $task The task description
     * @return bool Whether this agent can handle the task
     */
    public function canHandle(string $task): bool
    {
        $task = strtolower($task);
        $name = strtolower($this->name);
        $role = strtolower($this->role);
        
        // Check for keywords related to agent's specialty
        if (stripos($name, 'weather') !== false) {
            return preg_match('/\b(weather|temperature|forecast|rain|sunny|cloudy|storm)\b/', $task);
        }
        
        if (stripos($name, 'spanish') !== false || stripos($name, 'translate') !== false) {
            return preg_match('/\b(translate|spanish|english|idiom|grammar)\b/', $task);
        }
        
        if (stripos($name, 'math') !== false) {
            return preg_match('/\b(calculate|math|add|subtract|multiply|divide|\+|\-|\*|\/|\d+)\b/', $task);
        }
        
        // Manager agents can handle anything
        if (stripos($name, 'manager') !== false) {
            return true;
        }
        
        return false;
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
    
    /**
     * Get handoff agents
     * 
     * @return array The handoff agents
     */
    public function getHandoffs(): array
    {
        return $this->handoffs;
    }
    
    /**
     * Is this a manager agent?
     * 
     * @return bool True if this is a manager agent
     */
    public function isManager(): bool
    {
        return stripos($this->name, 'manager') !== false;
    }
    
    /**
     * Is this a worker agent?
     * 
     * @return bool True if this is a worker agent
     */
    public function isWorker(): bool
    {
        return !$this->isManager();
    }
    
    /**
     * Get agent capabilities summary
     * 
     * @return string Summary of what this agent can do
     */
    public function getCapabilities(): string
    {
        $capabilities = [$this->role];
        
        if (!empty($this->tools)) {
            $toolDescriptions = array_map(fn($tool) => $tool->getDescription(), $this->tools);
            $capabilities = array_merge($capabilities, $toolDescriptions);
        }
        
        return implode('. ', $capabilities);
    }
    
    /**
     * Execute with full context and error handling
     * 
     * @param string $task The task to execute
     * @param array $context Additional context information
     * @return array Result with success status and message
     */
    public function executeWithContext(string $task, array $context = []): array
    {
        try {
            // Add context to the agent if provided
            foreach ($context as $key => $value) {
                $this->addContext($key, $value);
            }
            
            $result = $this->execute($task);
            
            return [
                'success' => true,
                'result' => $result,
                'agent' => $this->name,
                'task' => $task
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'agent' => $this->name,
                'task' => $task
            ];
        }
    }
}