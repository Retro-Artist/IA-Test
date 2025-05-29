<?php
/**
 * System Manager class - Implements the Manager Pattern
 * 
 * A central "manager" agent coordinates multiple specialized agents via tool calls,
 * each handling a specific task or domain.
 */

require_once 'Agent.php';
require_once 'Guardrail.php';

class System
{
    private Agent $managerAgent;
    private array $specialistAgents = [];
    private array $instructions = [];
    private array $guardrails = [];
    
    /**
     * Create a new System manager
     * 
     * @param array $config Optional configuration override
     */
    public function __construct(array $config = [])
    {
        // Set default config for all agents if provided
        if (!empty($config)) {
            Agent::setDefaultConfig($config);
        }
        
        // Create the manager agent
        $this->managerAgent = new Agent(
            "Manager Agent",
            "You are a central coordinator that delegates tasks to specialized agents. " .
            "Analyze user requests and handoff to the most appropriate specialist agent. " .
            "Always use handoffs for specialized tasks rather than attempting them yourself."
        );
    }
    
    /**
     * Add specialist agents to the system
     * 
     * @param array $agents Array of specialist agents
     * @return self
     */
    public function addAgents(array $agents): self
    {
        $this->specialistAgents = array_merge($this->specialistAgents, $agents);
        
        // Update manager agent with handoffs
        $this->managerAgent->addHandoffs($this->specialistAgents);
        
        return $this;
    }
    
    /**
     * Add a single agent to the system
     * 
     * @param Agent $agent The agent to add
     * @return self
     */
    public function addAgent(array $agent): self
    {
        $this->specialistAgents[] = $agent;
        
        // Update manager agent with handoffs
        $this->managerAgent->addHandoffs($this->specialistAgents);
        
        return $this;
    }
    
    /**
     * Add system-wide instruction
     * 
     * @param string $instruction The instruction to add
     * @return self
     */
    public function addInstruction(string $instruction): self
    {
        $this->instructions[] = $instruction;
        $this->managerAgent->addInstruction($instruction);
        
        return $this;
    }
    
    /**
     * Add system-wide guardrail
     * 
     * @param Guardrail $guardrail The guardrail to add
     * @return self
     */
    public function addGuardrail(Guardrail $guardrail): self
    {
        $this->guardrails[] = $guardrail;
        $this->managerAgent->addGuardrail($guardrail);
        
        return $this;
    }
    
    /**
     * Execute user input through the manager agent
     * 
     * @param string $input User input to process
     * @return string The system response
     */
    public function execute(string $input): string
    {
        return $this->managerAgent->execute($input);
    }
    
    /**
     * Get all specialist agents
     * 
     * @return array Array of specialist agents
     */
    public function getAgents(): array
    {
        return $this->specialistAgents;
    }
    
    /**
     * Get the manager agent
     * 
     * @return Agent The manager agent
     */
    public function getManager(): Agent
    {
        return $this->managerAgent;
    }
}