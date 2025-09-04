<?php

namespace App\Actions\Images\Manager;

use Illuminate\Database\Eloquent\Model;

/**
 * 🎯 IMAGE ACTION INTERFACE
 *
 * Base contract for all image actions in the system
 * Provides consistent structure and fluent API foundation
 */
interface ImageActionInterface
{
    /**
     * 🎯 Execute the action
     *
     * @param mixed ...$parameters
     * @return mixed
     */
    public function execute(...$parameters);

    /**
     * 🔄 Make action fluent (return self for chaining)
     *
     * @return static
     */
    public function fluent(): static;

    /**
     * ✅ Validate the action can be performed
     *
     * @param mixed ...$parameters
     * @return bool
     */
    public function canExecute(...$parameters): bool;

    /**
     * 📊 Get action result/status
     *
     * @return mixed
     */
    public function getResult();
}