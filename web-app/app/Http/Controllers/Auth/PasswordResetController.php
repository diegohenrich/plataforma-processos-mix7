<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.password-request');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim($data['email']));

        // Keep the response identical for unknown and inactive accounts.
        if (User::query()->where('email', $email)->where('is_active', true)->exists()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Se existir uma conta ativa com esse e-mail, enviaremos um link para redefinir a senha.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:200', 'confirmed'],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user?->is_active) {
            throw ValidationException::withMessages(['email' => 'Não foi possível redefinir a senha desta conta.']);
        }

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'O link expirou ou já foi usado. Solicite outro link para redefinir a senha.']);
        }

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre com a nova senha.');
    }
}
