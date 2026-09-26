<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_sees_only_organization_activity_with_defined_measures(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $owner, $manager, $professional, $colleague] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $this->task($demand, $professional, 'Preparar página', TaskStatus::Todo, 60);
        $this->task($demand, $professional, 'Revisar conteúdo', TaskStatus::Blocked, 30);
        $completed = $this->task($demand, $professional, 'Publicar versão', TaskStatus::Completed, 45);
        $completed->update(['completed_at' => CarbonImmutable::now()->subDays(4)]);
        $this->task($demand, $colleague, 'Tarefa da colega', TaskStatus::InProgress, 120);
        TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $completed->id,
            'user_id' => $professional->id,
            'started_at' => CarbonImmutable::now()->subMinutes(45),
            'ended_at' => CarbonImmutable::now()->subMinutes(15),
        ]);
        [$outsideOrganization] = $this->workspace('outside');
        $outsideProfessional = User::factory()->create(['organization_id' => $outsideOrganization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $outsideDemand = $this->demand($outsideOrganization, $outsideProfessional);
        $this->task($outsideDemand, $outsideProfessional, 'Demanda de outra organização', TaskStatus::InProgress);

        $this->actingAs($manager)->get(route('team.activity'))
            ->assertOk()->assertSee('Produção da equipe')->assertSee($professional->name)
            ->assertSee('2,0 h')->assertSee('0,5 h')->assertSee('concluídas em 30 dias')
            ->assertSee('não são nota, ranking')->assertSee($colleague->name)->assertDontSee('Tarefa da colega')->assertDontSee($outsideProfessional->name);
        $this->get(route('dashboard'))->assertOk()->assertSee('Produção da equipe');
        $this->actingAs($owner)->get(route('team.activity'))->assertOk();
        $this->actingAs($manager)->get(route('team.index'))->assertForbidden();
    }

    public function test_professional_sees_own_tasks_and_timer_controls_but_not_colleagues(): void
    {
        [$organization, $owner, , $professional, $colleague] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $this->task($demand, $professional, 'Minha tarefa', TaskStatus::Todo);
        $this->task($demand, $colleague, 'Tarefa privada da colega', TaskStatus::InProgress);

        $this->actingAs($professional)->get(route('team.activity'))
            ->assertOk()->assertSee('Meu trabalho')->assertSee('Minha tarefa')->assertSee('Iniciar tempo')->assertDontSee('Tarefa privada da colega');
        $this->get(route('dashboard'))->assertOk()->assertSee('Meu trabalho')->assertSee('Abrir minhas tarefas');
        $this->actingAs($professional)->get(route('team.index'))->assertForbidden();
    }

    public function test_client_cannot_access_activity_dashboard(): void
    {
        [$organization, , , , , $client] = $this->workspace();

        $this->actingAs($client)->get(route('team.activity'))->assertForbidden();
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        return [$organization, $owner, $manager, $professional, $colleague, $client];
    }

    private function demand(Organization $organization, User $creator): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::InProgress,
        ]);
    }

    private function task(Demand $demand, User $assignee, string $title, TaskStatus $status, ?int $estimateMinutes = null): DemandTask
    {
        return $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $demand->created_by,
            'assigned_to' => $assignee->id,
            'title' => $title,
            'status' => $status,
            'estimate_minutes' => $estimateMinutes,
        ]);
    }
}
