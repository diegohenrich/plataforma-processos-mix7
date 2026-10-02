<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\ServiceAccess;
use App\Models\ServiceAccessRequest;
use App\Models\User;
use App\Services\ServiceAccessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_service_and_records_request_grant_revoke_and_history_without_credentials(): void
    {
        [$organization, $owner, $manager, $professional] = $this->team();
        $payload = $this->payload();

        $this->actingAs($owner)->post(route('service-access.store'), $payload)->assertRedirect(route('service-access.index'));
        $service = ServiceAccess::firstOrFail();
        $this->assertSame($organization->id, $service->organization_id);
        $this->assertDatabaseHas('service_access_events', ['service_access_id' => $service->id, 'event_type' => 'service_created', 'actor_id' => $owner->id]);
        $this->assertArrayNotHasKey('password', $service->getAttributes());
        $this->assertArrayNotHasKey('secret', $service->getAttributes());

        $this->actingAs($professional)->get(route('service-access.index'))
            ->assertOk()->assertSee('Acessos de serviços')->assertSee('Abrir serviço')->assertSee('não ficam aqui')->assertSee('Solicitar meu acesso');
        $this->actingAs($professional)->post(route('service-access.request', $service))->assertRedirect();
        $request = ServiceAccessRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame($professional->id, $request->user_id);

        $this->actingAs($owner)->get(route('service-access.index'))
            ->assertOk()->assertSee($professional->name)->assertSee('Aguardando')->assertSee('Registrar concessão');
        $this->actingAs($owner)->patch(route('service-access.decide', $request), ['status' => 'granted'])->assertRedirect();
        $this->assertDatabaseHas('service_access_requests', ['id' => $request->id, 'status' => 'granted', 'reviewed_by' => $owner->id]);
        $this->assertDatabaseHas('service_access_events', ['service_access_request_id' => $request->id, 'event_type' => 'access_granted']);
        $this->actingAs($owner)->post(route('service-access.revoke', $request))->assertRedirect();
        $this->assertDatabaseHas('service_access_requests', ['id' => $request->id, 'status' => 'revoked']);
        $this->assertDatabaseHas('service_access_events', ['service_access_request_id' => $request->id, 'event_type' => 'access_revoked']);

        $this->actingAs($professional)->post(route('service-access.request', $service))->assertRedirect();
        $this->assertDatabaseCount('service_access_requests', 2);
        $secondRequest = ServiceAccessRequest::query()->whereKeyNot($request->id)->firstOrFail();
        $this->actingAs($professional)->delete(route('service-access.withdraw', $secondRequest))->assertRedirect();
        $this->actingAs($owner)->delete(route('service-access.archive', $service))->assertRedirect();
        $this->assertNotNull($service->fresh()->archived_at);
        $this->actingAs($owner)->post(route('service-access.restore', $service))->assertRedirect();
        $this->assertNull($service->fresh()->archived_at);
        $this->assertSame(8, $service->events()->count());
    }

    public function test_professional_sees_only_their_own_access_requests_and_can_withdraw_pending(): void
    {
        [, $owner, , $professional, $colleague] = $this->team();
        $service = ServiceAccess::create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'updated_by' => $owner->id, ...$this->payload()]);
        $colleagueRequest = app(ServiceAccessManager::class)->request($service, $colleague);
        $ownRequest = app(ServiceAccessManager::class)->request($service, $professional);

        $this->actingAs($professional)->get(route('service-access.index'))
            ->assertOk()->assertSee('aguardando análise')->assertDontSee($colleague->name);
        $this->actingAs($professional)->delete(route('service-access.withdraw', $ownRequest))->assertRedirect();
        $this->assertDatabaseHas('service_access_requests', ['id' => $ownRequest->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('service_access_requests', ['id' => $colleagueRequest->id, 'status' => 'pending']);
        $this->actingAs($professional)->delete(route('service-access.withdraw', $colleagueRequest))->assertNotFound();
    }

    public function test_only_owner_can_manage_catalog_or_decide_and_archiving_requires_resolved_requests(): void
    {
        [, $owner, $manager, $professional] = $this->team();
        $service = ServiceAccess::create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'updated_by' => $owner->id, ...$this->payload()]);
        $request = app(ServiceAccessManager::class)->request($service, $professional);

        $this->actingAs($manager)->post(route('service-access.store'), $this->payload())->assertForbidden();
        $this->actingAs($professional)->patch(route('service-access.decide', $request), ['status' => 'granted'])->assertForbidden();
        $this->actingAs($owner)->delete(route('service-access.archive', $service))->assertConflict();
        $this->actingAs($owner)->patch(route('service-access.decide', $request), ['status' => 'denied'])->assertRedirect();
        $this->actingAs($owner)->patch(route('service-access.decide', $request), ['status' => 'granted'])->assertConflict();
        $this->actingAs($owner)->put(route('service-access.update', $service), [...$this->payload(), 'name' => 'Ferramenta atualizada'])->assertRedirect();
        $this->assertSame('Ferramenta atualizada', $service->fresh()->name);

        $outside = $this->team('outside');
        $this->actingAs($outside[1])->put(route('service-access.update', $service), $this->payload())->assertNotFound();
        $client = User::factory()->create(['organization_id' => $owner->organization_id, 'role' => UserRole::Client, 'is_active' => true]);
        $this->actingAs($client)->get(route('service-access.index'))->assertForbidden();
    }

    public function test_api_exposes_the_same_request_and_decision_flow_without_password_fields(): void
    {
        [$organization, $owner, , $professional] = $this->team();
        $created = $this->actingAs($owner)->postJson('/api/v1/team/service-access', $this->payload())
            ->assertCreated()->assertJsonPath('data.name', 'Ferramenta de teste')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.secret');
        $serviceId = $created->json('data.id');

        $this->actingAs($professional)->getJson('/api/v1/team/service-access')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $serviceId);
        $requested = $this->actingAs($professional)->postJson("/api/v1/team/service-access/{$serviceId}/requests")
            ->assertCreated()->assertJsonPath('data.status', 'pending');
        $requestId = $requested->json('data.id');
        $this->actingAs($professional)->postJson("/api/v1/team/service-access/{$serviceId}/requests")->assertUnprocessable();

        $this->actingAs($owner)->getJson('/api/v1/team/service-access')
            ->assertOk()->assertJsonPath('data.0.requests.0.user_id', $professional->id);
        $this->actingAs($owner)->postJson("/api/v1/team/service-access/requests/{$requestId}/decision", ['status' => 'granted'])
            ->assertOk()->assertJsonPath('data.status', 'granted')->assertJsonMissingPath('data.password');
        $this->actingAs($owner)->postJson("/api/v1/team/service-access/requests/{$requestId}/revoke")
            ->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->actingAs($owner)->deleteJson("/api/v1/team/service-access/{$serviceId}")->assertOk();
        $this->assertDatabaseHas('service_access_events', ['organization_id' => $organization->id, 'service_access_id' => $serviceId, 'event_type' => 'access_revoked']);
    }

    public function test_service_access_rejects_non_https_links_and_unknown_access_methods(): void
    {
        [, $owner] = $this->team();
        $this->actingAs($owner)->from(route('service-access.index'))->post(route('service-access.store'), [...$this->payload(), 'service_url' => 'javascript:alert(1)'])->assertSessionHasErrors('service_url');
        $this->postJson('/api/v1/team/service-access', [...$this->payload(), 'access_method' => 'shared_password'])->assertUnprocessable();
        $this->assertDatabaseCount('service_accesses', 0);
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

    private function payload(): array
    {
        return [
            'name' => 'Ferramenta de teste',
            'service_url' => 'https://service.example.test/login',
            'access_method' => 'vendor_invitation',
            'instructions' => 'Solicite convite com sua conta individual; a direção autoriza no serviço externo.',
            'review_due_on' => '2027-01-15',
        ];
    }
}
