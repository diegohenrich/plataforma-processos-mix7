<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TeamInvitation;
use App\Models\User;
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

        return view('team.index', compact('professionals', 'clients', 'invitations'));
    }
}
