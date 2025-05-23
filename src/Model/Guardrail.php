<?php
/**
 * Guardrail class for Model Context Protocol
 * 
 * Guardrails help manage risk by enforcing policies and constraints on the agent's behavior.
 * They can validate user input, check tool parameters, or filter model output.
 */

declare(strict_types=1);

/**
 * Abstract Guardrail class
 */
abstract class Guardrail
{
    /**
     * Validate user input against the guardrail
     * 
     * @param string $input The user input to validate
     * @return array An array with 'valid' (bool) and 'message' (string) keys
     */
    abstract public function validateInput(string $input): array;
}

/**
 * Input Length Guardrail
 * 
 * Ensures user input doesn't exceed a maximum length
 */
class InputLengthGuardrail extends Guardrail
{
    private int $maxLength;
    private string $errorMessage;
    
    /**
     * Create a new InputLengthGuardrail
     * 
     * @param int $maxLength The maximum allowed length for user input
     * @param string $errorMessage The error message to return if the input is too long
     */
    public function __construct(int $maxLength, string $errorMessage = "Input is too long. Please keep it under {maxLength} characters.")
    {
        $this->maxLength = $maxLength;
        $this->errorMessage = $errorMessage;
    }
    
    /**
     * Validate that the input doesn't exceed the maximum length
     */
    public function validateInput(string $input): array
    {
        $length = mb_strlen($input);
        
        if ($length <= $this->maxLength) {
            return ['valid' => true, 'message' => ''];
        }
        
        $message = str_replace('{maxLength}', (string)$this->maxLength, $this->errorMessage);
        return ['valid' => false, 'message' => $message];
    }
}

/**
 * Regex Pattern Guardrail
 * 
 * Validates user input against a regex pattern
 */
class RegexGuardrail extends Guardrail
{
    private string $pattern;
    private string $errorMessage;
    private bool $matchIs = true;
    
    /**
     * Create a new RegexGuardrail
     * 
     * @param string $pattern The regex pattern to match against
     * @param string $errorMessage The error message to return if the input doesn't match
     * @param bool $matchIs Whether a match means valid (true) or invalid (false)
     */
    public function __construct(string $pattern, string $errorMessage = "Input format is invalid.", bool $matchIs = true)
    {
        $this->pattern = $pattern;
        $this->errorMessage = $errorMessage;
        $this->matchIs = $matchIs;
    }
    
    /**
     * Validate that the input matches or doesn't match the pattern
     */
    public function validateInput(string $input): array
    {
        $matches = preg_match($this->pattern, $input) === 1;
        
        // If matchIs is true, we want matches to be true for validation to pass
        // If matchIs is false, we want matches to be false for validation to pass
        $valid = $this->matchIs ? $matches : !$matches;
        
        if ($valid) {
            return ['valid' => true, 'message' => ''];
        }
        
        return ['valid' => false, 'message' => $this->errorMessage];
    }
}

/**
 * Keyword Blocklist Guardrail
 * 
 * Blocks inputs containing specific keywords
 */
class KeywordGuardrail extends Guardrail
{
    private array $keywords;
    private string $errorMessage;
    private bool $caseSensitive;
    
    /**
     * Create a new KeywordGuardrail
     * 
     * @param array $keywords Array of blocked keywords
     * @param string $errorMessage Error message when blocked keywords are found
     * @param bool $caseSensitive Whether matching should be case-sensitive
     */
    public function __construct(array $keywords, string $errorMessage = "Input contains inappropriate content.", bool $caseSensitive = false)
    {
        $this->keywords = $keywords;
        $this->errorMessage = $errorMessage;
        $this->caseSensitive = $caseSensitive;
    }
    
    /**
     * Validate that the input doesn't contain blocked keywords
     */
    public function validateInput(string $input): array
    {
        $checkInput = $this->caseSensitive ? $input : strtolower($input);
        
        foreach ($this->keywords as $keyword) {
            $checkKeyword = $this->caseSensitive ? $keyword : strtolower($keyword);
            
            if (str_contains($checkInput, $checkKeyword)) {
                return ['valid' => false, 'message' => $this->errorMessage];
            }
        }
        
        return ['valid' => true, 'message' => ''];
    }
}