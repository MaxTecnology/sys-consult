<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        // Ignorar rotas estáticas/infra para reduzir volume
        if ($this->isIgnorable($request)) {
            return $next($request);
        }

        $requestId = (string) Str::orderedUuid();
        $request->headers->set('X-Request-Id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        if (auth()->check()) {
            try {
                $route = $request->route();

                UserActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => $route?->getActionName(),
                    'route_name' => $route?->getName(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'path' => $request->path(),
                    'metadata' => [
                        'request_id' => $requestId,
                        'trace_id' => $requestId,
                        'url' => $request->fullUrl(),
                        'payload_keys' => array_keys($request->except([
                            'password',
                            'password_confirmation',
                            'pkcs12_cert_encrypted',
                            'pkcs12_pass_encrypted',
                            'token_api',
                            'chave_criptografia',
                        ])),
                        'response_status' => $response->getStatusCode(),
                    ],
                ]);
            } catch (\Throwable $e) {
                // Silencioso para não afetar a requisição principal
            }
        }

        return $response;
    }

    private function isIgnorable(Request $request): bool
    {
        $path = $request->path();

        // Ignorar assets e livewire/health
        $ignoredPrefixes = [
            'livewire',
            'filament/assets',
            'filament/livewire',
            'horizon/api',
        ];

        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
