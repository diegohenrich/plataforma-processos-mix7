<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_review_notifies_only_active_internal_people_who_can_access_the_demand(): void
    {
        [$organization, $owner, $demand, $assignedProfessional] = $this->setupDemand();
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $unassignedProfessional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $inactiveManager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => false]);
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'outra-agencia']);
        $otherOwner = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $token = $this->createLink($owner, $demand);

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'changes_requested',
            'comment' => 'Rever a chamada da página.',
        ])->assertRedirect();

        foreach ([$owner, $manager, $assignedProfessional] as $recipient) {
            $notification = $recipient->notifications()->firstOrFail();
            $this->assertSame($demand->id, $notification->data['demand_id']);
            $this->assertSame('changes_requested', $notification->data['response_type']);
            $this->assertSame('Rever a chamada da página.', $notification->data['comment']);
            $this->assertNull($notification->read_at);
        }

        foreach ([$unassignedProfessional, $inactiveManager, $otherOwner] as $recipient) {
            $this->assertSame(0, $recipient->notifications()->count());
        }
    }

    public function test_notification_inbox_marks_own_item_read_and_opens_only_an_authorized_demand(): void
    {
        [$organization, $owner, $demand] = $this->setupDemand();
        $token = $this->createLink($owner, $demand);
        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'comment',
            'comment' => 'O material está claro.',
        ])->assertRedirect();

        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($owner)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notificações')
            ->assertSee('comentou sobre a versão')
            ->assertSee('O material está claro.')
            ->assertSee('Marcar todas como lidas');

        $this->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('demands.show', $demand));
        $this->assertNotNull($notification->fresh()->read_at);

        $otherOwner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $this->actingAs($otherOwner)->get(route('notifications.open', $notification->id))->assertNotFound();
    }

    private function setupDemand(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $assignedProfessional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::ClientApproval,
        ]);
        DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $owner->id,
            'assigned_to' => $assignedProfessional->id,
            'title' => 'Revisar página',
            'status' => TaskStatus::Todo,
        ]);

        return [$organization, $owner, $demand, $assignedProfessional];
    }

    private function createLink(User $owner, Demand $demand): string
    {
        $response = $this->actingAs($owner)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/site-v1',
            'expires_at' => now()->addDays(2)->toIso8601String(),
        ])->assertRedirect();

        return basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
    }
}
