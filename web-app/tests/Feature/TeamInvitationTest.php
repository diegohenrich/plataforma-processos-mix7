<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_create_invitation_for_supported_roles(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Nova Profissional', 'email' => ' NOVA@EXEMPLO.COM ', 'role' => UserRole::Professional->value,
        ])->assertRedirect(route('team.index'))->assertSessionHasNoErrors();

        $invite = TeamInvitation::query()->firstOrFail();
        $this->assertSame($organization->id, $invite->organization_id);
        $this->assertSame($owner->id, $invite->invited_by);
        $this->assertSame('nova@exemplo.com', $invite->email);
        $this->assertDatabaseMissing('users', ['email' => $invite->email]);
        Notification::assertSentOnDemand(TeamInvitationNotification::class);

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Nova Gerente', 'email' => ' GERENTE@EXEMPLO.COM ', 'role' => UserRole::MarketingManager->value,
        ])->assertRedirect(route('team.index'))->assertSessionHasNoErrors();

        $managerInvitation = TeamInvitation::query()->where('email', 'gerente@exemplo.com')->firstOrFail();
        $this->assertSame(UserRole::MarketingManager, $managerInvitation->role);
        $this->assertSame($owner->id, $managerInvitation->invited_by);

        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $this->actingAs($manager)->post(route('team-invitations.store'), [
            'name' => 'Negado', 'email' => 'negado@example.test', 'role' => UserRole::Client->value,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Papel inválido', 'email' => 'bad-role@example.test', 'role' => UserRole::AgencyOwner->value,
        ])->assertSessionHasErrors('role');
    }

    public function test_local_web_invitation_displays_a_manual_link_when_email_transport_is_not_configured(): void
    {
        Notification::fake();
        config(['app.env' => 'local', 'mail.default' => 'log']);
        [, $owner] = $this->workspace();

        $response = $this->actingAs($owner)->followingRedirects()->post(route('team-invitations.store'), [
            'name' => 'Profissional para teste local',
            'email' => 'teste-local@example.test',
            'role' => UserRole::Professional->value,
        ]);

        $invitation = TeamInvitation::query()->where('email', 'teste-local@example.test')->firstOrFail();
        $html = $response->assertOk()
            ->assertSee('Link do convite para teste local')
            ->assertSee('O ambiente local não enviou e-mail.')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->getContent();
        preg_match('/value="([^"]*\/convite\/([A-Za-z0-9]+))"/', $html, $matches);

        $this->assertNotEmpty($matches[2] ?? null);
        $this->assertSame(hash('sha256', $matches[2]), $invitation->token_hash);
        $this->assertSame(64, strlen($matches[2]));
        Notification::assertNothingSent();
        $this->get(route('team-invitations.show', $matches[2]))->assertOk()->assertSee('Crie sua senha');
    }

    public function test_web_invitation_keeps_the_email_flow_outside_local_manual_mode(): void
    {
        Notification::fake();
        config(['app.env' => 'production', 'mail.default' => 'smtp']);
        [, $owner] = $this->workspace();

        $response = $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Profissional por e-mail',
            'email' => 'por-email@example.test',
            'role' => UserRole::Professional->value,
        ]);

        $response->assertRedirect(route('team.index'))->assertSessionMissing('invitation_url');
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_invited_manager_activates_an_account_with_the_manager_role(): void
    {
        Notification::fake();
        [, $owner] = $this->workspace();

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Gerente Mix7',
            'email' => 'gerente@example.test',
            'role' => UserRole::MarketingManager->value,
        ])->assertRedirect(route('team.index'));

        $token = Notification::sent(new AnonymousNotifiable, TeamInvitationNotification::class)->first()->token;
        $this->get(route('team-invitations.show', $token))->assertOk()->assertSee('Gerente Mix7');
        $this->post(route('team-invitations.accept', $token), [
            'password' => 'senha-gerente-segura',
            'password_confirmation' => 'senha-gerente-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'gerente@example.test',
            'role' => UserRole::MarketingManager->value,
            'is_active' => true,
        ]);
        $this->assertNotNull(TeamInvitation::query()->where('email', 'gerente@example.test')->firstOrFail()->fresh()->accepted_at);
    }

    public function test_owner_can_issue_a_manager_invitation_link_through_the_api(): void
    {
        Notification::fake();
        [, $owner] = $this->workspace();

        $response = $this->actingAs($owner)->postJson('/api/v1/team/invitations', [
            'name' => 'Gerente via API',
            'email' => 'gerente-api@example.test',
            'role' => UserRole::MarketingManager->value,
        ])->assertCreated()->assertJsonPath('data.role', UserRole::MarketingManager->value);

        $token = basename(parse_url($response->json('data.invitation_url'), PHP_URL_PATH));
        $this->assertDatabaseHas('team_invitations', [
            'email' => 'gerente-api@example.test',
            'role' => UserRole::MarketingManager->value,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'gerente-api@example.test']);
        Notification::assertNothingSent();
    }

    public function test_owner_can_issue_a_hashed_manual_invitation_link_without_email_and_revoke_it(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();
        $manager = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::MarketingManager,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->postJson('/api/v1/team/invitations', [
            'name' => 'Pessoa não autorizada',
            'email' => 'negado@example.test',
            'role' => UserRole::Professional->value,
        ])->assertForbidden();

        $creation = $this->actingAs($owner)->postJson('/api/v1/team/invitations', [
            'name' => 'Nova profissional',
            'email' => ' NOVA@EXEMPLO.COM ',
            'role' => UserRole::Professional->value,
        ])->assertCreated()
            ->assertJsonPath('data.email', 'nova@exemplo.com')
            ->assertJsonPath('message', 'Convite criado. Nenhum e-mail foi enviado; compartilhe a URL por um canal seguro.')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $url = $creation->json('data.invitation_url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $invitation = TeamInvitation::query()->firstOrFail();
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertTrue($invitation->expires_at->between(now()->addHours(71)->addMinutes(59), now()->addHours(72)->addSeconds(1)));
        $this->assertDatabaseMissing('users', ['email' => 'nova@exemplo.com']);
        Notification::assertNothingSent();
        $this->get(route('team-invitations.show', $token))->assertOk()->assertSee('Crie sua senha');

        $this->actingAs($owner)->deleteJson('/api/v1/team/invitations/'.$invitation->id)
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id);
        $this->get(route('team-invitations.show', $token))->assertOk()->assertSee('Este convite não está disponível');
    }

    public function test_invitation_link_expires_after_seventy_two_hours(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();
        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Cliente', 'email' => 'client@example.test', 'role' => UserRole::Client->value,
        ])->assertRedirect();

        $invite = TeamInvitation::query()->firstOrFail();
        $this->assertTrue($invite->expires_at->between(now()->addHours(71)->addMinutes(59), now()->addHours(72)->addSeconds(1)));
        $token = Notification::sent(new AnonymousNotifiable, TeamInvitationNotification::class)->first()->token;
        $invite->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->get(route('team-invitations.show', $token))->assertOk()->assertSee('Este convite não está disponível');
        $this->post(route('team-invitations.accept', $token), [
            'password' => 'senha-cliente-segura', 'password_confirmation' => 'senha-cliente-segura',
        ])->assertSessionHasErrors('invitation');
        $this->assertDatabaseMissing('users', ['email' => 'client@example.test']);
    }

    public function test_owner_can_revoke_pending_invitation_but_cannot_revoke_another_organization_invite(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();
        [$otherOrganization, $otherOwner] = $this->workspace('outside');
        $invite = $this->invitation($organization, $owner, 'client@example.test');

        $this->actingAs($otherOwner)->delete(route('team-invitations.revoke', $invite))->assertNotFound();
        $this->actingAs($owner)->delete(route('team-invitations.revoke', $invite))->assertRedirect(route('team.index'));
        $this->assertNotNull($invite->fresh()->revoked_at);
        $this->get(route('team-invitations.show', 'irrelevant'))->assertOk()->assertSee('Este convite não está disponível');

        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $another = $this->invitation($organization, $owner, 'another@example.test');
        $this->actingAs($manager)->delete(route('team-invitations.revoke', $another))->assertForbidden();
    }

    public function test_creating_new_invitation_for_same_email_revokes_previous_token(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->workspace();
        $this->actingAs($owner)->post(route('team-invitations.store'), ['name' => 'One', 'email' => 'same@example.test', 'role' => UserRole::Professional->value]);
        $old = TeamInvitation::query()->firstOrFail();
        $oldToken = Notification::sent(new AnonymousNotifiable, TeamInvitationNotification::class)->first()->token;
        $this->post(route('team-invitations.store'), ['name' => 'Two', 'email' => 'SAME@example.test', 'role' => UserRole::Client->value]);

        $this->assertNotNull($old->fresh()->revoked_at);
        $this->get(route('team-invitations.show', $oldToken))->assertOk()->assertSee('Este convite não está disponível');
        $this->assertSame(2, TeamInvitation::query()->where('organization_id', $organization->id)->count());
    }

    public function test_password_validation_and_invalid_token_never_create_account(): void
    {
        $this->get(route('team-invitations.show', Str::random(64)))->assertOk()->assertSee('Este convite não está disponível');
        $this->post(route('team-invitations.accept', Str::random(64)), [
            'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);

        return [$organization, $owner];
    }

    private function invitation(Organization $organization, User $owner, string $email): TeamInvitation
    {
        return TeamInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'name' => 'Pessoa convidada',
            'email' => $email,
            'role' => UserRole::Client,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(72),
        ]);
    }
}
