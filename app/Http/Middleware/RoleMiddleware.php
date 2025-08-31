<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 👑 ROLE MIDDLEWARE
 *
 * Protects routes based on user roles (admin, manager, user).
 *
 * Usage:
 * Route::middleware(['auth', 'role:admin'])->group(function () {});
 * Route::middleware(['auth', 'role:admin,manager'])->group(function () {});
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles  - Comma-separated list of allowed roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check if user is authenticated
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // If no roles specified, just require any role
        if (empty($roles)) {
            if (! $user->hasRole()) {
                return $this->handleUnauthorized($request);
            }

            return $next($request);
        }

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($user->hasSpecificRole($role)) {
                return $next($request);
            }
        }

        // Handle hierarchy - admin can access manager routes, manager can access user routes
        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($user->isManager() && in_array('user', $roles)) {
            return $next($request);
        }

        return $this->handleUnauthorized($request);
    }

    /**
     * Handle unauthorized access
     */
    private function handleUnauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Insufficient permissions. Required role not found.',
                'required_roles' => func_get_args()[1] ?? [],
                'user_role' => Auth::user()->role ?? 'none',
            ], 403);
        }

        // For web requests, redirect to dashboard with error
        return redirect()->route('dashboard')
            ->with('error', 'You do not have permission to access that resource.');
    }
}
