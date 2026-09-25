<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandReviewLink;
use App\Models\DemandReviewResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DemandReviewController extends Controller
{
    public function store(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($demand->status === DemandStatus::ClientApproval, 409, 'A demanda precisa estar em aprovação do cliente.');

        $data = $request->validate([
            'material_url' => ['required', 'url:http,https', 'max:2048'],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $plainToken = Str::random(64);
        $link = DB::transaction(function () use ($demand, $request, $data, $plainToken): DemandReviewLink {
            $lockedDemand = Demand::query()->whereKey($demand->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedDemand->status === DemandStatus::ClientApproval, 409);

            $lockedDemand->reviewLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $version = ((int) $lockedDemand->reviewLinks()->max('version')) + 1;

            return $lockedDemand->reviewLinks()->create([
                'organization_id' => $lockedDemand->organization_id,
                'created_by' => $request->user()->id,
                'version' => $version,
                'token_hash' => hash('sha256', $plainToken),
                'material_url' => $data['material_url'],
                'expires_at' => $data['expires_at'],
            ]);
        });

        return back()->with('review_link_url', route('client-reviews.show', ['token' => $plainToken]))
            ->with('review_link_id', $link->id)
            ->with('success', 'Link da versão '.$link->version.' criado. Copie-o agora para enviar ao cliente.');
    }

    public function revoke(Request $request, Demand $demand, DemandReviewLink $reviewLink): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($reviewLink->demand_id === $demand->id && $reviewLink->organization_id === $request->user()->organization_id, 404);
        if ($reviewLink->revoked_at === null) {
            $reviewLink->update(['revoked_at' => now()]);
        }

        return back()->with('success', 'Link revogado.');
    }

    public function show(string $token): View|Response
    {
        $reviewLink = $this->findLink($token);
        if ($reviewLink->revoked_at !== null || $reviewLink->expires_at->isPast()) {
            return response()->view('client-reviews.unavailable', status: 410);
        }
        $reviewLink->load('responses');
        $hasDecision = $reviewLink->responses->contains(fn (DemandReviewResponse $response): bool => in_array($response->type, ['approved', 'changes_requested'], true));
        if ($reviewLink->demand->status !== DemandStatus::ClientApproval && ! $hasDecision) {
            return response()->view('client-reviews.unavailable', status: 410);
        }

        return view('client-reviews.show', compact('reviewLink', 'hasDecision'));
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', 'in:comment,approved,changes_requested'],
            'comment' => ['nullable', 'string', 'max:5000', 'required_if:type,comment,changes_requested'],
        ]);

        DB::transaction(function () use ($data, $token): void {
            $reviewLink = $this->findLink($token, lock: true);
            abort_unless($reviewLink->isAvailable(), 410, 'Este link expirou ou não está mais disponível.');
            $hasDecision = $reviewLink->responses()->whereIn('type', ['approved', 'changes_requested'])->exists();
            if ($hasDecision) {
                throw ValidationException::withMessages(['type' => 'Esta versão já recebeu uma decisão final.']);
            }

            $reviewLink->responses()->create([
                'reviewer_name' => $data['reviewer_name'],
                'type' => $data['type'],
                'comment' => $data['comment'] ?? null,
            ]);

            if (in_array($data['type'], ['approved', 'changes_requested'], true)) {
                $demand = Demand::query()->whereKey($reviewLink->demand_id)->lockForUpdate()->firstOrFail();
                abort_unless($demand->status === DemandStatus::ClientApproval, 410);
                $next = $data['type'] === 'approved' ? DemandStatus::Delivery : DemandStatus::Adjustments;
                $demand->update(['status' => $next]);
                DemandEvent::create([
                    'organization_id' => $demand->organization_id,
                    'demand_id' => $demand->id,
                    'actor_id' => null,
                    'event_type' => 'client_review_'.$data['type'],
                    'summary' => 'O cliente '.$data['reviewer_name'].' respondeu à versão '.$reviewLink->version.'; etapa atualizada para '.$next->label().'.',
                    'from_status' => DemandStatus::ClientApproval->value,
                    'to_status' => $next->value,
                ]);
            }
        });

        return back()->with('success', 'Sua resposta foi registrada. Obrigado pela revisão.');
    }

    private function findLink(string $token, bool $lock = false): DemandReviewLink
    {
        $query = DemandReviewLink::query()->with('demand')->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
