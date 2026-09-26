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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ApiTaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_start_pause_and_complete_an_assigned_task_through_the_api(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $owner, $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, $owner);
        $token = $professional->createToken('desktop')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/timer/start")
            ->assertOk()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.status', TaskStatus::InProgress->value)
            ->assertJsonPath('data.timer.started_at', '2026-09-26T12:00:00.000000Z');

        $this->travel(90)->seconds();
        $this->postJson("/api/v1/tasks/{$task->id}/timer/pause")
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Paused->value)
            ->assertJsonPath('data.timer.duration_seconds', 90);

        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::InProgress->value])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::InProgress->value);

        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Completed->value])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Completed->value)
            ->assertJsonPath('data.completed_at', '2026-09-26T12:01:30.000000Z');

        $this->assertDatabaseCount('task_time_entries', 1);
        $this->assertNotNull(TaskTimeEntry::query()->firstOrFail()->ended_at);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $professional->id,
            'event_type' => 'task_status_changed',
            'to_status' => TaskStatus::Completed->value,
        ]);
        Date::setTestNow();
    }

    public function test_api_timer_and_status_respect_assignment_role_and_organization_boundaries(): void
    {
        [$organization, $owner, $professional, $colleague, $client] = $this->workspace();
        [, $outsideOwner, $outsideProfessional] = $this->workspace('outside');
        $demand = $this->demand($organization, $owner);
        $ownTask = $this->task($demand, $professional, $owner);
        $colleagueTask = $this->task($demand, $colleague, $owner, 'Tarefa privada');
        $outsideDemand = $this->demand($outsideOwner->organization, $outsideOwner, 'Outra organização');
        $outsideTask = $this->task($outsideDemand, $outsideProfessional, $outsideOwner, 'Demanda externa');

        $this->authenticate($professional->createToken('desktop')->plainTextToken)
            ->postJson("/api/v1/tasks/{$ownTask->id}/timer/start")->assertOk();
        $this->postJson("/api/v1/tasks/{$colleagueTask->id}/timer/start")->assertForbidden();
        $this->postJson("/api/v1/tasks/{$outsideTask->id}/timer/start")->assertForbidden();
        $this->patchJson("/api/v1/tasks/{$colleagueTask->id}/status", ['status' => TaskStatus::InProgress->value])->assertForbidden();

        $this->authenticate($client->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$ownTask->id}/status", ['status' => TaskStatus::Paused->value])->assertForbidden();

        $this->authenticate($owner->createToken('desktop')->plainTextToken)
            ->patchJson("/api/v1/tasks/{$outsideTask->id}/status", ['status' => TaskStatus::InProgress->value])->assertForbidden();

        $this->assertSame(TaskStatus::Todo, $colleagueTask->fresh()->status);
        $this->assertSame(TaskStatus::Todo, $outsideTask->fresh()->status);
        $this->assertDatabaseCount('task_time_entries', 1);
    }

    public function test_api_rejects_invalid_transitions_dependencies_and_conflicting_timers(): void
    {
        [$organization, $owner, $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, $owner);
        $dependent = $this->task($demand, $professional, $owner, 'Tarefa dependente');
        $dependent->dependencies()->attach($task->id);
        $token = $professional->createToken('desktop')->plainTextToken;

        $this->authenticate($token)->postJson("/api/v1/tasks/{$dependent->id}/timer/start")
            ->assertConflict()
            ->assertJsonPath('errors.timer.0', 'Conclua as tarefas anteriores antes de iniciar esta tarefa.');
        $this->assertDatabaseCount('task_time_entries', 0);

        $this->postJson("/api/v1/tasks/{$task->id}/timer/start")->assertOk();
        $this->postJson("/api/v1/tasks/{$dependent->id}/timer/start")
            ->assertConflict()
            ->assertJsonPath('errors.timer.0', 'Pause sua tarefa atual antes de iniciar outra.');
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Todo->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('task_time_entries', 1);
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_api_can_recover_only_the_authenticated_professionals_abandoned_timer(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $owner, $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, $owner);
        $task->update(['status' => TaskStatus::InProgress]);
        $entry = TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'user_id' => $professional->id,
            'started_at' => CarbonImmutable::now()->subMinutes(5),
        ]);
        $token = $professional->createToken('desktop')->plainTextToken;

        $this->authenticate($token)->postJson('/api/v1/tasks/timer/recover')
            ->assertOk()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.status', TaskStatus::Paused->value)
            ->assertJsonPath('data.timer.id', $entry->id);

        $this->assertNotNull($entry->fresh()->ended_at);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $professional->id,
            'event_type' => 'timer_recovered',
        ]);

        $this->postJson('/api/v1/tasks/timer/recover')
            ->assertConflict()
            ->assertJsonValidationErrors('timer');
        $this->authenticate($owner->createToken('desktop')->plainTextToken)
            ->postJson('/api/v1/tasks/timer/recover')->assertForbidden();
        Date::setTestNow();
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        return [$organization, $owner, $professional, $colleague, $client];
    }

    private function authenticate(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    private function demand(Organization $organization, User $creator, string $title = 'Site institucional'): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => $title,
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::InProgress,
        ]);
    }

    private function task(Demand $demand, User $assignee, User $creator, string $title = 'Executar página'): DemandTask
    {
        return $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'title' => $title,
            'status' => TaskStatus::Todo,
        ]);
    }
}
