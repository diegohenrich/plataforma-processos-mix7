<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandReviewLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_one_time_visible_version_link_without_storing_plain_token(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/site-v1',
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        $url = $response->getSession()->get('review_link_url');
        $this->assertNotEmpty($url);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(64, strlen($token));
        $link = $demand->reviewLinks()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertStringNotContainsString($token, $link->token_hash);

        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Site institucional')
            ->assertSee('https://preview.example.test/site-v1')
            ->assertDontSee('Briefing privado do teste');
    }

    public function test_client_can_comment_then_request_changes_and_demand_enters_adjustments(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'comment', 'comment' => 'A cor ficou boa.'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::ClientApproval, $demand->fresh()->status);

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'changes_requested', 'comment' => 'Ajustar o título principal.'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::Adjustments, $demand->fresh()->status);
        $this->assertDatabaseCount('demand_review_responses', 2);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'event_type' => 'client_review_changes_requested', 'to_status' => DemandStatus::Adjustments->value]);
        $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()->assertSee('Respondida')->assertSee('Ajustar o título principal.');
        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Esta versão já recebeu uma decisão final')
            ->assertSee('Pediu ajustes')
            ->assertDontSee('Enviar comentário');
    }

    public function test_approval_advances_demand_and_prevents_another_decision(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'approved'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::Delivery, $demand->fresh()->status);
        $this->assertDatabaseHas('demand_review_responses', ['type' => 'approved', 'comment' => null]);
        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'changes_requested', 'comment' => 'Mudar'])
            ->assertStatus(410);
        $this->assertDatabaseCount('demand_review_responses', 1);
    }

    public function test_expired_revoked_and_previous_version_links_cannot_be_opened_or_answered(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $firstToken = $this->createLink($manager, $demand, 'https://preview.example.test/v1');
        $firstLink = $demand->reviewLinks()->firstOrFail();
        $secondToken = $this->createLink($manager, $demand, 'https://preview.example.test/v2');
        $this->assertNotNull($firstLink->fresh()->revoked_at);
        $this->get(route('client-reviews.show', $firstToken))->assertStatus(410);

        $secondLink = $demand->reviewLinks()->where('version', 2)->firstOrFail();
        $secondLink->update(['expires_at' => now()->subMinute()]);
        $this->get(route('client-reviews.show', $secondToken))->assertStatus(410);
        $this->post(route('client-reviews.respond', $secondToken), ['reviewer_name' => 'Cliente', 'type' => 'approved'])->assertStatus(410);
    }

    public function test_only_agency_manager_can_create_or_revoke_links_and_creation_requires_approval_stage(): void
    {
        [$organization, $manager, $demand, $professional] = $this->setupApproval();
        $this->actingAs($professional)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/v1', 'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertForbidden();

        $demand->update(['status' => DemandStatus::InProgress]);
        $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/v1', 'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('demand_review_links', 0);
    }

    public function test_manager_can_revoke_link_and_invalid_material_url_is_rejected(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');
        $link = $demand->reviewLinks()->firstOrFail();

        $this->actingAs($manager)->delete(route('demand-reviews.revoke', [$demand, $link]))->assertRedirect();
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->get(route('client-reviews.show', $token))->assertStatus(410)->assertSee('Este link não está mais disponível');

        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('demand-reviews.store', $demand), [
            'material_url' => 'javascript:alert(1)',
            'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertSessionHasErrors('material_url');
        $this->assertDatabaseCount('demand_review_links', 1);
    }

    private function setupApproval(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing privado do teste',
            'status' => DemandStatus::ClientApproval,
        ]);

        return [$organization, $manager, $demand, $professional];
    }

    private function createLink(User $manager, Demand $demand, string $materialUrl): string
    {
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => $materialUrl,
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        return basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
    }
}
