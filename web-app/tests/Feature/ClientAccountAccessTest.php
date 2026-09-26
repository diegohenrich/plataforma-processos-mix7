<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_client_account_and_assigns_a_demand_with_audited_author(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();
        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Cliente Exemplo',
            'email' => 'CLIENTE@EXEMPLO.COM',
            'role' => UserRole::Client->value,
        ])->assertRedirect(route('team.index'))->assertSessionHasNoErrors();

        $invitation = TeamInvitation::query()->where('email', 'cliente@exemplo.com')->firstOrFail();
        $token = null;
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function (TeamInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertDatabaseMissing('users', ['email' => 'cliente@exemplo.com']);
        $this->get(route('team-invitations.show', $token))->assertOk()->assertSee('Ative sua conta');
        $this->post(route('team-invitations.accept', $token), [
            'password' => 'senha-cliente-segura',
            'password_confirmation' => 'senha-cliente-segura',
        ])->assertRedirect(route('dashboard'));

        $client = User::query()->where('email', 'cliente@exemplo.com')->firstOrFail();
        $this->assertSame($organization->id, $client->organization_id);
        $this->assertSame(UserRole::Client, $client->role);
        $this->assertTrue(Hash::check('senha-cliente-segura', $client->password));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->post(route('team-invitations.accept', $token), [
            'password' => 'senha-cliente-segura',
            'password_confirmation' => 'senha-cliente-segura',
        ])->assertSessionHasErrors('invitation');

        $demand = $this->demand($organization, $owner, ['client_user_id' => $client->id]);
        $this->actingAs($client)->get(route('demands.index'))->assertOk()->assertSee('Site institucional');
    }

    public function test_client_sees_only_own_demand_status_without_internal_brief_tasks_or_history(): void
    {
        [$organization, $owner] = $this->workspace();
        $client = $this->client($organization, 'cliente@example.test');
        $otherClient = $this->client($organization, 'outra@example.test');
        $ownDemand = $this->demand($organization, $owner, ['client_user_id' => $client->id]);
        $this->demand($organization, $owner, ['title' => 'Demanda de outra conta', 'client_user_id' => $otherClient->id]);
        $this->demand($organization, $owner, ['title' => 'Demanda interna sem vínculo']);
        $task = $ownDemand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $owner->id, 'assigned_to' => $this->professional($organization)->id, 'title' => 'Tarefa estritamente interna', 'description' => 'Detalhe confidencial', 'status' => TaskStatus::Todo]);

        $this->actingAs($client)->get(route('demands.index'))
            ->assertOk()->assertSee('Site institucional')->assertDontSee('Demanda de outra conta')->assertDontSee('Demanda interna sem vínculo')->assertDontSee('Briefing estritamente interno');
        $this->get(route('demands.show', $ownDemand))->assertOk()
            ->assertSee('Etapa atual')->assertSee('Planejamento')
            ->assertDontSee('Briefing estritamente interno')->assertDontSee('Tarefa estritamente interna')->assertDontSee('Detalhe confidencial')->assertDontSee($owner->name);
        $this->get(route('demands.show', $task->demand))->assertOk();
        $this->get(route('demands.show', Demand::query()->where('title', 'Demanda de outra conta')->firstOrFail()))->assertForbidden();
        $this->patch(route('demands.status', $ownDemand), ['status' => DemandStatus::Planning->value])->assertForbidden();
        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::InProgress->value])->assertForbidden();
        $this->post(route('ai-agent.ask', $ownDemand), ['question' => 'Quais são as próximas etapas?'])->assertForbidden();
        $this->post(route('ai-planning.propose', $ownDemand))->assertForbidden();
        $this->get(route('knowledge.index'))->assertForbidden();
        $this->get(route('team.index'))->assertForbidden();
    }

    public function test_client_cannot_access_demand_in_another_organization_even_with_same_numeric_role(): void
    {
        [$organization, $owner] = $this->workspace();
        [$otherOrganization, $otherOwner] = $this->workspace('outside');
        $client = $this->client($organization, 'client@example.test');
        $outsideDemand = $this->demand($otherOrganization, $otherOwner, ['client_user_id' => $this->client($otherOrganization, 'outside-client@example.test')->id]);

        $this->actingAs($client)->get(route('demands.show', $outsideDemand))->assertForbidden();
        $token = $client->createToken('client-api')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/demands')->assertOk()->assertExactJson(['data' => [], 'meta' => ['limit' => 100]]);
    }

    public function test_client_api_returns_only_assigned_demand_names_and_stages(): void
    {
        [$organization, $owner] = $this->workspace();
        $client = $this->client($organization, 'client@example.test');
        $ownDemand = $this->demand($organization, $owner, ['client_user_id' => $client->id]);
        $this->demand($organization, $owner, ['title' => 'Privada de outro cliente', 'client_user_id' => $this->client($organization, 'other@example.test')->id]);
        $ownDemand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $owner->id, 'assigned_to' => $this->professional($organization)->id, 'title' => 'Tarefa escondida', 'status' => TaskStatus::Todo]);
        $token = $client->createToken('client-api')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/demands')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Site institucional')
            ->assertJsonMissingPath('data.0.brief')
            ->assertJsonMissingPath('data.0.tasks');
        $this->assertStringNotContainsString('Tarefa escondida', $response->getContent());
        $this->assertStringNotContainsString('Privada de outro cliente', $response->getContent());
        $this->withToken($token)->getJson('/api/v1/demands/'.$ownDemand->id)->assertOk()->assertJsonMissingPath('data.brief')->assertJsonMissingPath('data.tasks');
    }

    public function test_management_can_assign_only_active_client_from_same_organization_and_records_event(): void
    {
        [$organization, $owner] = $this->workspace();
        [$outsideOrganization] = $this->workspace('outside');
        $client = $this->client($organization, 'client@example.test');
        $outsideClient = $this->client($outsideOrganization, 'outside@example.test');
        $professional = $this->professional($organization);
        $inactiveClient = $this->client($organization, 'inactive@example.test', false);
        $demand = $this->demand($organization, $owner);

        $this->actingAs($owner)->patch(route('demands.client.assign', $demand), ['client_user_id' => $client->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($client->id, $demand->fresh()->client_user_id);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $owner->id, 'event_type' => 'demand_client_assigned', 'summary' => 'Demanda vinculada ao cliente '.$client->name]);

        foreach ([$outsideClient->id, $professional->id, $inactiveClient->id] as $invalidId) {
            $this->patch(route('demands.client.assign', $demand), ['client_user_id' => $invalidId])->assertSessionHasErrors('client_user_id');
        }
        $this->patch(route('demands.client.assign', $demand), ['client_user_id' => null])->assertRedirect();
        $this->assertNull($demand->fresh()->client_user_id);
    }

    public function test_new_demand_rejects_client_account_from_another_organization(): void
    {
        [$organization, $owner] = $this->workspace();
        [$outsideOrganization] = $this->workspace('outside');
        $professional = $this->professional($organization);
        $outsideClient = $this->client($outsideOrganization, 'outside@example.test');

        $this->actingAs($owner)->from(route('demands.create'))->post(route('demands.store'), [
            'title' => 'Site institucional',
            'brief' => 'Briefing interno.',
            'module_key' => 'website_review',
            'client_user_id' => $outsideClient->id,
            'tasks' => [['title' => 'Planejar', 'assignee_id' => $professional->id]],
        ])->assertSessionHasErrors('client_user_id');
        $this->assertDatabaseCount('demands', 0);
    }

    public function test_new_demand_can_be_created_for_an_active_client_in_the_same_organization(): void
    {
        [$organization, $owner] = $this->workspace();
        $client = $this->client($organization, 'cliente@example.test');
        $professional = $this->professional($organization);

        $this->actingAs($owner)->post(route('demands.store'), [
            'title' => 'Campanha institucional',
            'brief' => 'Briefing aprovado pela gerência.',
            'module_key' => 'social_creative',
            'client_user_id' => $client->id,
            'tasks' => [['title' => 'Planejar campanha', 'assignee_id' => $professional->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $demand = Demand::query()->where('title', 'Campanha institucional')->firstOrFail();
        $this->assertSame($client->id, $demand->client_user_id);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $owner->id,
            'event_type' => 'demand_client_assigned',
        ]);
        $this->actingAs($client)->get(route('demands.show', $demand))->assertOk()->assertSee('Campanha institucional');
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);

        return [$organization, $owner];
    }

    private function client(Organization $organization, string $email, bool $active = true): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'email' => $email, 'role' => UserRole::Client, 'is_active' => $active]);
    }

    private function professional(Organization $organization): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
    }

    private function demand(Organization $organization, User $owner, array $overrides = []): Demand
    {
        return Demand::create(array_merge([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing estritamente interno.',
            'status' => DemandStatus::Planning,
        ], $overrides));
    }
}
