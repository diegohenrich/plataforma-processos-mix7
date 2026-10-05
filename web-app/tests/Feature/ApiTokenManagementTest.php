<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_creates_short_lived_token_once_and_can_revoke_it(): void
    {
        [$organization, $owner] = $this->team();
        $payload = ['name' => 'Desktop Mix7', 'expires_in_days' => 30, 'current_password' => 'senha-correta-e-segura'];

        $this->actingAs($owner)->get(route('api-tokens.index'))
            ->assertOk()->assertSee('Acessos da API')->assertSee('Criar token');
        $this->post(route('api-tokens.store'), [...$payload, 'current_password' => 'senha-errada'])
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $response = $this->post(route('api-tokens.store'), $payload)->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('Desktop Mix7')->assertSee('Expira em');
        preg_match('/value="([A-Za-z0-9|]+)" readonly/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $plainTextToken = $matches[1];

        $token = $owner->tokens()->firstOrFail();
        $this->assertSame('Desktop Mix7', $token->name);
        $this->assertSame(['api'], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertNotSame($plainTextToken, $token->token);
        $this->get(route('api-tokens.index'))->assertOk()->assertSee('Desktop Mix7')->assertDontSee($plainTextToken);
        $this->withToken($plainTextToken)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.organization_id', $organization->id);

        $this->delete(route('api-tokens.destroy', $token->id))->assertRedirect(route('api-tokens.index'));
        $this->app['auth']->forgetGuards();
        $this->withToken($plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_client_cannot_manage_api_tokens_and_users_cannot_revoke_another_users_token(): void
    {
        [$organization, $owner] = $this->team();
        $otherOwner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $token = $otherOwner->createToken('Outro dispositivo', ['api'], now()->addDays(30));
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        $this->actingAs($client)->get(route('api-tokens.index'))->assertForbidden();
        $this->actingAs($client)->post(route('api-tokens.store'), ['name' => 'Cliente', 'expires_in_days' => 30, 'current_password' => 'senha'])->assertForbidden();
        $this->actingAs($owner)->delete(route('api-tokens.destroy', $token->accessToken->id))->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_tokens_have_a_fixed_api_ability_and_expire_within_ninety_days(): void
    {
        [, $owner] = $this->team();
        $limitedToken = $owner->createToken('Sem acesso à API', ['read-only'], now()->addDays(30));

        $this->withToken($limitedToken->plainTextToken)->getJson('/api/v1/me')->assertForbidden();
        $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'Prazo inválido', 'expires_in_days' => 365, 'current_password' => 'senha-correta-e-segura'])->assertSessionHasErrors('expires_in_days');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_account_has_a_limit_of_ten_unexpired_api_tokens(): void
    {
        [, $owner] = $this->team();
        for ($index = 1; $index <= 10; $index++) {
            $owner->createToken("Integração {$index}", ['api'], now()->addDays(30));
        }

        $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'Décimo primeiro', 'expires_in_days' => 30, 'current_password' => 'senha-correta-e-segura'])
            ->assertRedirect(route('api-tokens.index'))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('personal_access_tokens', 10);
    }

    private function team(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::AgencyOwner,
            'is_active' => true,
            'password' => 'senha-correta-e-segura',
        ]);

        return [$organization, $owner];
    }
}
