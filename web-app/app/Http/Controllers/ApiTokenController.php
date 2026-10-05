<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('managePersonalApiTokens', $request->user());

        $tokens = $request->user()->tokens()->latest()->limit(100)->get();

        return view('integrations.tokens', compact('tokens'));
    }

    public function store(Request $request): Response|RedirectResponse
    {
        Gate::authorize('managePersonalApiTokens', $request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'expires_in_days' => ['required', 'integer', 'in:7,30,90'],
            'current_password' => ['required', 'current_password'],
        ]);

        $activeTokens = $request->user()->tokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        if ($activeTokens >= 10) {
            return redirect()->route('api-tokens.index')->withErrors(['name' => 'Revogue um token ativo antes de criar outro. O limite é 10 por conta.'])->withInput($request->only('name', 'expires_in_days'));
        }

        $token = $request->user()->createToken(trim($data['name']), ['api'], now()->addDays((int) $data['expires_in_days']));

        return response()
            ->view('integrations.tokens-created', ['token' => $token->plainTextToken, 'tokenName' => trim($data['name']), 'expiresAt' => $token->accessToken->expires_at])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        Gate::authorize('managePersonalApiTokens', $request->user());
        $deleted = $request->user()->tokens()->whereKey($token)->delete();
        abort_unless($deleted === 1, 404);

        return redirect()->route('api-tokens.index')->with('success', 'Token revogado. A integração não poderá mais usar este token.');
    }
}
