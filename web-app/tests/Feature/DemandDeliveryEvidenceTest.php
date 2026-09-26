<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandDeliveryEvidence;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandDeliveryEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_records_audited_delivery_scheduling_or_publication_without_changing_stage(): void
    {
        [$organization, $owner, $manager, $demand, $professional, $client] = $this->workspace();
        $demand->update(['client_user_id' => $client->id]);
        DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Acompanhar publicação',
            'status' => TaskStatus::Todo,
        ]);

        $this->actingAs($manager)->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'published',
            'evidence_url' => 'https://mix7.example.test/campaign',
            'occurred_at' => '2026-09-26T15:30',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $evidence = DemandDeliveryEvidence::firstOrFail();
        $this->assertSame($organization->id, $evidence->organization_id);
        $this->assertSame($demand->id, $evidence->demand_id);
        $this->assertSame($manager->id, $evidence->recorded_by);
        $this->assertSame('published', $evidence->outcome);
        $this->assertSame(DemandStatus::Delivery, $demand->fresh()->status);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'delivery_evidence_recorded',
        ]);

        $this->actingAs($owner)->get(route('demands.show', $demand))
            ->assertOk()->assertSee('Publicado')->assertSee('Abrir referência');
        $this->actingAs($professional)->get(route('demands.show', $demand))
            ->assertOk()->assertSee('Publicado')->assertSee('Registrado por')
            ->assertDontSee('name="outcome"', false);
        $this->actingAs($client)->get(route('demands.show', $demand))
            ->assertOk()->assertDontSee('Registro de entrega, agendamento ou publicação');
    }

    public function test_evidence_can_be_recorded_with_a_manual_note_and_empty_evidence_is_rejected(): void
    {
        [, , $manager, $demand] = $this->workspace();

        $this->actingAs($manager)->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'scheduled',
            'details' => 'Agendado na ferramenta da plataforma social.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('demand_delivery_evidences', [
            'demand_id' => $demand->id,
            'outcome' => 'scheduled',
            'evidence_url' => null,
        ]);

        $this->from(route('demands.show', $demand))->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'delivered',
        ])->assertSessionHasErrors(['evidence_url', 'details']);

        $this->assertSame(1, DemandDeliveryEvidence::count());
    }

    public function test_only_management_can_record_evidence_and_only_at_delivery_or_completed_stage(): void
    {
        [$organization, , $manager, $demand, $professional] = $this->workspace();
        DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Conferir publicação',
            'status' => TaskStatus::Todo,
        ]);

        $this->actingAs($professional)->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'published',
            'details' => 'URL interna de teste.',
        ])->assertForbidden();

        $demand->update(['status' => DemandStatus::InProgress]);
        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('demands.delivery-evidence.store', $demand), [
                'outcome' => 'published',
                'details' => 'Não pode registrar antes de Entrega.',
            ])->assertStatus(409);

        $this->assertSame(0, DemandDeliveryEvidence::count());
    }

    public function test_only_the_demand_organization_can_record_delivery_evidence(): void
    {
        [, , , $demand] = $this->workspace();
        [, , $otherManager] = $this->workspace('outra-agencia');

        $this->actingAs($otherManager)->post(route('demands.delivery-evidence.store', $demand), [
            'outcome' => 'delivered',
            'details' => 'Fora da organização.',
        ])->assertForbidden();

        $this->assertSame(0, DemandEvent::where('event_type', 'delivery_evidence_recorded')->count());
    }

    /** @return array{Organization, User, User, Demand, User, User, User} */
    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => 'Mix7 '.$slug, 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $otherManager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => 'Campanha de teste',
            'brief' => 'Conteúdo fictício.',
            'status' => DemandStatus::Delivery,
        ]);

        return [$organization, $owner, $manager, $demand, $professional, $client, $otherManager];
    }
}
