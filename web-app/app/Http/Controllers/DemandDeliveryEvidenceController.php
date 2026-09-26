<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Models\Demand;
use App\Models\DemandDeliveryEvidence;
use App\Models\DemandEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DemandDeliveryEvidenceController extends Controller
{
    public function store(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless(in_array($demand->status, [DemandStatus::Delivery, DemandStatus::Completed], true), 409, 'A evidência só pode ser registrada na etapa Entrega ou depois da conclusão.');

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['delivered', 'scheduled', 'published'])],
            'evidence_url' => ['nullable', 'url:http,https', 'max:2048', 'required_without:details'],
            'details' => ['nullable', 'string', 'max:3000', 'required_without:evidence_url'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($request, $demand, $data): void {
            $evidence = DemandDeliveryEvidence::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'recorded_by' => $request->user()->id,
                ...$data,
            ]);

            $label = match ($evidence->outcome) {
                'scheduled' => 'agendamento',
                'published' => 'publicação',
                default => 'entrega',
            };

            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'delivery_evidence_recorded',
                'summary' => $request->user()->name.' registrou evidência de '.$label.'.',
            ]);
        });

        return back()->with('success', 'Evidência registrada no histórico da demanda.');
    }
}
