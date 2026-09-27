<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemandAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_upload_private_demand_attachments_and_open_or_download_them(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$organization, $owner, $professional, $demand] = $this->workspace();
        $this->assignDemandTo($demand, $owner, $professional);
        $upload = UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\nBrief privado\n%%EOF");

        $this->actingAs($professional)->post(route('demand-attachments.store', $demand), ['files' => [$upload]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $attachment = $demand->attachments()->firstOrFail();
        $this->assertSame($professional->id, $attachment->uploaded_by);
        $this->assertStringStartsWith("demand-attachments/{$organization->id}/{$demand->id}/", $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $professional->id,
            'event_type' => 'demand_attachments_added',
        ]);

        $preview = $this->get(route('demand-attachments.show', [$demand, $attachment]));
        $preview
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('Brief privado', Storage::disk('local')->get($attachment->file_path));
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $preview->headers->get('Cache-Control'));

        $this->get(route('demand-attachments.show', [$demand, $attachment]).'?download=1')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=brief.pdf');

        $this->actingAs($owner)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Arquivos da equipe')
            ->assertSee('brief.pdf')
            ->assertSee('Visualizar PDF nesta tela')
            ->assertSee('Controles do PDF')
            ->assertSee('data-pdf-preview', false)
            ->assertSee('Abrir PDF em outra guia')
            ->assertSee('Baixar PDF')
            ->assertSee('Anexar à demanda');

        $this->actingAs($professional)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('data-pdf-preview', false)
            ->assertSee('Abrir PDF em outra guia');
    }

    public function test_client_cannot_see_or_fetch_internal_demand_attachments(): void
    {
        Storage::fake('local');
        [$organization, $owner, , $demand, $client] = $this->workspace(withClient: true);
        $path = "demand-attachments/{$organization->id}/{$demand->id}/private.pdf";
        Storage::disk('local')->put($path, "%PDF-1.4\nPrivate\n%%EOF");
        $attachment = $demand->attachments()->create([
            'organization_id' => $organization->id,
            'uploaded_by' => $owner->id,
            'file_path' => $path,
            'original_name' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('local')->size($path),
        ]);

        $this->actingAs($client)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertDontSee('Arquivos da equipe')
            ->assertDontSee('private.pdf');
        $this->get(route('demand-attachments.show', [$demand, $attachment]))->assertNotFound();
        $this->post(route('demand-attachments.store', $demand), ['files' => [UploadedFile::fake()->create('client.pdf', 10, 'application/pdf')]])
            ->assertNotFound();
    }

    public function test_attachments_reject_unsupported_or_oversized_files_without_saving_them(): void
    {
        Storage::fake('local');
        [$organization, , $professional, $demand] = $this->workspace();
        $this->assignDemandTo($demand, $demand->creator, $professional);

        $this->actingAs($professional)->from(route('demands.show', $demand))
            ->post(route('demand-attachments.store', $demand), ['files' => [UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>')]])
            ->assertSessionHasErrors('files.0');

        $this->post(route('demand-attachments.store', $demand), ['files' => [UploadedFile::fake()->create('large.pdf', 20 * 1024 + 1, 'application/pdf')]])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, $demand->attachments()->count());
        $this->assertSame([], Storage::disk('local')->allFiles("demand-attachments/{$organization->id}/{$demand->id}"));
    }

    public function test_other_organization_cannot_read_or_attach_files_to_demand(): void
    {
        [$organization, , , $demand] = $this->workspace();
        [, $outsider] = $this->workspace('other');
        Storage::fake('local');

        $this->actingAs($outsider)->get(route('demands.show', $demand))->assertForbidden();
        $this->post(route('demand-attachments.store', $demand), ['files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]])
            ->assertForbidden();
    }

    /** @return array{Organization, User, User, Demand, User|null} */
    private function workspace(string $slug = 'mix7', bool $withClient = false): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = $withClient ? User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]) : null;
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'client_user_id' => $client?->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::Received,
        ]);

        return [$organization, $owner, $professional, $demand, $client];
    }

    private function assignDemandTo(Demand $demand, User $creator, User $professional): void
    {
        $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $creator->id,
            'assigned_to' => $professional->id,
            'title' => 'Executar entrega',
            'status' => TaskStatus::Todo,
        ]);
    }
}
