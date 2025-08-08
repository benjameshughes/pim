<?php

namespace App\Actions\Base;

/**
 * Base Action Class
 * 
 * Abstract base class for all action implementations following the Action Pattern.
 * Actions encapsulate single-responsibility business logic operations.
 * 
 * @package App\Actions\Base
 */
abstract class BaseAction
{
    /**
     * Execute the action with provided parameters
     * 
     * @param mixed ...$params Variable parameters for action execution
     * @return mixed The result of the action execution
     */
    abstract public function execute(...$params);
    
    /**
     * Validate parameters before execution (optional override)
     * 
     * @param array $params Parameters to validate
     * @return bool True if validation passes
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validate(array $params): bool
    {
        return true;
    }
    
    /**
     * Handle any cleanup after action execution (optional override)
     * 
     * @param mixed $result The result from execute()
     * @return void
     */
    protected function cleanup($result): void
    {
        // Default: no cleanup needed
    }
    
    /**
     * Execute action with built-in validation and cleanup
     * 
     * @param mixed ...$params Variable parameters for action execution
     * @return mixed The result of the action execution
     */
    public function handle(...$params)
    {
        $this->validate($params);
        $result = $this->execute(...$params);
        $this->cleanup($result);
        
        return $result;
    }
}