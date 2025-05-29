<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicChatRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'public-chat:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json([
                'message' => 'Too many requests. Please slow down.',
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 seconds decay

        return $next($request);
    }
}
