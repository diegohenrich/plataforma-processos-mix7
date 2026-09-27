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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiDemandAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_api_can_upload_list_preview_and_download_demand_attachments(): void
    {
        Storage::fake('local');
        [$organization, $owner, $professional, , $demand] = $this->workspace();
        $token = $professional->createToken('desktop')->plainTextToken;
        $response = $this->authenticate($token)->withHeaders(['Accept' => 'application/json'])
            ->post(route('api.v1.demand-attachments.store', $demand), [
                'files' => [
                    UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\nConteúdo reservado\n%%EOF"),
                    UploadedFile::fake()->createWithContent('layout.pdf', "%PDF-1.4\nLayout privado\n%%EOF"),
                ],
            ]);

        $response->assertCreated()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'brief.pdf')
            ->assertJsonPath('data.0.uploaded_by.id', $professional->id)
            ->assertJsonPath('data.0.mime_type', 'application/pdf')
            ->assertJsonMissingPath('data.0.file_path')
            ->assertJsonPath('data.0.preview_url', route('api.v1.demand-attachments.show', [$demand, 1]))
            ->assertJsonPath('data.0.download_url', route('api.v1.demand-attachments.show', [$demand, 1, 'download' => 1]));

        $attachment = $demand->attachments()->where('original_name', 'brief.pdf')->firstOrFail();
        $this->assertSame($organization->id, $attachment->organization_id);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'actor_id' => $professional->id,
            'event_type' => 'demand_attachments_added',
        ]);

        $this->authenticate($token)->getJson(route('api.v1.demand-attachments.index', $demand))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'layout.pdf');
        $this->authenticate($token)->get(route('api.v1.demand-attachments.show', [$demand, $attachment]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->authenticate($token)->get(route('api.v1.demand-attachments.show', [$demand, $attachment, 'download' => 1]))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=brief.pdf');
    }

    public function test_api_never_discloses_internal_attachments_to_client_accounts(): void
    {
        Storage::fake('local');
        [$organization, $owner, , $client, $demand] = $this->workspace(withClient: true);
        $attachment = $demand->attachments()->create([
            'organization_id' => $organization->id,
            'uploaded_by' => $owner->id,
            'file_path' => "demand-attachments/{$organization->id}/{$demand->id}/internal.pdf",
            'original_name' => 'internal.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
        ]);
        Storage::disk('local')->put($attachment->file_path, "%PDF-1.4\nInternal\n%%EOF");
        $token = $client->createToken('client')->plainTextToken;

        $this->authenticate($token)->getJson("/api/v1/demands/{$demand->id}")
            ->assertOk()->assertJsonMissingPath('data.attachments');
        $this->flushHeaders();
        Auth::forgetGuards();
        $this->getJson(route('api.v1.demand-attachments.index', $demand))->assertUnauthorized();
        $this->authenticate($token)->getJson(route('api.v1.demand-attachments.index', $demand))->assertNotFound();
        $this->authenticate($token)->post(route('api.v1.demand-attachments.store', $demand), [
            'files' => [UploadedFile::fake()->create('client.pdf', 10, 'application/pdf')],
        ])->assertNotFound();
        $this->authenticate($token)->get(route('api.v1.demand-attachments.show', [$demand, $attachment]))->assertNotFound();
    }

    public function test_api_rejects_cross_organization_and_invalid_attachment_requests(): void
    {
        Storage::fake('local');
        [, , $professional, , $demand] = $this->workspace();
        [, $outsider] = $this->workspace('other');
        $professionalToken = $professional->createToken('desktop')->plainTextToken;
        $outsiderToken = $outsider->createToken('desktop')->plainTextToken;

        $this->authenticate($professionalToken)->withHeaders(['Accept' => 'application/json'])
            ->post(route('api.v1.demand-attachments.store', $demand), [
                'files' => [UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>')],
            ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->authenticate($outsiderToken)->getJson(route('api.v1.demand-attachments.index', $demand))->assertForbidden();
        $this->authenticate($outsiderToken)->post(route('api.v1.demand-attachments.store', $demand), [
            'files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
        ])->assertForbidden();
        $this->assertSame(0, $demand->attachments()->count());
    }

    /** @return array{Organization, User, User, User, Demand} */
    private function workspace(string $slug = 'mix7', bool $withClient = false): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'client_user_id' => $withClient ? $client->id : null,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::Received,
        ]);
        $demand->tasks()->create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'assigned_to' => $professional->id,
            'title' => 'Executar entrega',
            'status' => TaskStatus::Todo,
        ]);

        return [$organization, $owner, $professional, $client, $demand];
    }

    private function authenticate(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
