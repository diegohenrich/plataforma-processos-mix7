<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\TaskTimerHeartbeat;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Conta desativada.'], 403)
                : redirect()->route('login');
        }

        if ($request->user()->role === UserRole::Professional) {
            app(TaskTimerHeartbeat::class)->closeStaleFor($request->user());
        }

        return $next($request);
    }
}
