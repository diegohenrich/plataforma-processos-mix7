<?php

namespace App\Services;

use App\Models\Demand;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiAgentTools
{
    /** @return list<array<string, mixed>> */
    public function definitions(User $user, Demand $demand): array
    {
        $tools = [
            $this->tool('read_demand_context', 'Lê o resumo autorizado desta demanda e as tarefas atribuídas à pessoa que perguntou.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]),
            $this->tool('search_knowledge', 'Busca referências e instruções internas ativas desta organização por palavras-chave.', [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 180]],
                'required' => ['query'], 'additionalProperties' => false,
            ]),
        ];

        if ($user->can('manage', $demand)) {
            $tools[] = $this->tool('list_client_feedback', 'Lê comentários e decisões do cliente desta demanda, sem revelar token ou arquivo privado.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]);
        }

        return $tools;
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    public function execute(string $name, mixed $arguments, User $user, Demand $demand): array
    {
        $arguments = is_array($arguments) ? $arguments : [];

        return match ($name) {
            'read_demand_context' => $this->readDemandContext($user, $demand),
            'search_knowledge' => $this->searchKnowledge($arguments, $user),
            'list_client_feedback' => $this->listClientFeedback($user, $demand),
            default => throw ValidationException::withMessages(['ai' => 'A ferramenta solicitada não está autorizada.']),
        };
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function readDemandContext(User $user, Demand $demand): array
    {
        abort_unless($user->can('manage', $demand), 403);

        $tasks = $demand->tasks()
            ->orderBy('id')
            ->get(['id', 'title', 'description', 'status', 'estimate_minutes'])
            ->map(fn ($task) => [
                'title' => $task->title,
                'description' => mb_substr((string) $task->description, 0, 400),
                'status' => $task->status->label(),
                'estimate_minutes' => $task->estimate_minutes,
            ])->all();

        return [
            'result' => [
                'title' => $demand->title,
                'brief' => mb_substr($demand->brief, 0, 6000),
                'stage' => $demand->status->label(),
                'tasks' => $tasks,
            ],
            'receipt' => ['tool' => 'read_demand_context', 'source' => $demand->title, 'items' => count($tasks)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function searchKnowledge(array $arguments, User $user): array
    {
        abort_unless($user->can('viewAny', KnowledgeItem::class), 403);
        $validated = Validator::make($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:180'],
        ])->validate();

        $term = trim($validated['query']);
        $words = collect(preg_split('/\s+/u', $term) ?: [])->filter()->take(6)->values();
        if ($words->isEmpty()) {
            return ['result' => ['items' => []], 'receipt' => ['tool' => 'search_knowledge', 'source' => '', 'items' => 0]];
        }

        $items = KnowledgeItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('archived_at')
            ->where(function ($query) use ($words): void {
                $query->where(function ($title) use ($words): void {
                    foreach ($words as $word) {
                        $title->where('title', 'like', '%'.$word.'%');
                    }
                })->orWhere(function ($content) use ($words): void {
                    foreach ($words as $word) {
                        $content->where('content', 'like', '%'.$word.'%');
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get(['id', 'type', 'title', 'content', 'url', 'steps']);

        $results = $items->map(fn (KnowledgeItem $item) => [
            'title' => $item->title,
            'type' => $item->type,
            'content' => mb_substr($item->content, 0, 1200),
            'url' => $item->url,
            'steps' => array_slice($item->steps ?? [], 0, 12),
        ])->all();

        return [
            'result' => ['items' => $results],
            'receipt' => ['tool' => 'search_knowledge', 'source' => $term, 'items' => count($results)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function listClientFeedback(User $user, Demand $demand): array
    {
        abort_unless($user->can('manage', $demand), 403);
        $links = $demand->reviewLinks()->with(['responses' => fn ($query) => $query->latest('created_at')])->get();
        $feedback = $links->flatMap(fn ($link) => $link->responses->map(fn ($response) => [
            'version' => $link->version,
            'reviewer_name' => $response->reviewer_name,
            'reviewer_name_is_self_reported' => true,
            'type' => $response->type,
            'comment' => $response->comment,
            'anchor_type' => $response->anchor_type,
            'anchor' => $response->anchor_data,
            'created_at' => $response->created_at?->toIso8601String(),
        ]))->take(20)->values()->all();

        return [
            'result' => ['feedback' => $feedback],
            'receipt' => ['tool' => 'list_client_feedback', 'source' => 'Feedback de clientes', 'items' => count($feedback)],
        ];
    }

    /** @param array<string, mixed> $parameters */
    private function tool(string $name, string $description, array $parameters): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $description, 'parameters' => $parameters,
        ]];
    }
}
