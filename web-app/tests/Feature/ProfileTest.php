<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Mix7',
            'slug' => 'mix7',
        ]);
    }

    public function test_each_active_profile_can_edit_its_own_personal_information_without_changing_system_permissions(): void
    {
        $organization = $this->createOrganization();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create([
                'organization_id' => $organization->id,
                'role' => $role,
                'position_title' => $role->label(),
                'is_active' => true,
            ]);

            $this->actingAs($user)
                ->get(route('profile.index'))
                ->assertOk()
                ->assertSee('Meu perfil')
                ->assertSee($role->label());

            $this->put(route('profile.update'), [
                'name' => 'Nome atualizado',
                'position_title' => 'Especialista de conteúdo',
                'email' => $user->email,
                'role' => UserRole::AgencyOwner->value,
            ])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => 'Nome atualizado',
                'position_title' => 'Especialista de conteúdo',
                'role' => $role->value,
            ]);
        }
    }

    public function test_changing_email_requires_current_password_and_clears_old_verification(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->createOrganization()->id,
            'is_active' => true,
            'position_title' => 'Gerente',
            'email' => 'person@mix7.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => 'new@mix7.test',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => 'new@mix7.test',
            'current_password' => 'password',
        ])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@mix7.test',
            'email_verified_at' => null,
        ]);
    }

    public function test_password_change_checks_current_password_and_confirmation(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->createOrganization()->id,
            'is_active' => true,
            'position_title' => 'Profissional',
        ]);
        $this->actingAs($user);

        $this->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'password' => 'NovaSenhaSegura2026',
            'password_confirmation' => 'NovaSenhaSegura2026',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => $user->email,
            'current_password' => 'password',
            'password' => 'NovaSenhaSegura2026',
            'password_confirmation' => 'NovaSenhaSegura2026',
        ])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenhaSegura2026', $user->fresh()->password));
    }

    public function test_email_must_remain_unique(): void
    {
        $organizationId = $this->createOrganization()->id;
        $user = User::factory()->create(['organization_id' => $organizationId, 'is_active' => true, 'position_title' => 'Profissional']);
        $other = User::factory()->create(['organization_id' => $organizationId, 'is_active' => true, 'position_title' => 'Profissional', 'email' => 'other@mix7.test']);

        $this->actingAs($user)->from(route('profile.index'))->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => $other->email,
        ])->assertRedirect(route('profile.index'))->assertSessionHasErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_photo_is_stored_privately_and_served_only_to_its_owner(): void
    {
        Storage::fake('local');
        $organization = $this->createOrganization();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'position_title' => 'Profissional',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->createWithContent('perfil.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pQAAAABJRU5ErkJggg==')),
        ])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertStringStartsWith('profile-photos/'.$user->id.'/', $path);
        Storage::disk('local')->assertExists($path);
        $response = $this->get(route('profile.photo'))->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        auth()->logout();
        $this->get(route('profile.photo'))->assertRedirect(route('login'));
    }

    public function test_profile_photo_rejects_non_image_uploads(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->createOrganization()->id,
            'is_active' => true,
            'position_title' => 'Profissional',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'position_title' => $user->position_title,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->create('arquivo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('photo');
    }
}
