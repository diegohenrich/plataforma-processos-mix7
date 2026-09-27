<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\TeamMemberEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiTeamMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_revoke_and_restore_member_access_through_api(): void
    {
        [$organization, $owner, $professional] = $this->workspace();
        $demand = Demand::create(['organization_id' => $organization->id, 'created_by' => $owner->id, 'title' => 'Demanda de teste', 'brief' => 'Sintético.', 'status' => DemandStatus::InProgress]);
        $task = DemandTask::create(['organization_id' => $organization->id, 'demand_id' => $demand->id, 'created_by' => $owner->id, 'assigned_to' => $professional->id, 'title' => 'Tarefa ativa', 'status' => TaskStatus::InProgress]);
        $entry = TaskTimeEntry::create(['organization_id' => $organization->id, 'task_id' => $task->id, 'user_id' => $professional->id, 'started_at' => now()->subMinute()]);
        $token = $professional->createToken('desktop')->plainTextToken;

        $this->actingAs($owner)->patchJson("/api/v1/team/members/{$professional->id}/access")
            ->assertOk()->assertJsonPath('data.id', $professional->id)->assertJsonPath('data.is_active', false)->assertJsonPath('data.access', 'revoked');
        $this->assertNotNull($entry->fresh()->ended_at);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $professional->id]);
        $this->assertDatabaseHas('team_member_events', ['member_id' => $professional->id, 'actor_id' => $owner->id, 'event_type' => 'access_revoked']);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $owner->id, 'event_type' => 'timer_paused']);

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->actingAs($owner)->patchJson("/api/v1/team/members/{$professional->id}/access")
            ->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.access', 'restored');
        $this->assertDatabaseHas('team_member_events', ['member_id' => $professional->id, 'actor_id' => $owner->id, 'event_type' => 'access_restored']);
    }

    public function test_only_owner_can_change_access_for_same_organization_members_but_not_self_or_other_organizations(): void
    {
        [$organization, $owner, $professional, $client, $manager] = $this->workspace();
        [, , $outsideProfessional] = $this->workspace('outside');

        $this->actingAs($manager)->patchJson("/api/v1/team/members/{$professional->id}/access")->assertForbidden();
        $this->actingAs($professional)->patchJson("/api/v1/team/members/{$client->id}/access")->assertForbidden();
        $this->actingAs($owner)->patchJson("/api/v1/team/members/{$owner->id}/access")->assertForbidden();
        $this->actingAs($owner)->patchJson("/api/v1/team/members/{$manager->id}/access")
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.access', 'revoked');
        $this->actingAs($owner)->patchJson("/api/v1/team/members/{$outsideProfessional->id}/access")->assertNotFound();

        $this->assertTrue($professional->fresh()->is_active);
        $this->assertTrue($client->fresh()->is_active);
        $this->assertFalse($manager->fresh()->is_active);
        $this->assertDatabaseHas('team_member_events', [
            'member_id' => $manager->id,
            'actor_id' => $owner->id,
            'event_type' => 'access_revoked',
        ]);
        $this->assertSame(1, TeamMemberEvent::query()->count());
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
