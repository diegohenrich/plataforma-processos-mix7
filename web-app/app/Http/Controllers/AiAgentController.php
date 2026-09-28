<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Jobs\ProcessAiAgentRun;
use App\Models\AiAgentRun;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Services\AiAgentRuntime;
use App\Services\AiProviderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiAgentController extends Controller
{
    public function organizationIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->organization_id !== null && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
        $runs = AiAgentRun::query()->where('organization_id', $user->organization_id)
            ->whereNull('demand_id')->whereIn('agent', ['organization_assistant', 'knowledge_assistant', 'operations_assistant'])->where('requested_by', $user->id)
            ->latest()->paginate(15);
        $providerSettings = app(AiProviderSettings::class)->forOrganization((int) $user->organization_id);
        $configured = app(AiProviderSettings::class)->isConfigured($providerSettings);

        return view('ai.organization-assistant', compact('runs', 'configured', 'providerSettings'));
    }

    public function askOrganization(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->organization_id !== null && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:3000'],
            'specialist' => ['nullable', 'string', 'in:organization_assistant,knowledge_assistant,operations_assistant'],
        ]);
        $agent = $data['specialist'] ?? 'organization_assistant';
        $providerSettings = app(AiProviderSettings::class)->forOrganization((int) $user->organization_id);
        if (! app(AiProviderSettings::class)->isConfigured($providerSettings)) {
            return back()->withErrors(['assistant' => 'O agente ainda não está configurado. Nenhuma chamada foi enviada.']);
        }
        $model = $providerSettings['model'] ?: 'claude-code-subscription';
        $provider = $providerSettings['provider'];

        $run = AiAgentRun::create([
            'organization_id' => $user->organization_id,
            'demand_id' => null,
            'requested_by' => $user->id,
            'agent' => $agent,
            'provider' => $provider,
            'model' => $model,
            'input_hash' => hash('sha256', $data['question']),
            'input_characters' => mb_strlen($data['question']),
            'status' => 'queued',
        ]);

        try {
            ProcessAiAgentRun::dispatch($run->id, Crypt::encryptString($data['question']));
        } catch (\Throwable) {
            $run->update(['status' => 'failed', 'error_message' => 'Não foi possível colocar a pergunta na fila.', 'completed_at' => now()]);

            return back()->withErrors(['assistant' => 'Não foi possível iniciar o assistente. Nenhuma alteração foi feita.']);
        }

        return to_route('organization-assistant.index')->with('success', 'Pergunta enviada ao assistente da agência. Nenhuma ação será aplicada.');
    }

    public function organizationStatus(Request $request, AiAgentRun $run): JsonResponse
    {
        $user = $request->user();
        abort_unless($run->demand_id === null && in_array($run->agent, ['organization_assistant', 'knowledge_assistant', 'operations_assistant'], true) && $run->organization_id === $user->organization_id && $run->requested_by === $user->id, 404);

        return response()->json(['status' => $run->status]);
    }

    public function ask(Request $request, Demand $demand): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('view', $demand);
        abort_if($user->role === UserRole::Client, 403);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:3000'],
            'specialist' => ['nullable', 'string', 'in:demand_assistant,approval_assistant'],
        ]);
        abort_if(($data['specialist'] ?? 'demand_assistant') === 'approval_assistant' && ! $user->can('manage', $demand), 403);

        $providerSettings = app(AiProviderSettings::class)->forOrganization((int) $user->organization_id);
        if (! app(AiProviderSettings::class)->isConfigured($providerSettings)) {
            return back()->withErrors(['assistant' => 'O agente ainda não está configurado. Nenhuma chamada foi enviada.']);
        }
        $model = $providerSettings['model'] ?: 'claude-code-subscription';
        $provider = $providerSettings['provider'];

        $run = DB::transaction(function () use ($request, $demand, $data, $model, $provider): AiAgentRun {
            $run = AiAgentRun::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'requested_by' => $request->user()->id,
                'agent' => $data['specialist'] ?? 'demand_assistant',
                'provider' => $provider,
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
        abort_unless($run->requested_by === $request->user()->id, 404);

        return response()->json(['status' => $run->status]);
    }

    public function askContextual(Request $request, AiAgentRuntime $runtime): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->organization_id !== null && $user->role !== UserRole::Client, 403);
        $data = $request->validate([
            'area' => ['required', 'string', 'in:overview,demands,tasks,approvals,team,knowledge,service-access,capacity,evaluations,onboarding'],
            'messages' => ['required', 'array', 'min:1', 'max:10'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'min:1', 'max:2000'],
        ]);
        abort_unless(collect($data['messages'])->last()['role'] === 'user', 422, 'Envie uma pergunta para o assistente.');
        abort_if(collect($data['messages'])->sum(fn (array $message): int => mb_strlen($message['content'])) > 7000, 422, 'Conversa longa demais. Inicie uma nova conversa.');
        $areas = [
            'overview' => 'visão geral e navegação', 'demands' => 'criação e acompanhamento de demandas',
            'tasks' => 'tarefas e execução do trabalho', 'approvals' => 'aprovação de criativos e feedback do cliente',
            'team' => 'equipe e responsabilidades', 'knowledge' => 'conhecimento e onboarding',
            'service-access' => 'acessos a serviços externos', 'capacity' => 'disponibilidade e carga de trabalho',
            'evaluations' => 'avaliações humanas de tarefas', 'onboarding' => 'integração e treinamento de profissionais',
        ];
        $dialogue = collect($data['messages'])->map(fn (array $message): string => ($message['role'] === 'assistant' ? 'Assistente' : 'Pessoa').': '.$message['content'])->implode("\n\n");
        $question = 'Área atual: '.$areas[$data['area']].". Esta é a conversa de ajuda, não um comando para executar no sistema:\n".$dialogue;
        $settings = app(AiProviderSettings::class)->forOrganization((int) $user->organization_id);
        $run = AiAgentRun::create([
            'organization_id' => $user->organization_id, 'demand_id' => null, 'requested_by' => $user->id,
            'agent' => 'contextual_assistant', 'provider' => $settings['provider'],
            'model' => $settings['model'] ?: $settings['provider'], 'input_hash' => hash('sha256', $question),
            'input_characters' => mb_strlen($question), 'status' => 'running', 'started_at' => now(),
        ]);

        try {
            $result = $runtime->run(null, $user, $question, 'contextual_assistant');
            $run->update([
                'status' => 'completed', 'answer' => $result['answer'], 'tool_trace' => $result['tool_trace'],
                'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens'],
                'provider_cost' => $result['provider_cost'], 'cost_currency' => $result['provider_cost'] === null ? null : 'USD',
                'completed_at' => now(),
            ]);

            return response()->json(['answer' => $result['answer'], 'run_id' => $run->id]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 255), 'completed_at' => now()]);

            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
