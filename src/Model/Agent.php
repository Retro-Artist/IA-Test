<?php
/**
 * Agent implementation for Model Context Protocol
 * 
 * This implements the concept of an agent as described in the OpenAI guide
 * using the ModelContextProtocol as the foundation.
 */

declare(strict_types=1);

require_once 'ModelContextProtocol.php';

/**
 * Agent class
 * 
 * Extends ModelContextProtocol with workflow execution capabilities
 */
class Agent extends ModelContextProtocol
{
    private string $name;
    private array $conversationHistory = [];
    private int $maxSteps = 10;
    
    /**
     * Create a new Agent
     * 
     * @param string $name The name of the agent
     * @param array $config Configuration for the agent
     */
    public function __construct(string $name, array $config = [])
    {
        parent::__construct($config);
        $this->name = $name;
        
        // Add the agent name to its instructions
        $this->addInstruction("You are $name.");
    }
    
    /**
     * Run the agent in a loop until completion
     * 
     * This implements the "run" concept from the OpenAI guide, where an agent
     * operates in a loop until an exit condition is reached.
     * 
     * @param string $userInput The initial user input
     * @return string The final response after the workflow is complete
     */
    public function execute(string $userInput): string
    {
        // Add the user input to the conversation history
        $this->conversationHistory[] = ['role' => 'user', 'content' => $userInput];
        
        $step = 0;
        $lastResponse = '';
        $isComplete = false;
        
        // Loop until the workflow is complete or we reach the maximum number of steps
        while (!$isComplete && $step < $this->maxSteps) {
            $step++;
            
            // Get the response from the model
            $response = parent::run($userInput);
            
            // Add the response to the conversation history
            $this->conversationHistory[] = ['role' => 'assistant', 'content' => $response];
            
            // Check if the workflow is complete
            // In a real implementation, this would be more sophisticated
            if ($this->isWorkflowComplete($response)) {
                $isComplete = true;
            }
            
            // If not complete, we'd need to update the user input for the next step
            // In a real implementation, this might involve extracting tool results
            $userInput = $this->prepareNextInput($response);
            
            $lastResponse = $response;
        }
        
        // If we reached the maximum number of steps without completing, add a note
        if (!$isComplete) {
            $lastResponse .= "\n\n(Reached maximum number of steps without completing the workflow)";
        }
        
        return $lastResponse;
    }
    
    /**
     * Set the maximum number of steps the agent can take
     * 
     * @param int $steps The maximum number of steps
     * @return self
     */
    public function setMaxSteps(int $steps): self
    {
        $this->maxSteps = $steps;
        return $this;
    }
    
    /**
     * Check if the workflow is complete
     * 
     * In a real implementation, this would be more sophisticated,
     * possibly looking for specific outputs or states.
     * 
     * @param string $response The current response
     * @return bool Whether the workflow is complete
     */
    private function isWorkflowComplete(string $response): bool
    {
        // For this simple example, if the response doesn't contain a tool call
        // indicator, we'll consider the workflow complete
        return !str_contains($response, 'Executed tool calls:');
    }
    
    /**
     * Prepare the input for the next step
     * 
     * @param string $response The current response
     * @return string The prepared input for the next step
     */
    private function prepareNextInput(string $response): string
    {
        // In a real implementation, this would extract tool results and format them
        // For this simple example, we'll just pass a follow-up instruction
        if (str_contains($response, 'Executed tool calls:')) {
            return "Continue with the information from the tools.";
        }
        
        return "Continue the workflow.";
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
     * Get the conversation history
     * 
     * @return array The conversation history
     */
    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }
}