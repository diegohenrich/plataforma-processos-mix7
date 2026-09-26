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

    public function test_management_can_create_update_archive_and_restore_knowledge_with_audit_identity(): void
    {
        [, $owner, , , $manager] = $this->teamWithManager();
        $payload = ['type' => 'training', 'title' => ' Guia de marca ', 'content' => ' Paleta aprovada ', 'owner_name' => 'Direção', 'audience' => 'Design', 'review_due_at' => '2027-01-15', 'url' => 'https://example.test/brand', 'steps' => [' Revisar cores ', '']];

        $created = $this->actingAs($owner)->postJson('/api/v1/knowledge', $payload)
            ->assertCreated()->assertJsonPath('data.title', 'Guia de marca')->assertJsonPath('data.steps', ['Revisar cores']);
        $itemId = $created->json('data.id');
        $this->assertDatabaseHas('knowledge_items', ['id' => $itemId, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'organization_id' => $owner->organization_id]);

        $this->actingAs($manager)->putJson("/api/v1/knowledge/{$itemId}", [...$payload, 'type' => 'reference', 'title' => 'Guia vigente', 'content' => 'Nova versão'])
            ->assertOk()->assertJsonPath('data.title', 'Guia vigente')->assertJsonPath('data.steps', null);
        $this->assertDatabaseHas('knowledge_items', ['id' => $itemId, 'created_by' => $owner->id, 'updated_by' => $manager->id]);

        $this->actingAs($manager)->deleteJson("/api/v1/knowledge/{$itemId}")->assertOk()->assertJsonPath('data.id', $itemId);
        $this->assertNotNull(KnowledgeItem::findOrFail($itemId)->archived_at);
        $this->actingAs($owner)->postJson("/api/v1/knowledge/{$itemId}/restore")->assertOk()->assertJsonPath('data.title', 'Guia vigente');
        $this->assertNull(KnowledgeItem::findOrFail($itemId)->archived_at);
    }

    public function test_knowledge_management_is_limited_to_management_and_own_organization(): void
    {
        [, $owner, $professional] = $this->team();
        $item = $this->item($owner, 'reference', 'Manual interno');
        $other = $this->team('outside');
        $foreignItem = $this->item($other[1], 'reference', 'Outro manual');

        $this->actingAs($professional)->postJson('/api/v1/knowledge', ['type' => 'reference', 'title' => 'Inválido', 'content' => 'x'])->assertForbidden();
        $this->actingAs($owner)->putJson("/api/v1/knowledge/{$foreignItem->id}", ['type' => 'reference', 'title' => 'Invadido', 'content' => 'x'])->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/v1/knowledge/{$foreignItem->id}")->assertForbidden();
        $this->actingAs($owner)->postJson('/api/v1/knowledge', ['type' => 'onboarding', 'title' => 'Trilha vazia', 'content' => 'Sem etapas', 'steps' => [' ', '']])->assertUnprocessable();
        $this->assertDatabaseHas('knowledge_items', ['id' => $item->id, 'title' => 'Manual interno']);
        $this->assertDatabaseHas('knowledge_items', ['id' => $foreignItem->id, 'title' => 'Outro manual']);
    }

    public function test_management_can_assign_onboarding_only_to_active_professionals_in_organization(): void
    {
        [$organization, $owner, $professional] = $this->team();
        $item = $this->item($owner, 'onboarding', 'Primeiros passos', ['Ler guia', 'Conhecer fluxo']);

        $response = $this->actingAs($owner)->postJson("/api/v1/knowledge/{$item->id}/assignments", ['user_id' => $professional->id])
            ->assertCreated()->assertJsonPath('data.assigned_to', $professional->id)->assertJsonPath('data.assigned_by', $owner->id)->assertJsonPath('data.total_steps', 2);
        $assignmentId = $response->json('data.id');
        $this->assertDatabaseHas('onboarding_assignments', ['id' => $assignmentId, 'organization_id' => $organization->id, 'knowledge_item_id' => $item->id]);
        $this->assertDatabaseCount('onboarding_assignment_steps', 2);

        $this->actingAs($owner)->postJson("/api/v1/knowledge/{$item->id}/assignments", ['user_id' => $professional->id])->assertUnprocessable();
        $outside = $this->team('outside');
        $this->actingAs($owner)->postJson("/api/v1/knowledge/{$item->id}/assignments", ['user_id' => $outside[2]->id])->assertUnprocessable();
        $this->assertDatabaseCount('onboarding_assignments', 1);
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

    private function teamWithManager(): array
    {
        [$organization, $owner, $professional, $colleague] = $this->team();
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);

        return [$organization, $owner, $professional, $colleague, $manager];
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
