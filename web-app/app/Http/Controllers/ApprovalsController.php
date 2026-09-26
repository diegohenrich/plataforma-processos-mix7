<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\DemandReviewLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->role === UserRole::Client, 403);

        $links = DemandReviewLink::query()
            ->where('organization_id', $user->organization_id)
            ->whereHas('demand', function (Builder $query) use ($user): void {
                $query->where('organization_id', $user->organization_id)
                    ->when($user->role === UserRole::Professional, function (Builder $query) use ($user): void {
                        $query->where(function (Builder $visible) use ($user): void {
                            $visible->where('created_by', $user->id)
                                ->orWhereHas('tasks', fn (Builder $tasks) => $tasks->where('assigned_to', $user->id));
                        });
                    });
            })
            ->with([
                'demand:id,organization_id,title,status,created_by',
                'creator:id,name',
                'responses' => fn ($query) => $query->latest('created_at')->limit(3),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (DemandReviewLink $link): DemandReviewLink {
                $decision = $link->responses->first(fn ($response): bool => in_array($response->type, ['approved', 'changes_requested'], true));

                if ($decision) {
                    $link->setAttribute('queue_state', $decision->type);
                    $link->setAttribute('queue_label', $decision->type === 'approved' ? 'Aprovado pelo cliente' : 'Ajustes solicitados');
                } elseif ($link->revoked_at !== null) {
                    $link->setAttribute('queue_state', 'revoked');
                    $link->setAttribute('queue_label', 'Link revogado');
                } elseif ($link->expires_at->isPast()) {
                    $link->setAttribute('queue_state', 'expired');
                    $link->setAttribute('queue_label', 'Link expirado');
                } elseif ($link->demand->status === DemandStatus::ClientApproval) {
                    $link->setAttribute('queue_state', 'awaiting');
                    $link->setAttribute('queue_label', 'Aguardando cliente');
                } else {
                    $link->setAttribute('queue_state', 'closed');
                    $link->setAttribute('queue_label', 'Fora da etapa de aprovação');
                }

                return $link;
            });

        return view('approvals.index', compact('links'));
    }
}
