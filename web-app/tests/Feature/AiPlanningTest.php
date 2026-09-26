<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiPlanningRun;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai_gateway.key' => 'test-key',
            'services.ai_gateway.base_url' => 'https://ai-gateway.vercel.sh/v1',
            'services.ai_gateway.model' => 'test-provider/test-model',
        ]);
    }

    public function test_manager_generates_proposal_without_creating_tasks(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();

        $run = AiPlanningRun::firstOrFail();
        $this->assertSame($organization->id, $run->organization_id);
        $this->assertSame($manager->id, $run->requested_by);
        $this->assertSame('pending', $run->status);
        $this->assertSame(2, count($run->proposal['tasks']));
        $this->assertSame(0, $run->proposal['tasks'][1]['depends_on'][0]);
        $this->assertSame(0, DemandTask::count());
        $this->assertSame(64, strlen($run->input_hash));
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'ai_planning_proposed']);
        Http::assertSent(fn ($request) => $request['messages'][1]['content'] !== ''
            && str_contains($request['messages'][1]['content'], 'Briefing sintético')
            && $request['response_format']['type'] === 'json_schema');
    }

    public function test_manager_edits_and_approves_proposal_with_real_dependency_and_audit(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'tasks' => [
                ['include' => 1, 'title' => 'Briefing revisado', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 25, 'assignee_id' => $professional->id],
                ['include' => 1, 'title' => 'Mapa do site revisado', 'responsibility_profile' => 'Design', 'estimate_minutes' => 65, 'assignee_id' => $professional->id],
            ],
            'questions' => ['Qual prazo?'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tasks = $demand->tasks()->get();
        $this->assertCount(2, $tasks);
        $this->assertSame('Briefing revisado', $tasks[0]->title);
        $this->assertSame($professional->id, $tasks[0]->assigned_to);
        $this->assertSame([$tasks[0]->id], $tasks[1]->dependencies()->pluck('demand_tasks.id')->all());
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame($manager->id, $run->fresh()->reviewed_by);
        $this->assertSame('Mapa do site revisado', $run->fresh()->reviewed_tasks['tasks'][1]['title']);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'ai_planning_approved']);

        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $tasks[1]))->assertSessionHasErrors('timer');
        $tasks[0]->update(['status' => TaskStatus::Completed]);
        $this->post(route('demand-tasks.timer.start', $tasks[1]))->assertRedirect();
    }

    public function test_proposal_cannot_be_applied_twice_or_assigned_outside_organization(): void
    {
        [, $manager, , $demand] = $this->workspace();
        [, , $externalProfessional] = $this->workspace('other');
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();
        $invalid = [
            'tasks' => [
                ['include' => 1, 'title' => 'A', 'responsibility_profile' => 'Design', 'estimate_minutes' => 10, 'assignee_id' => $externalProfessional->id],
                ['include' => 1, 'title' => 'B', 'responsibility_profile' => 'Design', 'estimate_minutes' => 10, 'assignee_id' => $externalProfessional->id],
            ],
        ];
        $this->post(route('ai-planning.approve', [$demand, $run]), $invalid)->assertSessionHasErrors('tasks.0.assignee_id');
        $this->assertSame(0, DemandTask::count());

        $valid = $invalid;
        $insideProfessional = User::query()->where('organization_id', $demand->organization_id)->where('role', UserRole::Professional->value)->firstOrFail();
        foreach ($valid['tasks'] as &$task) {
            $task['assignee_id'] = $insideProfessional->id;
        }
        unset($task);
        $this->post(route('ai-planning.approve', [$demand, $run]), $valid)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('ai-planning.approve', [$demand, $run]), $valid)->assertSessionHasErrors('ai');
        $this->assertSame(2, DemandTask::count());
    }

    public function test_missing_provider_configuration_and_wrong_roles_do_not_create_runs(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        config(['services.ai_gateway.key' => '']);
        config(['services.ai_gateway.oidc_token' => '']);
        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');
        config(['services.ai_gateway.key' => 'test-key']);
        config(['services.ai_gateway.oidc_token' => '']);
        $this->actingAs($professional)->post(route('ai-planning.propose', $demand))->assertForbidden();
        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_runtime_oidc_token_authenticates_gateway_when_api_key_is_not_set(): void
    {
        [, $manager, , $demand] = $this->workspace();
        config(['services.ai_gateway.key' => '', 'services.ai_gateway.oidc_token' => 'short-lived-vercel-token']);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer short-lived-vercel-token'));
        $this->assertSame('pending', AiPlanningRun::firstOrFail()->status);
        $this->assertSame(0, DemandTask::count());
    }

    public function test_discard_keeps_audit_and_creates_no_tasks(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();
        $this->delete(route('ai-planning.discard', [$demand, $run]))->assertRedirect();
        $this->assertSame('discarded', $run->fresh()->status);
        $this->assertSame($manager->id, $run->fresh()->reviewed_by);
        $this->assertSame(0, DemandTask::count());
        $this->assertDatabaseHas('demand_events', ['event_type' => 'ai_planning_discarded', 'actor_id' => $manager->id]);
    }

    public function test_invalid_provider_dependency_response_is_rejected_without_persistence(): void
    {
        [, $manager, , $demand] = $this->workspace();
        $response = $this->providerResponse();
        $proposal = json_decode($response['choices'][0]['message']['content'], true, 32, JSON_THROW_ON_ERROR);
        $proposal['tasks'][1]['depends_on'] = [1];
        $response['choices'][0]['message']['content'] = json_encode($proposal, JSON_THROW_ON_ERROR);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($response, 200)]);

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');
        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_manager_can_reject_one_suggestion_and_remaining_dependencies_are_recalculated(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'tasks' => [
                ['include' => 0],
                ['include' => 1, 'title' => 'Mapear páginas', 'responsibility_profile' => 'Design', 'estimate_minutes' => 75, 'assignee_id' => $professional->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $demand->tasks()->count());
        $task = $demand->tasks()->firstOrFail();
        $this->assertSame('Mapear páginas', $task->title);
        $this->assertSame(0, $task->dependencies()->count());
        $reviewed = $run->fresh()->reviewed_tasks['tasks'];
        $this->assertFalse($reviewed[0]['include']);
        $this->assertTrue($reviewed[1]['include']);
    }

    public function test_non_manager_does_not_see_ai_planning_controls(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Tarefa visível ao profissional',
            'status' => TaskStatus::Todo,
        ]);
        $this->actingAs($professional)->get(route('demands.show', $demand))
            ->assertOk()->assertDontSee('Planejamento com IA')->assertDontSee('Gerar proposta a partir do briefing');
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::Planning,
        ]);

        return [$organization, $manager, $professional, $demand];
    }

    private function providerResponse(): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 'Planejar site em duas etapas.',
                'questions' => ['Qual prazo?'],
                'tasks' => [
                    ['title' => 'Organizar briefing', 'rationale' => 'Confirmar objetivo.', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 30, 'depends_on' => []],
                    ['title' => 'Mapear páginas', 'rationale' => 'Preparar estrutura.', 'responsibility_profile' => 'Design', 'estimate_minutes' => 60, 'depends_on' => [0]],
                ],
            ], JSON_THROW_ON_ERROR)]]],
            'usage' => ['prompt_tokens' => 123, 'completion_tokens' => 45],
        ];
    }
}
