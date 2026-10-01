<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            auth('sanctum')->user()?->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account is '.$user->status.'. Contact HR for access.',
            ], 403);
        }

        return $next($request);
    }
}
