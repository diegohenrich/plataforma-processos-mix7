<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Jobs\ProcessAiAgentRun;
use App\Models\AiAgentRun;
use App\Models\Demand;
use App\Models\DemandEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AiAgentController extends Controller
{
    public function ask(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_if($request->user()->role === UserRole::Client, 403);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $model = (string) config('services.ai_gateway.model');
        if ($model === '' || (! config('services.ai_gateway.key') && ! config('services.ai_gateway.oidc_token'))) {
            return back()->withErrors(['assistant' => 'O agente ainda não está configurado. Nenhuma chamada foi enviada.']);
        }

        $run = DB::transaction(function () use ($request, $demand, $data, $model): AiAgentRun {
            $run = AiAgentRun::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'requested_by' => $request->user()->id,
                'agent' => 'demand_assistant',
                'provider' => 'vercel-ai-gateway',
                'model' => $model,
                'input_hash' => hash('sha256', $data['question']),
                'input_characters' => mb_strlen($data['question']),
                'status' => 'queued',
            ]);

            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'ai_agent_requested',
                'summary' => $request->user()->name.' pediu ajuda ao assistente da demanda. Nenhuma alteração foi autorizada.',
            ]);

            return $run;
        });

        try {
            ProcessAiAgentRun::dispatch($run->id, Crypt::encryptString($data['question']));
        } catch (\Throwable) {
            $run->update([
                'status' => 'failed',
                'error_message' => 'Não foi possível colocar a pergunta na fila. Tente novamente.',
                'completed_at' => now(),
            ]);

            return back()->withErrors(['assistant' => 'Não foi possível iniciar o assistente. Nenhuma alteração foi feita.']);
        }

        return back()->with('success', 'Pergunta enviada ao assistente. Ele só pode consultar dados autorizados; nenhuma ação será aplicada.');
    }

    public function status(Request $request, Demand $demand, AiAgentRun $run): JsonResponse
    {
        $this->authorize('view', $demand);
        abort_unless($run->demand_id === $demand->id && $run->organization_id === $request->user()->organization_id, 404);
        abort_unless($run->requested_by === $request->user()->id || $request->user()->can('manage', $demand), 404);

        return response()->json(['status' => $run->status]);
    }
}
