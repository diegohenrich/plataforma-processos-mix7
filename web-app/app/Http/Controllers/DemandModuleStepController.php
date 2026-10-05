<?php

namespace App\Http\Controllers;

use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandModuleStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DemandModuleStepController extends Controller
{
    public function update(Request $request, Demand $demand, string $stepKey): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $demand);
        abort_unless(preg_match('/^[a-z][a-z0-9_]{1,39}$/', $stepKey) === 1, 404);
        $data = $request->validate(['completed' => ['required', 'boolean']]);

        $step = DB::transaction(function () use ($request, $demand, $stepKey, $data): DemandModuleStep {
            $step = DemandModuleStep::query()
                ->where('organization_id', $demand->organization_id)
                ->where('demand_id', $demand->id)
                ->where('key', $stepKey)
                ->lockForUpdate()
                ->firstOrFail();
            $completed = (bool) $data['completed'];

            if ($completed === ($step->completed_at !== null)) {
                return $step->load('completer:id,name');
            }

            $step->update([
                'completed_by' => $completed ? $request->user()->id : null,
                'completed_at' => $completed ? now() : null,
            ]);
            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => $completed ? 'module_step_completed' : 'module_step_reopened',
                'summary' => $request->user()->name.($completed ? ' concluiu: ' : ' reabriu: ').$step->label,
            ]);

            return $step->fresh('completer:id,name');
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $step->completed_at ? 'Etapa específica registrada como concluída.' : 'Etapa específica reaberta.',
                'data' => [
                    'key' => $step->key,
                    'label' => $step->label,
                    'completed' => $step->completed_at !== null,
                    'completed_at' => $step->completed_at?->toISOString(),
                    'completed_by' => $step->completer ? ['id' => $step->completer->id, 'name' => $step->completer->name] : null,
                ],
            ]);
        }

        return to_route('demands.show', $demand)->with('success', $step->completed_at
            ? 'Etapa específica registrada como concluída.'
            : 'Etapa específica reaberta.');
    }
}
