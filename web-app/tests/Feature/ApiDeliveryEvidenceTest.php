<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandDeliveryEvidence;
use App\Models\DemandEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiDeliveryEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_records_delivery_evidence_through_the_api_without_advancing_the_demand(): void
    {
        [$organization, $manager, $demand] = $this->workspace();
        $response = $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/demands/{$demand->id}/delivery-evidences", [
                'outcome' => 'published',
                'evidence_url' => 'https://mix7.example.test/campaign',
                'occurred_at' => '2026-09-26T15:30:00Z',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.demand_id', $demand->id)
            ->assertJsonPath('data.outcome', 'published')
            ->assertJsonPath('data.evidence_url', 'https://mix7.example.test/campaign')
            ->assertJsonPath('data.recorded_by', $manager->id);

        $evidence = DemandDeliveryEvidence::query()->firstOrFail();
        $this->assertSame($organization->id, $evidence->organization_id);
        $this->assertSame($manager->id, $evidence->recorded_by);
        $this->assertSame(DemandStatus::Delivery, $demand->fresh()->status);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $manager->id,
            'event_type' => 'delivery_evidence_recorded',
        ]);
    }

    public function test_api_delivery_evidence_requires_management_and_a_valid_delivery_stage_and_reference(): void
    {
        [$organization, $manager, $demand, $professional] = $this->workspace();
        $payload = ['outcome' => 'scheduled', 'details' => 'Agendamento sintético.'];

        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/demands/{$demand->id}/delivery-evidences", $payload)->assertForbidden();

        $demand->update(['status' => DemandStatus::InProgress]);
        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/demands/{$demand->id}/delivery-evidences", $payload)
            ->assertConflict();

        $demand->update(['status' => DemandStatus::Completed]);
        $this->postJson("/api/v1/demands/{$demand->id}/delivery-evidences", ['outcome' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['evidence_url', 'details']);

        $this->assertSame(0, DemandDeliveryEvidence::query()->count());
        $this->assertSame(0, DemandEvent::query()->where('event_type', 'delivery_evidence_recorded')->count());
    }

    public function test_api_delivery_evidence_cannot_cross_organization_boundaries(): void
    {
        [, , $demand] = $this->workspace();
        [, $otherManager] = $this->workspace('outside');

        $this->authenticate($otherManager->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/demands/{$demand->id}/delivery-evidences", [
                'outcome' => 'delivered',
                'details' => 'Registro fora da organização.',
            ])
            ->assertForbidden();

        $this->assertSame(0, DemandDeliveryEvidence::query()->count());
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => 'Campanha de teste',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::Delivery,
        ]);

        return [$organization, $manager, $demand, $professional];
    }

    private function authenticate(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
