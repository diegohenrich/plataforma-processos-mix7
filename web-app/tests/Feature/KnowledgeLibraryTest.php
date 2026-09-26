<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_manager_can_create_update_search_archive_and_restore_knowledge(): void
    {
        [$organization, $owner, $manager, $professional] = $this->team();
        $this->actingAs($owner)->post(route('knowledge.store'), ['type' => 'training', 'title' => 'Guia de marca', 'content' => 'Usar a paleta aprovada.', 'owner_name' => 'Direção', 'audience' => 'Design', 'review_due_at' => '2027-01-15', 'url' => 'https://example.test/brand', 'steps' => ['Revisar cores']])->assertRedirect(route('knowledge.index'));
        $item = KnowledgeItem::firstOrFail();
        $this->assertSame($organization->id, $item->organization_id);
        $this->assertSame($owner->id, $item->created_by);
        $this->actingAs($manager)->put(route('knowledge.update', $item), ['type' => 'reference', 'title' => 'Guia visual vigente', 'content' => 'Paleta azul Mix7.', 'owner_name' => 'Gerência', 'audience' => 'Equipe', 'review_due_at' => '2027-02-01', 'url' => 'https://example.test/brand'])->assertRedirect();
        $this->assertSame($manager->id, $item->fresh()->updated_by);
        $this->get(route('knowledge.index', ['q' => 'Paleta', 'type' => 'reference']))->assertOk()->assertSee('Guia visual vigente');
        $this->actingAs($owner)->delete(route('knowledge.archive', $item->fresh()))->assertRedirect();
        $this->get(route('knowledge.index'))->assertDontSee('Guia visual vigente');
        $this->get(route('knowledge.archived'))->assertSee('Guia visual vigente');
        $this->post(route('knowledge.restore', $item->fresh()))->assertRedirect();
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame($organization->id, $professional->organization_id);
    }

    public function test_onboarding_assignment_snapshots_steps_and_person_tracks_authenticated_progress(): void
    {
        [, $owner, $manager, $professional, $colleague] = $this->team();
        $item = $this->onboarding($owner);
        $this->actingAs($manager)->post(route('knowledge.assign', $item), ['user_id' => $professional->id])->assertRedirect();
        $this->from(route('knowledge.index'))->post(route('knowledge.assign', $item), ['user_id' => $professional->id])->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('onboarding_assignments', 1);
        $assignment = OnboardingAssignment::firstOrFail();
        $item->update(['steps' => ['Alterada depois']]);
        $this->assertSame(['Bem-vindo', 'Ler processos'], $assignment->fresh()->steps);
        $step = $assignment->steps()->firstOrFail();
        $this->actingAs($colleague)->patch(route('knowledge.assignment-step', [$assignment, $step]))->assertForbidden();
        $this->actingAs($professional)->patch(route('knowledge.assignment-step', [$assignment, $step]))->assertRedirect();
        $this->assertSame($professional->id, $step->fresh()->completed_by);
        $this->actingAs($professional)->patch(route('knowledge.assignment-step', [$assignment, $step]))->assertRedirect();
        $this->assertNull($step->fresh()->completed_at);
        $this->actingAs($owner)->patch(route('knowledge.assignment-step', [$assignment, $step]))->assertRedirect();
        $this->assertSame($owner->id, $step->fresh()->completed_by);
    }

    public function test_access_is_scoped_to_active_internal_users_and_organization(): void
    {
        [$organization, $owner, $manager, $professional] = $this->team();
        $item = $this->onboarding($owner);
        [$otherOrganization, $outsideOwner] = $this->team('outside');
        $this->actingAs($professional)->get(route('knowledge.index'))->assertOk()->assertSee('Mapa de briefing');
        $this->actingAs($outsideOwner)->put(route('knowledge.update', $item), ['type' => 'reference', 'title' => 'Invadir', 'content' => 'x'])->assertForbidden();
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $this->actingAs($client)->get(route('knowledge.index'))->assertForbidden();
        $inactive = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => false]);
        $this->actingAs($inactive)->get(route('knowledge.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->from(route('knowledge.index'))->post(route('knowledge.assign', $item), ['user_id' => $outsideOwner->id])->assertSessionHasErrors('user_id');
        $this->assertNotSame($otherOrganization->id, $owner->organization_id);
    }

    public function test_onboarding_requires_steps_and_rejects_unsafe_links(): void
    {
        [, $owner] = $this->team();
        $this->actingAs($owner)->from(route('knowledge.index'))->post(route('knowledge.store'), ['type' => 'onboarding', 'title' => 'Primeira semana', 'content' => 'Percurso inicial', 'steps' => [' ', '']])->assertSessionHasErrors('steps');
        $this->assertDatabaseCount('knowledge_items', 0);
        $this->post(route('knowledge.store'), ['type' => 'contact', 'title' => 'Fornecedor', 'content' => 'Contato autorizado.', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_professional_sees_only_their_assigned_onboarding_in_the_library(): void
    {
        [, $owner, , $professional, $colleague] = $this->team();
        $item = $this->onboarding($owner);
        $this->actingAs($owner)->post(route('knowledge.assign', $item), ['user_id' => $professional->id])->assertRedirect();
        $this->actingAs($owner)->post(route('knowledge.assign', $item), ['user_id' => $colleague->id])->assertRedirect();

        $professionalResponse = $this->actingAs($professional)->get(route('knowledge.index'))->assertOk()->assertSee('Sua trilha: Mapa de briefing')->assertSee('Bem-vindo')->assertSee('Pendente');
        $colleagueResponse = $this->actingAs($colleague)->get(route('knowledge.index'))->assertOk()->assertSee('Sua trilha: Mapa de briefing')->assertSee('Bem-vindo')->assertSee('Pendente');
        $this->assertDatabaseHas('onboarding_assignments', ['assigned_to' => $professional->id, 'knowledge_item_id' => $item->id]);
        $this->assertDatabaseHas('onboarding_assignments', ['assigned_to' => $colleague->id, 'knowledge_item_id' => $item->id]);
    }

    private function team(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $owner, $manager, $professional, $colleague];
    }

    private function onboarding(User $owner): KnowledgeItem
    {
        return KnowledgeItem::create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'type' => 'onboarding', 'title' => 'Mapa de briefing', 'content' => 'Como registrar uma solicitação.', 'owner_name' => $owner->name, 'audience' => 'Equipe Mix7', 'steps' => ['Bem-vindo', 'Ler processos']]);
    }
}
