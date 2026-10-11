<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    private const VALID_ROLES = ['admin', 'staff'];

    // Accepts one or many roles: 'role:admin' or 'role:admin,staff'
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Session expired. Please log in again.'], 401)
                : redirect()->route('authentication.login');
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Logged in but not allowed here.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to do that.'], 403);
        }

        // Unknown role: log them out instead of redirecting in circles.
        if (! in_array($user->role, self::VALID_ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('authentication.login')
                ->with('error', 'Your account has no valid role. Please contact an administrator.');
        }

        // Was 'staff.dashboard', which doesn't exist in web.php (RouteNotFoundException).
        return redirect()
            ->route($user->role === 'admin' ? 'admin.dashboard' : 'staff.orders')
            ->with('error', "You don't have permission to open that page.");
    }
}