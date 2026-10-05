<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_professional_receives_a_private_notification_and_can_open_the_demand(): void
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $unassignedProfessional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        $this->actingAs($manager)->post(route('demands.store'), [
            'title' => 'Página da campanha',
            'brief' => 'Preparar uma página para a campanha de lançamento.',
            'module_key' => 'website_review',
            'tasks' => [
                ['title' => 'Preparar a estrutura', 'assignee_id' => $professional->id, 'estimate_minutes' => 90],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $demand = Demand::query()->firstOrFail();
        $notification = $professional->notifications()->firstOrFail();
        $this->assertSame('task_assigned', $notification->data['type']);
        $this->assertSame($demand->id, $notification->data['demand_id']);
        $this->assertSame($demand->tasks()->firstOrFail()->id, $notification->data['task_id']);
        $this->assertSame('Preparar a estrutura', $notification->data['task_title']);
        $this->assertSame($manager->name, $notification->data['assigned_by']);
        $this->assertSame(0, $unassignedProfessional->notifications()->count());

        $this->actingAs($professional)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Atribuições de tarefas')
            ->assertSee('Preparar a estrutura')
            ->assertSee('Página da campanha');
        $this->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('demands.show', $demand));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(DemandStatus::Received, $demand->fresh()->status);
    }
}
