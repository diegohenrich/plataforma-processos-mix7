<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_users_can_read_active_organization_knowledge_and_clients_cannot(): void
    {
        [$organization, $owner, $professional] = $this->team();
        $guide = $this->item($owner, 'reference', 'Guia de briefing');
        $this->item($owner, 'training', 'Treinamento de marca');
        $outside = $this->team('outside');
        $this->item($outside[1], 'reference', 'Segredo de outra agência');
        $guide->update(['archived_at' => now()]);

        $this->actingAs($owner)->getJson('/api/v1/knowledge')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Treinamento de marca');
        $this->actingAs($professional)->getJson('/api/v1/knowledge?type=reference')
            ->assertOk()->assertJsonCount(0, 'data');

        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $this->actingAs($client)->getJson('/api/v1/knowledge')->assertForbidden();
    }

    public function test_professional_can_read_and_update_only_their_own_onboarding_steps(): void
    {
        [, $owner, $professional, $colleague] = $this->team();
        $item = $this->item($owner, 'onboarding', 'Primeiros passos', ['Ler guia', 'Conhecer fluxo']);
        $assignment = $this->assign($item, $professional, $owner);
        $otherAssignment = $this->assign($item, $colleague, $owner);
        $ownStep = $assignment->steps()->firstOrFail();
        $otherStep = $otherAssignment->steps()->firstOrFail();

        $this->actingAs($professional)->getJson('/api/v1/onboarding/assignments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Primeiros passos')
            ->assertJsonPath('data.0.total_steps', 2);
        $this->patchJson("/api/v1/onboarding/assignments/{$assignment->id}/steps/{$ownStep->id}")
            ->assertOk()->assertJsonPath('data.id', $ownStep->id);
        $this->assertSame($professional->id, $ownStep->fresh()->completed_by);
        $this->patchJson("/api/v1/onboarding/assignments/{$otherAssignment->id}/steps/{$otherStep->id}")->assertNotFound();
        $this->assertNull($otherStep->fresh()->completed_at);
    }

    public function test_management_cannot_use_professional_onboarding_self_service_endpoint(): void
    {
        [, $owner, $professional] = $this->team();
        $item = $this->item($owner, 'onboarding', 'Primeiros passos', ['Ler guia']);
        $assignment = $this->assign($item, $professional, $owner);
        $step = $assignment->steps()->firstOrFail();

        $this->actingAs($owner)->getJson('/api/v1/onboarding/assignments')->assertForbidden();
        $this->actingAs($owner)->patchJson("/api/v1/onboarding/assignments/{$assignment->id}/steps/{$step->id}")->assertForbidden();
    }

    private function team(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $owner, $professional, $colleague];
    }

    private function item(User $owner, string $type, string $title, ?array $steps = null): KnowledgeItem
    {
        return KnowledgeItem::create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'type' => $type, 'title' => $title, 'content' => 'Conteúdo interno de teste.', 'steps' => $steps]);
    }

    private function assign(KnowledgeItem $item, User $professional, User $owner): OnboardingAssignment
    {
        $assignment = OnboardingAssignment::create(['organization_id' => $item->organization_id, 'knowledge_item_id' => $item->id, 'assigned_to' => $professional->id, 'assigned_by' => $owner->id, 'title' => $item->title, 'steps' => $item->steps]);
        foreach ($item->steps as $position => $title) {
            $assignment->steps()->create(['position' => $position + 1, 'title' => $title]);
        }

        return $assignment;
    }
}
