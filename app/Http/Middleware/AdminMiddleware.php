<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error' => 'Unauthenticated'
            ], 401);
        }
        
        // Check if the authenticated user is an Admin model instance
        if (!$user instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required',
                'error' => 'Insufficient permissions'
            ], 403);
        }
        
        // Check if admin is active
        if (!$user->canAccessDashboard()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not active',
                'error' => 'Account inactive'
            ], 403);
        }
        
        return $next($request);
    }
}
