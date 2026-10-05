<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingAssignmentStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', KnowledgeItem::class);
        $user = $request->user();
        $items = KnowledgeItem::query()->where('organization_id', $user->organization_id)->whereNull('archived_at')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->string('q')->toString())).'%';
                $query->where(fn ($search) => $search->where('title', 'like', $term)->orWhere('content', 'like', $term));
            })->with(['creator:id,name', 'updater:id,name', 'assignments.assignee:id,name', 'assignments.assigner:id,name', 'assignments.steps.completer:id,name'])
            ->orderBy('type')->orderBy('title')->paginate(20)->withQueryString();
        if ($user->role === UserRole::Professional) {
            $items->getCollection()->each(function ($item) use ($user): void {
                $item->setRelation('assignments', $item->assignments()->where('assigned_to', $user->id)->with(['assignee:id,name', 'assigner:id,name', 'steps.completer:id,name'])->get());
            });
        }
        foreach ($items->items() as $item) {
            foreach ($item->assignments as $assignment) {
                $assignment->setRelation('steps', $assignment->steps()->with('completer:id,name')->get());
            }
        }
        $members = User::query()->where('organization_id', $user->organization_id)->where('is_active', true)->where('role', UserRole::Professional->value)->orderBy('name')->get(['id', 'name']);
        $canManage = $user->can('manageAny', KnowledgeItem::class);
        $archivedCount = $canManage ? KnowledgeItem::query()->where('organization_id', $user->organization_id)->whereNotNull('archived_at')->count() : 0;

        return view('knowledge.index', compact('items', 'members', 'canManage', 'archivedCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageAny', KnowledgeItem::class);
        $data = $this->validateItem($request);
        $steps = $this->normalizeSteps($data['steps'] ?? []);
        if ($data['type'] === 'onboarding' && $steps === []) {
            return back()->withErrors(['steps' => 'Inclua ao menos uma etapa para a trilha.'])->withInput();
        }
        KnowledgeItem::create($this->itemAttributes($data, $steps, $request->user(), $request->user()->organization_id));

        return redirect()->route('knowledge.index')->with('success', 'Conteúdo adicionado à biblioteca da equipe.');
    }

    public function update(Request $request, KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);
        abort_if($item->archived_at, 404);
        $data = $this->validateItem($request);
        $steps = $this->normalizeSteps($data['steps'] ?? []);
        if ($data['type'] === 'onboarding' && $steps === []) {
            return back()->withErrors(['steps' => 'Inclua ao menos uma etapa para a trilha.'])->withInput();
        }
        $attributes = $this->itemAttributes($data, $steps, $request->user(), $item->organization_id);
        unset($attributes['created_by']);
        $attributes['updated_by'] = $request->user()->id;
        $item->update($attributes);

        return redirect()->route('knowledge.index')->with('success', 'Conteúdo atualizado. As trilhas já atribuídas mantêm uma cópia das etapas anteriores.');
    }

    public function archive(Request $request, KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);
        abort_if($item->archived_at, 404);
        $item->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);

        return redirect()->route('knowledge.index')->with('success', 'Conteúdo arquivado.');
    }

    public function archived(Request $request): View
    {
        $this->authorize('manageAny', KnowledgeItem::class);
        $items = KnowledgeItem::query()->where('organization_id', $request->user()->organization_id)->whereNotNull('archived_at')->with('creator:id,name')->orderByDesc('archived_at')->paginate(20);

        return view('knowledge.archived', compact('items'));
    }

    public function restore(Request $request, KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);
        abort_unless($item->archived_at, 404);
        $item->update(['archived_at' => null, 'updated_by' => $request->user()->id]);

        return redirect()->route('knowledge.archived')->with('success', 'Conteúdo restaurado à biblioteca.');
    }

    public function assign(Request $request, KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);
        abort_if($item->archived_at, 404);
        abort_unless($item->type === 'onboarding' && is_array($item->steps) && count($item->steps) > 0, 422);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::unique('onboarding_assignments', 'assigned_to')->where(fn ($query) => $query
                ->where('knowledge_item_id', $item->id)), Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $request->user()->organization_id)
                ->where('role', UserRole::Professional->value)
                ->where('is_active', true))],
        ]);
        $assignment = DB::transaction(function () use ($item, $data, $request): OnboardingAssignment {
            $assignment = OnboardingAssignment::create(['organization_id' => $request->user()->organization_id, 'knowledge_item_id' => $item->id, 'assigned_to' => $data['user_id'], 'assigned_by' => $request->user()->id, 'title' => $item->title, 'steps' => $item->steps]);
            foreach ($item->steps as $position => $title) {
                $assignment->steps()->create(['position' => $position + 1, 'title' => $title]);
            }

            return $assignment;
        });

        return redirect()->route('knowledge.index')->with('success', 'Trilha atribuída a '.$assignment->assignee->name.'.');
    }

    public function toggleStep(Request $request, OnboardingAssignment $assignment, OnboardingAssignmentStep $step): RedirectResponse
    {
        abort_unless($assignment->organization_id === $request->user()->organization_id && $step->assignment_id === $assignment->id, 404);
        abort_unless($request->user()->can('manageAny', KnowledgeItem::class) || ($request->user()->role === UserRole::Professional && $assignment->assigned_to === $request->user()->id), 403);
        abort_unless($request->user()->is_active, 403);
        $done = $step->completed_at === null;
        $step->update(['completed_by' => $done ? $request->user()->id : null, 'completed_at' => $done ? now() : null]);

        return back()->with('success', $done ? 'Etapa concluída.' : 'Etapa reaberta.');
    }

    private function validateItem(Request $request): array
    {
        return $request->validate(['type' => ['required', Rule::in(['reference', 'training', 'contact', 'onboarding'])], 'title' => ['required', 'string', 'max:180'], 'content' => ['required', 'string', 'max:12000'], 'owner_name' => ['nullable', 'string', 'max:160'], 'audience' => ['nullable', 'string', 'max:160'], 'review_due_at' => ['nullable', 'date'], 'url' => ['nullable', 'url:http,https', 'max:2048'], 'steps' => ['nullable', 'array', 'max:30'], 'steps.*' => ['nullable', 'string', 'max:180']]);
    }

    private function normalizeSteps(array $steps): array
    {
        return array_values(array_filter(array_map(fn ($step) => trim((string) $step), $steps), fn ($step) => $step !== ''));
    }

    private function itemAttributes(array $data, array $steps, User $user, int $organizationId): array
    {
        return ['organization_id' => $organizationId, 'created_by' => $user->id, 'type' => $data['type'], 'title' => trim($data['title']), 'content' => trim($data['content']), 'owner_name' => isset($data['owner_name']) ? trim($data['owner_name']) : null, 'audience' => isset($data['audience']) ? trim($data['audience']) : null, 'review_due_at' => $data['review_due_at'] ?? null, 'url' => isset($data['url']) ? trim($data['url']) : null, 'steps' => in_array($data['type'], ['training', 'onboarding'], true) ? $steps : null];
    }
}
