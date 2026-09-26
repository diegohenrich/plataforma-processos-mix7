<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null && ! $request->user()?->tokenCan('api')) {
            throw new MissingAbilityException('api');
        }

        return $next($request);
    }
}
