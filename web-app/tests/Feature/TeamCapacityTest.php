<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_record_weekly_hours_and_absences_and_see_estimated_due_workload(): void
    {
        [$organization, $owner, $manager, $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $this->task($demand, $manager, $professional, 'Criar página', TaskStatus::Todo, 120, '2026-09-24');
        $this->task($demand, $manager, $professional, 'Conferir texto', TaskStatus::Todo, null, '2026-09-25');
        $this->task($demand, $manager, $professional, 'Tarefa sem prazo', TaskStatus::Todo, 60, null);
        $this->task($demand, $manager, $professional, 'Já concluída', TaskStatus::Completed, 30, '2026-09-26');

        $week = '2026-W39';
        $this->actingAs($manager)->get(route('team.capacity', ['week' => $week, 'professional_id' => $professional->id]))
            ->assertOk()->assertSee('Não informadas')->assertSee('Previsão manual');

        $this->post(route('team.capacity.schedule'), [
            'week' => $week, 'professional_id' => $professional->id, 'scheduled_hours' => '20',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('team.capacity.absences.store'), [
            'week' => $week, 'professional_id' => $professional->id, 'work_date' => '2026-09-23', 'absence_hours' => '2',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('team.capacity', ['week' => $week, 'professional_id' => $professional->id]))
            ->assertOk()->assertSee('18,00 h')->assertSee('2,00 h')->assertSee('16,00 h')
            ->assertSee('Criar página')->assertSee('Conferir texto')->assertSee('Sem estimativa')
            ->assertSee('Tarefa sem prazo')->assertDontSee('Já concluída');

        $this->assertDatabaseHas('team_capacity_snapshots', [
            'organization_id' => $organization->id,
            'professional_id' => $professional->id,
            'recorded_by' => $manager->id,
            'change_type' => 'absence_added',
        ]);
    }

    public function test_professionals_only_read_their_own_capacity_and_cannot_edit_it(): void
    {
        [$organization, $owner, $manager, $professional, $colleague] = $this->workspace();
        $firstDemand = $this->demand($organization, $owner, 'Demanda própria');
        $secondDemand = $this->demand($organization, $owner, 'Demanda da colega');
        $this->task($firstDemand, $manager, $professional, 'Minha tarefa', TaskStatus::Todo, 60, '2026-09-22');
        $this->task($secondDemand, $manager, $colleague, 'Tarefa da colega', TaskStatus::Todo, 60, '2026-09-22');
        $this->recordCapacity($organization, $manager, $professional, 2400);
        $this->recordCapacity($organization, $manager, $colleague, 3000);

        $this->actingAs($professional)->get(route('team.capacity', ['week' => '2026-W39', 'professional_id' => $colleague->id]))
            ->assertOk()->assertSee('Minha tarefa')->assertSee('Sua semana')->assertDontSee('Tarefa da colega')
            ->assertDontSee('Horas disponíveis nesta semana');

        $this->post(route('team.capacity.schedule'), [
            'week' => '2026-W39', 'professional_id' => $professional->id, 'scheduled_hours' => 20,
        ])->assertForbidden();

        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $this->actingAs($client)->get(route('team.capacity'))->assertForbidden();
    }

    public function test_absence_changes_append_history_and_cannot_exceed_available_hours(): void
    {
        [, , $manager, $professional] = $this->workspace();
        $week = '2026-W39';
        $this->actingAs($manager)->post(route('team.capacity.schedule'), [
            'week' => $week, 'professional_id' => $professional->id, 'scheduled_hours' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('team.capacity.absences.store'), [
            'week' => $week, 'professional_id' => $professional->id, 'work_date' => '2026-09-22', 'absence_hours' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->from(route('team.capacity', ['week' => $week, 'professional_id' => $professional->id]))
            ->post(route('team.capacity.absences.store'), [
                'week' => $week, 'professional_id' => $professional->id, 'work_date' => '2026-09-23', 'absence_hours' => 5,
            ])->assertSessionHasErrors('absence_hours');

        $absenceId = TeamCapacitySnapshot::latest('id')->firstOrFail()->absences[0]['id'];
        $this->delete(route('team.capacity.absences.destroy', $absenceId), [
            'week' => $week, 'professional_id' => $professional->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $history = TeamCapacitySnapshot::where('professional_id', $professional->id)->whereDate('week_start', '2026-09-21')->orderBy('id')->get();
        $this->assertSame(['availability_set', 'absence_added', 'absence_removed'], $history->pluck('change_type')->all());
        $this->assertSame([], $history->last()->absences);
        $this->assertSame(3, $history->count());
    }

    public function test_capacity_input_rejects_invalid_iso_weeks_and_absences_outside_the_selected_week(): void
    {
        [, , $manager, $professional] = $this->workspace();
        $this->actingAs($manager)->get(route('team.capacity', ['week' => '2026-W54']))->assertStatus(422);
        $this->post(route('team.capacity.schedule'), [
            'week' => '2026-W39', 'professional_id' => $professional->id, 'scheduled_hours' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('team.capacity', ['week' => '2026-W39', 'professional_id' => $professional->id]))
            ->post(route('team.capacity.absences.store'), [
                'week' => '2026-W39', 'professional_id' => $professional->id, 'work_date' => '2026-09-28', 'absence_hours' => 1,
            ])->assertSessionHasErrors('work_date');
        $this->assertSame(1, TeamCapacitySnapshot::count());
    }

    public function test_management_cannot_read_or_write_capacity_for_another_organization(): void
    {
        [, , $manager] = $this->workspace();
        $outside = $this->workspace('outside');
        $outsideProfessional = $outside[3];

        $this->actingAs($manager)->get(route('team.capacity', ['week' => '2026-W39', 'professional_id' => $outsideProfessional->id]))
            ->assertNotFound();
        $this->post(route('team.capacity.schedule'), [
            'week' => '2026-W39', 'professional_id' => $outsideProfessional->id, 'scheduled_hours' => 16,
        ])->assertSessionHasErrors('professional_id');

        $this->assertSame(0, TeamCapacitySnapshot::count());
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => 'Mix7 '.$slug, 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $owner, $manager, $professional, $colleague];
    }

    private function demand(Organization $organization, User $owner, string $title = 'Demanda de teste'): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => $title,
            'brief' => 'Briefing fictício.',
            'status' => DemandStatus::InProgress,
        ]);
    }

    private function task(Demand $demand, User $manager, User $professional, string $title, TaskStatus $status, ?int $estimate, ?string $due): DemandTask
    {
        return DemandTask::create([
            'organization_id' => $demand->organization_id,
            'demand_id' => $demand->id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => $title,
            'status' => $status,
            'estimate_minutes' => $estimate,
            'planned_due_on' => $due,
        ]);
    }

    private function recordCapacity(Organization $organization, User $manager, User $professional, int $minutes): void
    {
        TeamCapacitySnapshot::create([
            'organization_id' => $organization->id,
            'professional_id' => $professional->id,
            'recorded_by' => $manager->id,
            'week_start' => '2026-09-21',
            'scheduled_minutes' => $minutes,
            'absences' => [],
            'change_type' => 'availability_set',
        ]);
    }
}
