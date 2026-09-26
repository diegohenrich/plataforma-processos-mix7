<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamMemberAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $members = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('role', [UserRole::Professional->value, UserRole::Client->value])
            ->orderBy('role')->orderBy('name')->limit(200)->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return response()->json(['data' => $members->map(fn (User $member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role->value,
            'is_active' => $member->is_active,
            'created_at' => $member->created_at?->toISOString(),
        ])]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $invitations = TeamInvitation::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->latest()->limit(100)->get(['id', 'name', 'email', 'role', 'expires_at', 'created_at']);

        return response()->json(['data' => $invitations->map(fn (TeamInvitation $invitation) => [
            'id' => $invitation->id,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'created_at' => $invitation->created_at?->toISOString(),
        ])])->header('Cache-Control', 'private, no-store');
    }

    public function toggleAccess(Request $request, User $member, TeamMemberAccessManager $accessManager): JsonResponse
    {
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        $this->authorize('updateAccess', $member);
        $member = $accessManager->toggle($member, $request->user());

        return response()->json(['data' => [
            'id' => $member->id,
            'is_active' => $member->is_active,
            'access' => $member->is_active ? 'restored' : 'revoked',
        ]]);
    }
}
