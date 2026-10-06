<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps admin and staff apart. A logged-in user whose role doesn't match the
 * one required for this route group is sent back to their own home page
 * instead of a 403 — simpler for a two-role app like this.
 *
 * Registered as the 'role' alias in bootstrap/app.php. Used like:
 *   ->middleware(['auth', 'role:admin'])
 *   ->middleware(['auth', 'role:staff'])
 *
 * The ':admin' / ':staff' part after the colon arrives in $role below —
 * that's how a single middleware class can guard two different route groups
 * with two different required roles, instead of needing one class per role.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('authentication.login');
        }

        if ($user->role !== $role) {
            return redirect()->route($user->role === 'admin' ? 'admin.dashboard' : 'staff.orders');
        }

        return $next($request);
    }
}