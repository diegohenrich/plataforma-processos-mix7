<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_accounts_for_every_role_can_sign_in_and_out(): void
    {
        $organization = Organization::create(['name' => 'Mix7 de teste', 'slug' => 'mix7-de-teste']);

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create([
                'organization_id' => $organization->id,
                'role' => $role,
                'is_active' => true,
            ]);

            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_inactive_account_cannot_sign_in_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_account_with_an_incorrect_password_stays_a_guest(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'senha-incorreta',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
