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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiTaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_schedule_and_transfer_a_task_and_the_active_timer_is_closed(): void
    {
        [$organization, $manager, $professional, $nextProfessional] = $this->workspace();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager);

        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/schedule", [
                'planned_start_on' => '2026-10-02',
                'planned_due_on' => '2026-10-07',
            ])
            ->assertOk()
            ->assertJsonPath('data.planned_start_on', '2026-10-02')
            ->assertJsonPath('data.planned_due_on', '2026-10-07');

        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/tasks/{$task->id}/timer/start")->assertOk();
        $entry = TaskTimeEntry::query()->firstOrFail();

        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/assignee", ['assignee_id' => $nextProfessional->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $nextProfessional->id)
            ->assertJsonPath('data.status', TaskStatus::Paused->value)
            ->assertJsonPath('data.planned_start_on', '2026-10-02');

        $this->assertSame($nextProfessional->id, $task->fresh()->assigned_to);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertNotNull($entry->fresh()->ended_at);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_schedule_updated',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'timer_paused',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_reassigned',
        ]);
    }

    public function test_task_schedule_and_assignment_reject_wrong_roles_invalid_dates_and_external_people(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->workspace();
        [, , $outsideProfessional] = $this->workspace('outside');
        $inactiveProfessional = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Professional,
            'is_active' => false,
        ]);
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager);

        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/schedule", [
                'planned_start_on' => '2026-10-08',
                'planned_due_on' => '2026-10-07',
            ])
            ->assertForbidden();
        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/schedule", [
                'planned_start_on' => '2026-10-08',
                'planned_due_on' => '2026-10-07',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('planned_due_on');
        $this->patchJson("/api/v1/tasks/{$task->id}/assignee", ['assignee_id' => $outsideProfessional->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
        $this->patchJson("/api/v1/tasks/{$task->id}/assignee", ['assignee_id' => $inactiveProfessional->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/assignee", ['assignee_id' => $colleague->id])
            ->assertForbidden();

        $this->assertNull($task->fresh()->planned_start_on);
        $this->assertSame($professional->id, $task->fresh()->assigned_to);
    }

    public function test_completed_task_keeps_its_assignee_and_other_organizations_cannot_manage_schedule(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        [, $outsideManager] = $this->workspace('outside');
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager);
        $task->update(['status' => TaskStatus::Completed]);

        $this->authenticate($manager->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/assignee", ['assignee_id' => $professional->id])
            ->assertConflict();
        $this->authenticate($outsideManager->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$task->id}/schedule", [
                'planned_start_on' => '2026-10-02',
                'planned_due_on' => '2026-10-07',
            ])
            ->assertForbidden();

        $this->assertSame($professional->id, $task->fresh()->assigned_to);
        $this->assertNull($task->fresh()->planned_start_on);
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $nextProfessional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $owner, $professional, $nextProfessional];
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

    private function task(Demand $demand, User $assignee, User $creator): DemandTask
    {
        return $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'title' => 'Criar páginas',
            'status' => TaskStatus::Todo,
        ]);
    }

    private function authenticate(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
