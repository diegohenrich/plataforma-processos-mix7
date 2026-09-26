<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TeamMemberAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
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
