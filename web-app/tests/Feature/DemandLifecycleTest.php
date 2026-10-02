<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandDeliveryEvidence;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_demand_runs_from_brief_to_client_adjustments_approval_and_documented_delivery(): void
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7-lifecycle']);
        $manager = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::MarketingManager,
            'is_active' => true,
        ]);
        $professional = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Professional,
            'is_active' => true,
        ]);
        $client = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->post(route('demands.store'), [
            'title' => 'Campanha de lançamento',
            'brief' => 'Apresentar a nova linha de produtos com peças para redes sociais.',
            'intake_source' => 'Briefing fictício de teste',
            'brief_author_id' => $manager->id,
            'module_key' => 'social_creative',
            'client_user_id' => $client->id,
            'tasks' => [[
                'title' => 'Preparar criativo principal',
                'assignee_id' => $professional->id,
                'estimate_minutes' => 90,
            ]],
        ])->assertRedirect();

        $demand = Demand::query()->where('title', 'Campanha de lançamento')->firstOrFail();
        $task = $demand->tasks()->firstOrFail();
        $this->assertSame(DemandStatus::Received, $demand->status);
        $this->assertSame($client->id, $demand->client_user_id);
        $this->assertSame($professional->id, $task->assigned_to);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_assigned',
        ]);

        foreach ([DemandStatus::Planning, DemandStatus::InProgress] as $nextStatus) {
            $this->patch(route('demands.status', $demand), ['status' => $nextStatus->value])->assertRedirect();
        }
        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task))->assertRedirect();
        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::InProgress->value])->assertRedirect();
        $this->travel(45)->seconds();
        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::Completed->value])->assertRedirect();
        $this->assertNotNull(TaskTimeEntry::query()->where('task_id', $task->id)->firstOrFail()->ended_at);

        $this->actingAs($manager)
            ->patch(route('demands.status', $demand), ['status' => DemandStatus::InternalReview->value])->assertRedirect();
        $this->patch(route('demands.status', $demand), ['status' => DemandStatus::ClientApproval->value])->assertRedirect();
        $versionOne = $this->createReviewLink($manager, $demand, 'https://preview.example.test/campanha-v1');
        $this->get(route('client-reviews.show', $versionOne))->assertOk()->assertSee('Campanha de lançamento');

        $this->post(route('client-reviews.respond', $versionOne), [
            'reviewer_name' => 'Cliente de demonstração',
            'type' => 'annotation',
            'comment' => 'Aumentar o contraste do título.',
            'anchor_type' => 'area',
            'anchor_x' => 50,
            'anchor_y' => 25,
        ])->assertRedirect();
        $this->post(route('client-reviews.respond', $versionOne), [
            'reviewer_name' => 'Cliente de demonstração',
            'type' => 'changes_requested',
            'comment' => 'Ajustar o contraste antes da publicação.',
        ])->assertRedirect();
        $this->assertSame(DemandStatus::Adjustments, $demand->fresh()->status);
        $this->assertDatabaseHas('demand_review_responses', [
            'type' => 'annotation',
            'comment' => 'Aumentar o contraste do título.',
            'anchor_type' => 'area',
        ]);

        $this->actingAs($manager)
            ->patch(route('demands.status', $demand), ['status' => DemandStatus::ClientApproval->value])->assertRedirect();
        $versionTwo = $this->createReviewLink($manager, $demand, 'https://preview.example.test/campanha-v2');
        $this->get(route('client-reviews.show', $versionTwo))->assertOk()->assertSee('campanha-v2');
        $this->post(route('client-reviews.respond', $versionTwo), [
            'reviewer_name' => 'Cliente de demonstração',
            'type' => 'approved',
        ])->assertRedirect();
        $this->assertSame(DemandStatus::Delivery, $demand->fresh()->status);

        $this->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'scheduled',
            'details' => 'Agendamento fictício confirmado para o próximo dia útil.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('demands.status', $demand), ['status' => DemandStatus::Completed->value])->assertRedirect();

        $this->assertSame(DemandStatus::Completed, $demand->fresh()->status);
        $this->assertSame(2, $demand->reviewLinks()->count());
        $this->assertNotNull($demand->reviewLinks()->where('version', 1)->firstOrFail()->revoked_at);
        $this->assertDatabaseHas('demand_review_responses', ['type' => 'approved']);
        $this->assertSame($manager->id, DemandDeliveryEvidence::query()->where('demand_id', $demand->id)->firstOrFail()->recorded_by);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'delivery_evidence_recorded',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'to_status' => DemandStatus::Completed->value,
        ]);
    }

    private function createReviewLink(User $manager, Demand $demand, string $materialUrl): string
    {
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => $materialUrl,
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        return basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
    }
}
