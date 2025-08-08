<?php

namespace App\Services\Import\Actions;

interface ImportMiddleware
{
    /**
     * Handle the action context through middleware.
     *
     * @param ActionContext $context
     * @param \Closure $next
     * @return ActionResult
     */
    public function handle(ActionContext $context, \Closure $next): ActionResult;
}