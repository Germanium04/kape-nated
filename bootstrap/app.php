<?php

// MERGE THESE INTO YOUR EXISTING bootstrap/app.php (Laravel 11/12 style).
// Keep anything you already have (e.g. withRouting); only the middleware and
// exception parts below are new or changed.

use App\Http\Middleware\NoCacheHeaders;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Every web page is sent with no-store headers (fixes Back-after-logout).
        $middleware->appendToGroup('web', NoCacheHeaders::class);

        // Where the `auth` middleware sends logged-out visitors.
        // Default is route('login'), which doesn't exist in this app.
        $middleware->redirectGuestsTo(fn () => route('authentication.login'));

        // Where the `guest` middleware sends logged-in users who open /, /signup, etc.
        // Default is '/dashboard', which doesn't exist in this app.
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->role === 'admin'
                ? route('admin.dashboard')
                : route('staff.orders');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // 419 = CSRF token / session expired (e.g. a form left open too long).
        // Show a friendly message instead of the raw "Page Expired" screen.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null; // let Laravel handle everything else normally
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please log in again.'], 419);
            }

            return redirect()->route('authentication.login')
                ->with('error', 'Your session expired. Please log in again.');
        });
    })
    ->create();