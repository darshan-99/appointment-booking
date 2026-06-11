<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ── Domain / business-rule errors thrown by services ────────────────
        $exceptions->render(function (\App\Exceptions\DomainException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data'    => null,
                    'errors'  => null,
                ], $e->getHttpStatus());
            }
        });

        // ── Model not found (findOrFail, route–model binding) ────────────────
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                \Illuminate\Support\Facades\Log::warning('Resource not found.', [
                    'model' => $e->getModel(),
                    'ids'   => $e->getIds(),
                    'url'   => $request->fullUrl(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data'    => null,
                    'errors'  => null,
                ], 404);
            }
        });

        // ── Validation errors (from $request->validate() inline calls) ───────
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'data'    => null,
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // ── Catch-all: unexpected server errors ──────────────────────────────
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                \Illuminate\Support\Facades\Log::error('Unhandled exception.', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                    'url'     => $request->fullUrl(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'An unexpected error occurred. Please try again later.',
                    'data'    => null,
                    'errors'  => null,
                ], 500);
            }
        });
    })->create();
