<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiDemandMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_creates_a_demand_with_initial_tasks_and_audited_client_link(): void
    {
        [$organization, $owner, $professional, , $client] = $this->workspace();
        $token = $owner->createToken('desktop')->plainTextToken;
        $response = $this->authenticate($token)
            ->postJson('/api/v1/demands', [
                'title' => 'Site institucional',
                'brief' => 'Briefing sintético para o site.',
                'intake_source' => 'E-mail',
                'brief_author_id' => $professional->id,
                'module_key' => 'website_review',
                'client_user_id' => $client->id,
                'tasks' => [
                    ['title' => 'Planejar páginas', 'assignee_id' => $professional->id, 'estimate_minutes' => 90],
                    ['title' => 'Preparar conteúdo', 'assignee_id' => $professional->id],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Site institucional')
            ->assertJsonPath('data.intake_source', 'E-mail')
            ->assertJsonPath('data.brief_author.id', $professional->id)
            ->assertJsonPath('data.brief_author.name', $professional->name)
            ->assertJsonPath('data.status', DemandStatus::Received->value)
            ->assertJsonPath('data.module.key', 'website_review')
            ->assertJsonPath('data.module.label', 'Revisão de site')
            ->assertJsonPath('data.module.version', 1)
            ->assertJsonPath('data.client_user_id', $client->id)
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonPath('data.tasks.0.assignee.id', $professional->id)
            ->assertJsonPath('data.tasks.0.assigned_by.id', $owner->id)
            ->assertJsonPath('data.tasks.0.assigned_by.name', $owner->name)
            ->assertJsonPath('data.tasks.0.estimate_minutes', 90);

        $demand = Demand::query()->firstOrFail();
        $this->assertSame($organization->id, $demand->organization_id);
        $this->assertSame($owner->id, $demand->created_by);
        $this->assertSame('website_review', $demand->module_key);
        $this->assertSame(1, $demand->module_version);
        $this->authenticate($token)->getJson("/api/v1/demands/{$demand->id}")
            ->assertOk()
            ->assertJsonPath('data.module.key', 'website_review')
            ->assertJsonPath('data.module.version', 1)
            ->assertJsonPath('data.intake_source', 'E-mail')
            ->assertJsonPath('data.brief_author.id', $professional->id)
            ->assertJsonPath('data.tasks.0.assigned_by.id', $owner->id)
            ->assertJsonPath('data.tasks.0.assigned_by.name', $owner->name);
        $this->authenticate($token)->getJson('/api/v1/demands')
            ->assertOk()
            ->assertJsonPath('data.0.tasks.0.assigned_by.id', $owner->id)
            ->assertJsonPath('data.0.tasks.0.assigned_by.name', $owner->name);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $owner->id,
            'event_type' => 'demand_created',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $owner->id,
            'event_type' => 'demand_client_assigned',
        ]);
        $this->assertSame(2, DemandTask::query()->where('demand_id', $demand->id)->count());
    }

    public function test_demand_creation_rejects_brief_author_outside_active_internal_team(): void
    {
        [, $owner, $professional, , $client] = $this->workspace();
        [, , $outsideProfessional] = $this->workspace('outside');
        $base = [
            'title' => 'Demanda de teste',
            'brief' => 'Briefing sintético.',
            'module_key' => 'social_creative',
            'tasks' => [['title' => 'Criar peça', 'assignee_id' => $professional->id]],
        ];
        $token = $owner->createToken('desktop')->plainTextToken;

        foreach ([$outsideProfessional->id, $client->id] as $invalidAuthorId) {
            $this->authenticate($token)->postJson('/api/v1/demands', [
                ...$base,
                'brief_author_id' => $invalidAuthorId,
            ])->assertUnprocessable()->assertJsonValidationErrors('brief_author_id');
        }

        $this->assertSame(0, Demand::query()->count());
    }

    public function test_demand_creation_rejects_unauthorized_profiles_and_cross_organization_assignments(): void
    {
        [$organization, $owner, $professional, , $client] = $this->workspace();
        [, , $outsideProfessional, , $outsideClient] = $this->workspace('outside');
        $payload = [
            'title' => 'Demanda inválida',
            'brief' => 'Briefing sintético.',
            'module_key' => 'social_creative',
            'tasks' => [['title' => 'Tarefa', 'assignee_id' => $professional->id]],
        ];

        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->postJson('/api/v1/demands', $payload)->assertForbidden();
        $this->authenticate($client->createToken('desktop')->plainTextToken)
            ->postJson('/api/v1/demands', $payload)->assertForbidden();

        $this->authenticate($owner->createToken('desktop')->plainTextToken)
            ->postJson('/api/v1/demands', [
                ...$payload,
                'client_user_id' => $outsideClient->id,
                'tasks' => [['title' => 'Tarefa externa', 'assignee_id' => $outsideProfessional->id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_user_id', 'tasks.0.assignee_id']);

        $this->assertSame(0, Demand::query()->count());
        $this->assertSame(0, DemandTask::query()->count());
    }

    public function test_demand_creation_rejects_unknown_approval_modules(): void
    {
        [, $owner, $professional] = $this->workspace();

        $this->authenticate($owner->createToken('desktop')->plainTextToken)
            ->postJson('/api/v1/demands', [
                'title' => 'Tipo inexistente',
                'brief' => 'Briefing sintético.',
                'module_key' => 'inventado',
                'tasks' => [['title' => 'Preparar material', 'assignee_id' => $professional->id]],
            ])->assertUnprocessable()->assertJsonValidationErrors('module_key');

        $this->assertSame(0, Demand::query()->count());
    }

    public function test_management_adds_tasks_and_demand_transition_waits_for_task_completion(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $demand = $this->demand($organization, $manager);
        $token = $manager->createToken('desktop')->plainTextToken;

        $taskResponse = $this->authenticate($token)->postJson("/api/v1/demands/{$demand->id}/tasks", [
            'title' => 'Criar página inicial',
            'assignee_id' => $professional->id,
            'estimate_minutes' => 120,
        ]);
        $taskResponse->assertCreated()
            ->assertJsonPath('data.title', 'Criar página inicial')
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.assignee.id', $professional->id)
            ->assertJsonPath('data.assigned_by.id', $manager->id)
            ->assertJsonPath('data.assigned_by.name', $manager->name);
        $taskId = $taskResponse->json('data.id');

        $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => DemandStatus::Planning->value])
            ->assertOk()
            ->assertJsonPath('data.status', DemandStatus::Planning->value);
        $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => DemandStatus::InProgress->value])
            ->assertOk()
            ->assertJsonPath('data.status', DemandStatus::InProgress->value);
        $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => DemandStatus::InternalReview->value])
            ->assertConflict()
            ->assertJsonPath('errors.status.0', 'Conclua todas as tarefas antes da revisão interna.');

        $this->patchJson("/api/v1/tasks/{$taskId}/status", ['status' => TaskStatus::InProgress->value])
            ->assertOk();
        $this->patchJson("/api/v1/tasks/{$taskId}/status", ['status' => TaskStatus::Completed->value])
            ->assertOk();
        $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => DemandStatus::InternalReview->value])
            ->assertOk()
            ->assertJsonPath('data.status', DemandStatus::InternalReview->value);
        $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => DemandStatus::ClientApproval->value])
            ->assertOk()
            ->assertJsonPath('data.status', DemandStatus::ClientApproval->value);

        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'task_id' => $taskId,
            'actor_id' => $manager->id,
            'event_type' => 'task_assigned',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'demand_status_changed',
            'from_status' => DemandStatus::InProgress->value,
            'to_status' => DemandStatus::InternalReview->value,
        ]);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'demand_status_changed',
            'from_status' => DemandStatus::InternalReview->value,
            'to_status' => DemandStatus::ClientApproval->value,
            'summary' => $manager->name.' aprovou a revisão interna e enviou a demanda para aprovação do cliente',
        ]);
    }

    public function test_task_creation_is_rejected_after_client_approval_begins(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $demand = $this->demand($organization, $manager);
        $demand->update(['status' => DemandStatus::ClientApproval]);

        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/demands/{$demand->id}/tasks", [
                'title' => 'Alteração fora do fluxo',
                'assignee_id' => $professional->id,
            ])
            ->assertConflict()
            ->assertJsonValidationErrors('title');

        $this->assertSame(0, DemandTask::query()->count());
    }

    public function test_management_api_cannot_bypass_client_review_decision(): void
    {
        [$organization, $manager] = $this->workspace();
        $demand = $this->demand($organization, $manager);
        $demand->update(['status' => DemandStatus::ClientApproval]);
        $this->authenticate($manager->createToken('review-stage')->plainTextToken);

        foreach ([DemandStatus::Delivery, DemandStatus::Adjustments] as $target) {
            $this->patchJson("/api/v1/demands/{$demand->id}/status", ['status' => $target->value])
                ->assertConflict()
                ->assertJsonPath('errors.status.0', 'A etapa só avança depois que o cliente registra uma decisão pelo link de revisão.');
        }

        $this->assertSame(DemandStatus::ClientApproval, $demand->fresh()->status);
        $this->assertDatabaseMissing('demand_events', ['demand_id' => $demand->id, 'event_type' => 'demand_status_changed']);
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        return [$organization, $owner, $professional, $colleague, $client];
    }

    private function demand(Organization $organization, User $creator): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::Received,
        ]);
    }

    private function authenticate(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
