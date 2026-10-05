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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_deactivate_and_restore_professional_and_client_access(): void
    {
        [$organization, $owner, $professional, $client] = $this->workspace();
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'client_user_id' => $client->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::InProgress,
        ]);
        $task = DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $owner->id,
            'assigned_to' => $professional->id,
            'title' => 'Implementar página',
            'status' => TaskStatus::InProgress,
        ]);
        $entry = TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'user_id' => $professional->id,
            'started_at' => now()->subMinutes(7),
        ]);
        $token = $professional->createToken('desktop')->plainTextToken;
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
        DB::table('sessions')->insert([
            'id' => 'professional-session',
            'user_id' => $professional->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'synthetic-session',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($owner)->from(route('team.index'))->patch(route('team.members.access', $professional))
            ->assertRedirect(route('team.index'))->assertSessionHasNoErrors();

        $this->assertFalse($professional->fresh()->is_active);
        $this->assertNotNull($entry->fresh()->ended_at);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $professional->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'professional-session']);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $owner->id, 'event_type' => 'timer_paused']);
        $this->assertDatabaseHas('team_member_events', ['member_id' => $professional->id, 'actor_id' => $owner->id, 'event_type' => 'access_revoked']);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->actingAs($professional->fresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($owner)->from(route('team.index'))->patch(route('team.members.access', $professional))->assertRedirect(route('team.index'));
        $this->assertTrue($professional->fresh()->is_active);
        $this->assertSame(2, TeamMemberEvent::query()->where('member_id', $professional->id)->count());
        $this->assertDatabaseHas('team_member_events', ['member_id' => $professional->id, 'actor_id' => $owner->id, 'event_type' => 'access_restored']);
        $this->get(route('team.index'))->assertOk()->assertSee('Histórico da equipe')->assertSee('Acesso restaurado')->assertSee($owner->name);

        $this->from(route('team.index'))->patch(route('team.members.access', $client))->assertRedirect(route('team.index'));
        $this->assertFalse($client->fresh()->is_active);
        $this->actingAs($client->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_only_owner_can_change_access_for_same_organization_members_but_not_self_or_other_organizations(): void
    {
        [$organization, $owner, $professional, $client, $manager] = $this->workspace();
        [, , $outsideProfessional] = $this->workspace('outside');

        $this->actingAs($manager)->patch(route('team.members.access', $professional))->assertForbidden();
        $this->actingAs($owner)->patch(route('team.members.access', $outsideProfessional))->assertNotFound();
        $this->actingAs($owner)->patch(route('team.members.access', $owner))->assertForbidden();

        $this->assertTrue($professional->fresh()->is_active);
        $this->assertTrue($client->fresh()->is_active);
        $this->assertSame(0, TeamMemberEvent::query()->count());
    }

    public function test_owner_can_suspend_and_restore_a_manager_with_audit_events(): void
    {
        [, $owner, , , $manager] = $this->workspace();

        $this->actingAs($owner)->from(route('team.index'))->patch(route('team.members.access', $manager))
            ->assertRedirect(route('team.index'));

        $this->assertFalse($manager->fresh()->is_active);
        $this->assertDatabaseHas('team_member_events', [
            'member_id' => $manager->id,
            'actor_id' => $owner->id,
            'event_type' => 'access_revoked',
        ]);

        $this->actingAs($owner)->from(route('team.index'))->patch(route('team.members.access', $manager))
            ->assertRedirect(route('team.index'));

        $this->assertTrue($manager->fresh()->is_active);
        $this->assertDatabaseHas('team_member_events', [
            'member_id' => $manager->id,
            'actor_id' => $owner->id,
            'event_type' => 'access_restored',
        ]);
        $this->actingAs($owner)->get(route('team.index'))->assertOk()
            ->assertSee('Gerentes cadastrados')
            ->assertSee($manager->name);
    }

    public function test_owner_can_set_professional_specialties_and_other_roles_cannot(): void
    {
        [, $owner, $professional, , $manager] = $this->workspace();
        [, , $outsideProfessional] = $this->workspace('outside');

        $this->actingAs($owner)->patch(route('team.members.specialties', $professional), [
            'specialties' => ' Design, Edição de vídeo; design ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['Design', 'Edição de vídeo'], $professional->fresh()->specialties);
        $this->assertDatabaseHas('team_member_events', [
            'organization_id' => $professional->organization_id,
            'member_id' => $professional->id,
            'actor_id' => $owner->id,
            'event_type' => 'specialties_updated',
        ]);
        $this->actingAs($owner)->get(route('team.index'))->assertOk()->assertSee('Especialidades profissionais atualizadas')->assertSee($owner->name);
        $this->actingAs($manager)->patch(route('team.members.specialties', $professional), ['specialties' => 'Redação'])->assertForbidden();
        $this->actingAs($owner)->patch(route('team.members.specialties', $outsideProfessional), ['specialties' => 'Redação'])->assertNotFound();
        $this->actingAs($owner)->patch(route('team.members.specialties', $professional), ['specialties' => implode(',', range(1, 13))])->assertSessionHasErrors('specialties');
        $this->assertSame(['Design', 'Edição de vídeo'], $professional->fresh()->specialties);
    }

    public function test_management_keeps_inactive_professionals_open_work_visible(): void
    {
        [$organization, $owner, $professional] = $this->workspace();
        $professional->update(['is_active' => false]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => 'Demanda preservada',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::InProgress,
        ]);
        DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $owner->id,
            'assigned_to' => $professional->id,
            'title' => 'Trabalho ainda atribuído',
            'status' => TaskStatus::Paused,
        ]);

        $this->actingAs($owner)->get(route('team.activity'))
            ->assertOk()->assertSee($professional->name)->assertSee('Acesso desativado')->assertSee('1 tarefa aberta');
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
