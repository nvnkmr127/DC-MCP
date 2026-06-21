<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \App\Http\Middleware\TenantIsolationMiddleware::class,
        ]);

        $middleware->alias([
            'webhook.signature' => \App\Http\Middleware\VerifyWebhookSignature::class,
        ]);

        // Exempt auth API routes and webhook endpoints from CSRF verification.
        // These are registered in web.php with explicit 'api' middleware so they
        // don't need a CSRF token, but the outer 'web' group still runs VerifyCsrfToken.
        $middleware->validateCsrfTokens(except: [
            'api/v1/auth/login',
            'api/v1/auth/register',
            'webhooks/mcp/*',
        ]);

        // Attach a correlation ID to every request for log tracing
        $middleware->append(\App\Http\Middleware\RequestId::class);

        // Track API endpoint hit rates in Pulse
        $middleware->append(\App\Http\Middleware\RecordEndpointHitRate::class);

        // Register Inertia HandleInertiaRequests for web routes
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\TenantIsolationMiddleware::class,
            \App\Http\Middleware\EnsureOrganizationIsOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);

        $exceptions->context(function (\Throwable $e) {
            $baseRunbookUrl = 'https://docs.mycompany.com/runbooks/';

            // Map common exceptions to specific runbooks
            $runbookMapping = [
                \Illuminate\Database\QueryException::class => 'database-query-failures',
                \GuzzleHttp\Exception\RequestException::class => 'third-party-api-failures',
                \Illuminate\Auth\AuthenticationException::class => 'auth-failures',
                \RedisException::class => 'redis-connection-failures',
                \App\Modules\MCP\Exceptions\McpSyncException::class => 'mcp-sync-failures',
            ];

            $runbookLink = $baseRunbookUrl . 'general-troubleshooting';
            foreach ($runbookMapping as $exceptionClass => $slug) {
                if ($e instanceof $exceptionClass) {
                    $runbookLink = $baseRunbookUrl . $slug;
                    break;
                }
            }

            return [
                'runbook_url' => $runbookLink,
            ];
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            Log::warning('Unauthenticated request', [
                'path'   => $request->path(),
                'method' => $request->method(),
                'ip'     => $request->ip(),
            ]);
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            Log::warning('Authorization exception', [
                'user_id' => $request->user()?->id,
                'path'    => $request->path(),
                'method'  => $request->method(),
                'ip'      => $request->ip(),
            ]);
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return \Inertia\Inertia::render('Errors/403', [
                'message' => $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : null,
            ])->toResponse($request)->setStatusCode(403);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });
    })->create();
