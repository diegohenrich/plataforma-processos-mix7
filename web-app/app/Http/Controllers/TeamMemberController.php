<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TeamInvitation;
use App\Models\TeamMemberEvent;
use App\Models\User;
use App\Services\TeamMemberAccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
