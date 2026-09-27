<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TeamInvitation;
use App\Models\TeamMemberEvent;
use App\Models\User;
use App\Services\TeamMemberAccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $professionals = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Professional->value)
            ->orderBy('name')
            ->paginate(20);

        $clients = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Client->value)
            ->orderBy('name')
            ->paginate(20, ['*'], 'clients_page');

        $invitations = TeamInvitation::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();
        $accessEvents = TeamMemberEvent::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['member:id,name', 'actor:id,name'])
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('team.index', compact('professionals', 'clients', 'invitations', 'accessEvents'));
    }

    public function updateAccess(Request $request, User $member, TeamMemberAccessManager $accessManager): RedirectResponse
    {
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        $this->authorize('updateAccess', $member);

        $member = $accessManager->toggle($member, $request->user());

        return back()->with('success', $member->is_active
            ? 'Acesso restaurado. A pessoa poderá entrar novamente.'
            : 'Acesso desativado. Sessões e tokens foram encerrados; tarefas abertas continuam atribuídas à pessoa.');
    }

    public function updateSpecialties(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        $this->authorize('updateAccess', $member);

        $data = $request->validate([
            'specialties' => ['nullable', 'string', 'max:600'],
        ]);

        $rawSpecialties = collect(preg_split('/[,;\n]+/u', (string) ($data['specialties'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $specialty): string => trim(preg_replace('/\s+/u', ' ', $specialty)))
            ->filter()
            ->values()
            ->all();

        if (count($rawSpecialties) > 12) {
            return back()->withErrors(['specialties' => 'Informe até 12 especialidades.'])->withInput();
        }
        if (collect($rawSpecialties)->contains(fn (string $specialty): bool => mb_strlen($specialty) > 60)) {
            return back()->withErrors(['specialties' => 'Cada especialidade pode ter até 60 caracteres.'])->withInput();
        }

        $specialties = collect($rawSpecialties)
            ->unique(fn (string $specialty): string => Str::lower(Str::ascii($specialty)))
            ->values()
            ->all();

        DB::transaction(function () use ($member, $request, $specialties): void {
            $member->update(['specialties' => $specialties]);
            TeamMemberEvent::create([
                'organization_id' => $member->organization_id,
                'member_id' => $member->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'specialties_updated',
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Especialidades profissionais atualizadas. Elas servem apenas como referência para sugestões revisáveis.');
    }
}
