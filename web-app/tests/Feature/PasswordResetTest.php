<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_account_can_request_and_use_a_single_use_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'person@example.test', 'is_active' => true]);

        $this->get(route('password.request'))->assertOk()->assertSee('Esqueceu sua senha?');
        $this->post(route('password.email'), ['email' => ' PERSON@EXAMPLE.TEST '])
            ->assertRedirect()->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);
        $this->assertNotSame($token, \DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Crie uma nova senha');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'senha-nova-com-mais-de-doze',
            'password_confirmation' => 'senha-nova-com-mais-de-doze',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('senha-nova-com-mais-de-doze', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'outra-senha-valida-para-teste',
            'password_confirmation' => 'outra-senha-valida-para-teste',
        ])->assertSessionHasErrors('email');
    }

    public function test_unknown_and_inactive_accounts_receive_the_same_generic_confirmation_without_email(): void
    {
        Notification::fake();
        $inactive = User::factory()->create(['email' => 'inactive@example.test', 'is_active' => false]);

        $unknownResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'unknown@example.test']);
        $inactiveResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => $inactive->email]);

        $unknownResponse->assertRedirect(route('password.request'))->assertSessionHas('status');
        $inactiveResponse->assertRedirect(route('password.request'))->assertSessionHas('status');
        $this->assertSame($unknownResponse->getSession()->get('status'), $inactiveResponse->getSession()->get('status'));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_invalid_token_and_weak_password_are_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->from(route('password.reset', ['token' => Str::random(64), 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => Str::random(64),
                'email' => $user->email,
                'password' => 'fraca',
                'password_confirmation' => 'diferente',
            ])->assertSessionHasErrors(['password']);
    }

    public function test_inactive_account_cannot_use_a_reset_token(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('password.update'), [
            'token' => Str::random(64),
            'email' => $user->email,
            'password' => 'senha-nova-com-mais-de-doze',
            'password_confirmation' => 'senha-nova-com-mais-de-doze',
        ])->assertSessionHasErrors('email');
    }
}
