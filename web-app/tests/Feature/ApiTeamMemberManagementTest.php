<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTeamMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_organization_professionals_and_clients_with_access_state(): void
    {
        [$organization, $owner, $professional, $client] = $this->workspace();
        $professional->update(['is_active' => false]);
        $this->workspace('outside');

        $this->actingAs($owner)->getJson('/api/v1/team/members')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $professional->id, 'role' => 'professional', 'is_active' => false])
            ->assertJsonFragment(['id' => $client->id, 'role' => 'client', 'is_active' => true])
            ->assertJsonMissing(['id' => $owner->id]);
        $this->assertSame($organization->id, $professional->fresh()->organization_id);
    }

    public function test_owner_lists_only_live_invites_without_exposing_token_hash(): void
    {
        [$organization, $owner] = $this->workspace();
        $pending = TeamInvitation::create(['organization_id' => $organization->id, 'invited_by' => $owner->id, 'name' => 'Nova pessoa', 'email' => 'nova@example.test', 'role' => UserRole::Professional, 'token_hash' => hash('sha256', 'secret'), 'expires_at' => now()->addDay()]);
        TeamInvitation::create(['organization_id' => $organization->id, 'invited_by' => $owner->id, 'name' => 'Expirado', 'email' => 'expired@example.test', 'role' => UserRole::Client, 'token_hash' => hash('sha256', 'expired'), 'expires_at' => now()->subSecond()]);
        $outside = $this->workspace('outside');
        TeamInvitation::create(['organization_id' => $outside[0]->id, 'invited_by' => $outside[1]->id, 'name' => 'Outro', 'email' => 'other@example.test', 'role' => UserRole::Client, 'token_hash' => hash('sha256', 'outside'), 'expires_at' => now()->addDay()]);

        $this->actingAs($owner)->getJson('/api/v1/team/invitations')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)->assertJsonPath('data.0.email', 'nova@example.test')
            ->assertJsonMissingPath('data.0.token_hash')->assertJsonMissing(['email' => 'expired@example.test']);
    }

    public function test_only_owner_can_list_team_accounts_and_invitations(): void
    {
        [, , $professional, , $manager] = $this->workspace();

        $this->actingAs($manager)->getJson('/api/v1/team/members')->assertForbidden();
        $this->actingAs($manager)->getJson('/api/v1/team/invitations')->assertForbidden();
        $this->actingAs($professional)->getJson('/api/v1/team/members')->assertForbidden();
        $this->actingAs($professional)->getJson('/api/v1/team/invitations')->assertForbidden();
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);

        return [$organization, $owner, $professional, $client, $manager];
    }
}
