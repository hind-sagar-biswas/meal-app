<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request and ensure idempotent execution when key is supplied.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');

        if (! $idempotencyKey || ! $request->user()) {
            return $next($request);
        }

        $method = $request->method();
        $path = $request->path();
        $cacheKey = "idempotency:{$request->user()->id}:{$method}:{$path}:{$idempotencyKey}";

        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse) {
            return response()->json($cachedResponse['data'], $cachedResponse['status'])->header('X-Idempotent-Replay', 'true');
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);

            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => $content,
            ], now()->addHours(24));
        }

        return $response;
    }
}
