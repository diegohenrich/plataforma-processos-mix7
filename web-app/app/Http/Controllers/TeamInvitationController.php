<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamInvitationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([UserRole::Professional->value, UserRole::Client->value])],
        ]);

        $organizationId = $request->user()->organization_id;
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($data, $organizationId, $request, $token): TeamInvitation {
            TeamInvitation::query()
                ->where('organization_id', $organizationId)
                ->where('email', $data['email'])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return TeamInvitation::create([
                'organization_id' => $organizationId,
                'invited_by' => $request->user()->id,
                'name' => trim($data['name']),
                'email' => $data['email'],
                'role' => $data['role'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(72),
            ]);
        });

        Notification::route('mail', $invitation->email)->notify(new TeamInvitationNotification($invitation, $token));

        return redirect()->route('team.index')->with('success', 'Convite enviado. A pessoa criará a própria senha pelo link temporário.');
    }

    public function show(string $token): View
    {
        $invitation = $this->findValidInvitation($token);

        if (! $invitation) {
            return view('team-invitations.invalid');
        }

        return view('team-invitations.accept', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'max:200', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($token, $data): User {
            $invitation = TeamInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isPending() || User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'Este convite não está mais disponível. Peça à direção um novo link.']);
            }

            $user = User::create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => $data['password'],
                'organization_id' => $invitation->organization_id,
                'role' => $invitation->role,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Conta ativada. Você já pode acessar a plataforma.');
    }

    public function revoke(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);
        abort_unless($invitation->organization_id === $request->user()->organization_id, 404);

        if ($invitation->isPending()) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

        return redirect()->route('team.index')->with('success', 'Convite cancelado. O link não pode mais ser usado.');
    }

    private function findValidInvitation(string $token): ?TeamInvitation
    {
        $invitation = TeamInvitation::query()->where('token_hash', hash('sha256', $token))->first();

        return $invitation?->isPending() ? $invitation : null;
    }
}
