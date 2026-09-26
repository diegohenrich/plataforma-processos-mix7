<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemandReviewLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_approval_inbox_shows_versions_and_client_responses(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $this->createLink($manager, $demand, 'https://preview.example.test/site-v1');
        $link = $demand->reviewLinks()->firstOrFail();
        $link->responses()->create([
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'comment',
            'comment' => 'Ajustar o texto do destaque.',
            'created_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Aprovações')
            ->assertSee('Site institucional')
            ->assertSee('Versão 1')
            ->assertSee('Aguardando cliente')
            ->assertSee('Cliente Mix7')
            ->assertSee('Ajustar o texto do destaque.')
            ->assertSee(route('demands.show', $demand), false)
            ->assertDontSee('Briefing privado do teste');
    }

    public function test_professional_approval_inbox_only_shows_demands_in_their_work(): void
    {
        [$organization, $manager, $demand, $professional] = $this->setupApproval();
        $this->createLink($manager, $demand, 'https://preview.example.test/assigned');
        $otherDemand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Demanda sem atribuição',
            'brief' => 'Não deve aparecer',
            'status' => DemandStatus::ClientApproval,
        ]);
        $this->createLink($manager, $otherDemand, 'https://preview.example.test/private');
        DemandTask::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Produzir página',
            'status' => 'todo',
        ]);

        $this->actingAs($professional)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Site institucional')
            ->assertDontSee('Demanda sem atribuição')
            ->assertDontSee('Não deve aparecer');
    }

    public function test_approval_inbox_distinguishes_expired_revoked_and_decided_versions(): void
    {
        [$organization, $manager, $revokedDemand] = $this->setupApproval();
        $this->createLink($manager, $revokedDemand, 'https://preview.example.test/revoked');
        $revokedDemand->reviewLinks()->firstOrFail()->update(['revoked_at' => now()]);

        $expiredDemand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Versão expirada',
            'brief' => 'Conteúdo sintético',
            'status' => DemandStatus::ClientApproval,
        ]);
        $this->createLink($manager, $expiredDemand, 'https://preview.example.test/expired');
        $expiredDemand->reviewLinks()->firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $approvedDemand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Versão aprovada',
            'brief' => 'Conteúdo sintético',
            'status' => DemandStatus::ClientApproval,
        ]);
        $this->createLink($manager, $approvedDemand, 'https://preview.example.test/approved');
        $approvedDemand->update(['status' => DemandStatus::Delivery]);
        $approvedDemand->reviewLinks()->firstOrFail()->responses()->create([
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'approved',
            'created_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Link revogado')
            ->assertSee('Link expirado')
            ->assertSee('Aprovado pelo cliente');
    }

    public function test_client_account_cannot_open_internal_approval_inbox(): void
    {
        [$organization, $manager] = $this->setupApproval();
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        $this->actingAs($client)->get(route('approvals.index'))->assertForbidden();
    }

    public function test_manager_creates_one_time_visible_version_link_without_storing_plain_token(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/site-v1',
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        $url = $response->getSession()->get('review_link_url');
        $this->assertNotEmpty($url);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(64, strlen($token));
        $link = $demand->reviewLinks()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertStringNotContainsString($token, $link->token_hash);

        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Site institucional')
            ->assertSee('https://preview.example.test/site-v1')
            ->assertDontSee('Briefing privado do teste');
    }

    public function test_client_can_comment_then_request_changes_and_demand_enters_adjustments(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'comment', 'comment' => 'A cor ficou boa.'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::ClientApproval, $demand->fresh()->status);

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'changes_requested', 'comment' => 'Ajustar o título principal.'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::Adjustments, $demand->fresh()->status);
        $this->assertDatabaseCount('demand_review_responses', 2);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'event_type' => 'client_review_changes_requested', 'to_status' => DemandStatus::Adjustments->value]);
        $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()->assertSee('Respondida')->assertSee('Ajustar o título principal.');
        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Esta versão já recebeu uma decisão final')
            ->assertSee('Pediu ajustes')
            ->assertDontSee('Enviar comentário');
    }

    public function test_approval_advances_demand_and_prevents_another_decision(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');

        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'approved'])
            ->assertRedirect();
        $this->assertSame(DemandStatus::Delivery, $demand->fresh()->status);
        $this->assertDatabaseHas('demand_review_responses', ['type' => 'approved', 'comment' => null]);
        $this->post(route('client-reviews.respond', $token), ['reviewer_name' => 'Cliente Mix7', 'type' => 'changes_requested', 'comment' => 'Mudar'])
            ->assertStatus(410);
        $this->assertDatabaseCount('demand_review_responses', 1);
    }

    public function test_client_can_send_versioned_text_and_area_annotations_before_final_decision(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/site-v1');

        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Ancorar este comentário em')
            ->assertSee('Trecho de texto')
            ->assertSee('selecione e copie o texto')
            ->assertSee('Abrir site para selecionar texto')
            ->assertSee('target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer"', false)
            ->assertSee('Área da página')
            ->assertSee('Selecionar retângulo')
            ->assertSee('Rabiscar livremente')
            ->assertSee('Marque uma área retangular ou faça um rabisco livre sobre a prévia')
            ->assertSee('name="anchor_width" type="number" data-optional="true"', false)
            ->assertSee('sandbox="allow-scripts allow-forms"', false);

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Aumentar a leitura deste título.',
            'anchor_type' => 'text',
            'anchor_text' => 'Conheça nossos serviços',
        ])->assertRedirect();

        $this->assertDatabaseHas('demand_review_responses', [
            'type' => 'annotation',
            'anchor_type' => 'text',
            'comment' => 'Aumentar a leitura deste título.',
        ]);
        $textAnnotation = $demand->reviewLinks()->firstOrFail()->responses()->firstOrFail();
        $this->assertSame('Conheça nossos serviços', $textAnnotation->anchor_data['text']);
        $this->assertSame('https://preview.example.test/site-v1', $textAnnotation->anchor_data['url']);
        $this->assertSame(DemandStatus::ClientApproval, $demand->fresh()->status);
        $this->actingAs($manager)->get(route('demands.show', $demand))
            ->assertOk()->assertSee('Anotou no material')->assertSee('Conheça nossos serviços');

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Mover o botão para o canto.',
            'anchor_type' => 'area',
            'anchor_x' => 82.5,
            'anchor_y' => 47,
        ])->assertRedirect();

        $areaAnnotation = $demand->reviewLinks()->firstOrFail()->responses()
            ->where('comment', 'Mover o botão para o canto.')
            ->firstOrFail();
        $this->assertSame(82.5, $areaAnnotation->anchor_data['x']);
        $this->assertEquals(47, $areaAnnotation->anchor_data['y']);
        $this->assertDatabaseCount('demand_review_responses', 2);

        foreach ([['time', 'anchor_time', '00:01:25'], ['page', 'anchor_page', 3]] as [$anchorType, $field, $value]) {
            $this->post(route('client-reviews.respond', $token), [
                'reviewer_name' => 'Cliente Mix7',
                'type' => 'annotation',
                'comment' => 'Conferir este ponto.',
                'anchor_type' => $anchorType,
                $field => $value,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('demand_review_responses', 4);
        $this->assertSame('00:01:25', $demand->reviewLinks()->firstOrFail()->responses()->where('anchor_type', 'time')->firstOrFail()->anchor_data['time']);
        $this->assertSame(3, $demand->reviewLinks()->firstOrFail()->responses()->where('anchor_type', 'page')->firstOrFail()->anchor_data['page']);

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Alterar este bloco inteiro.',
            'anchor_type' => 'area',
            'anchor_x' => 42.5,
            'anchor_y' => 31,
            'anchor_width' => 26.5,
            'anchor_height' => 14,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rectangleAnnotation = $demand->reviewLinks()->firstOrFail()->responses()
            ->where('comment', 'Alterar este bloco inteiro.')
            ->firstOrFail();
        $this->assertSame('Alterar este bloco inteiro.', $rectangleAnnotation->comment);
        $this->assertSame(26.5, $rectangleAnnotation->anchor_data['width']);
        $this->assertEquals(14, $rectangleAnnotation->anchor_data['height']);
        $this->actingAs($manager)->get(route('demands.show', $demand))
            ->assertOk()->assertSee('largura 26.5%, altura 14%');
    }

    public function test_annotation_anchor_values_are_validated(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/site-v1');

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Corrigir aqui.',
            'anchor_type' => 'area',
            'anchor_x' => 101,
            'anchor_y' => -1,
            'anchor_width' => 101,
            'anchor_height' => -1,
        ])->assertSessionHasErrors(['anchor_x', 'anchor_y', 'anchor_width', 'anchor_height']);

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Rever este quadro.',
            'anchor_type' => 'time',
            'anchor_time' => '25:90:99',
        ])->assertSessionHasErrors('anchor_time');

        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Marcar uma região.',
            'anchor_type' => 'area',
            'anchor_x' => 40,
            'anchor_y' => 50,
            'anchor_width' => 20,
        ])->assertSessionHasErrors('anchor_height');
        $this->assertDatabaseCount('demand_review_responses', 0);
    }

    public function test_client_can_send_a_freehand_drawing_attached_to_an_area_comment(): void
    {
        [, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/site-v1');

        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Rabiscar livremente')
            ->assertSee('name="anchor_path" type="hidden"', false);

        $path = [
            ['x' => 12.3, 'y' => 18.4],
            ['x' => 26.7, 'y' => 33.2],
            ['x' => 48.9, 'y' => 29.5],
        ];
        $this->post(route('client-reviews.respond', $token), [
            'reviewer_name' => 'Cliente Mix7',
            'type' => 'annotation',
            'comment' => 'Circulei o título que precisa de ajuste.',
            'anchor_type' => 'area',
            'anchor_x' => 30,
            'anchor_y' => 26,
            'anchor_path' => json_encode($path),
        ])->assertRedirect();

        $response = $demand->reviewLinks()->firstOrFail()->responses()->firstOrFail();
        $this->assertSame($path, $response->anchor_data['path']);
        $this->assertSame('https://preview.example.test/site-v1', $response->anchor_data['url']);
        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('Rabiscos ligados aos comentários')
            ->assertSee('points="12.3,18.4 26.7,33.2 48.9,29.5"', false)
            ->assertSee('Circulei o título que precisa de ajuste.');
        $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()
            ->assertSee('Rabiscos enviados com comentários')
            ->assertSee('points="12.3,18.4 26.7,33.2 48.9,29.5"', false)
            ->assertSee('Circulei o título que precisa de ajuste.');
    }

    public function test_freehand_path_rejects_malformed_or_out_of_bounds_coordinates(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/site-v1');

        foreach ([
            '[not-json]',
            json_encode([['x' => -1, 'y' => 20], ['x' => 101, 'y' => 120]]),
        ] as $path) {
            $this->post(route('client-reviews.respond', $token), [
                'reviewer_name' => 'Cliente Mix7',
                'type' => 'annotation',
                'comment' => 'Marcação inválida.',
                'anchor_type' => 'area',
                'anchor_x' => 30,
                'anchor_y' => 26,
                'anchor_path' => $path,
            ])->assertSessionHasErrors('anchor_path');
        }

        $this->assertDatabaseCount('demand_review_responses', 0);
    }

    public function test_video_review_offers_a_control_to_copy_the_paused_timestamp_into_annotation(): void
    {
        Storage::fake('local');
        [$organization, $manager, $demand] = $this->setupApproval();
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_file' => UploadedFile::fake()->create('criativo.mp4', 120, 'video/mp4'),
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        $token = basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('id="review-video"', false)
            ->assertSee('id="use-video-time"', false)
            ->assertSee('Usar instante pausado')
            ->assertSee('Pause o vídeo no ponto desejado');
    }

    public function test_expired_revoked_and_previous_version_links_cannot_be_opened_or_answered(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $firstToken = $this->createLink($manager, $demand, 'https://preview.example.test/v1');
        $firstLink = $demand->reviewLinks()->firstOrFail();
        $secondToken = $this->createLink($manager, $demand, 'https://preview.example.test/v2');
        $this->assertNotNull($firstLink->fresh()->revoked_at);
        $this->get(route('client-reviews.show', $firstToken))->assertStatus(410);

        $secondLink = $demand->reviewLinks()->where('version', 2)->firstOrFail();
        $secondLink->update(['expires_at' => now()->subMinute()]);
        $this->get(route('client-reviews.show', $secondToken))->assertStatus(410);
        $this->post(route('client-reviews.respond', $secondToken), ['reviewer_name' => 'Cliente', 'type' => 'approved'])->assertStatus(410);
    }

    public function test_only_agency_manager_can_create_or_revoke_links_and_creation_requires_approval_stage(): void
    {
        [$organization, $manager, $demand, $professional] = $this->setupApproval();
        $this->actingAs($professional)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/v1', 'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertForbidden();

        $demand->update(['status' => DemandStatus::InProgress]);
        $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/v1', 'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('demand_review_links', 0);
    }

    public function test_manager_can_revoke_link_and_invalid_material_url_is_rejected(): void
    {
        [$organization, $manager, $demand] = $this->setupApproval();
        $token = $this->createLink($manager, $demand, 'https://preview.example.test/v1');
        $link = $demand->reviewLinks()->firstOrFail();

        $this->actingAs($manager)->delete(route('demand-reviews.revoke', [$demand, $link]))->assertRedirect();
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->get(route('client-reviews.show', $token))->assertStatus(410)->assertSee('Este link não está mais disponível');

        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('demand-reviews.store', $demand), [
            'material_url' => 'javascript:alert(1)',
            'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertSessionHasErrors('material_url');
        $this->assertDatabaseCount('demand_review_links', 1);
    }

    public function test_manager_can_upload_private_pdf_and_client_can_view_it_through_active_review_link(): void
    {
        Storage::fake('local');
        [$organization, $manager, $demand] = $this->setupApproval();
        $upload = UploadedFile::fake()->createWithContent('brief-preview.pdf', "%PDF-1.4\nSynthetic review PDF\n%%EOF");

        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_file' => $upload,
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        $token = basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
        $link = $demand->reviewLinks()->firstOrFail();
        $this->assertNull($link->material_url);
        $this->assertSame('brief-preview.pdf', $link->material_file_name);
        $this->assertSame('application/pdf', $link->material_mime);
        $this->assertStringStartsWith("review-materials/{$organization->id}/{$demand->id}/", $link->material_file_path);
        Storage::disk('local')->assertExists($link->material_file_path);
        Storage::disk('public')->assertMissing($link->material_file_path);

        $this->get(route('client-reviews.show', $token))->assertOk()
            ->assertSee('brief-preview.pdf')
            ->assertSee(route('client-reviews.material', $token), false)
            ->assertSee('Prévia do material')
            ->assertDontSee($link->material_file_path)
            ->assertDontSee('Briefing privado do teste');

        $this->get(route('client-reviews.material', $token))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('Synthetic review PDF', Storage::disk('local')->get($link->material_file_path));

        $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()
            ->assertSee('Visualizar arquivo desta versão')
            ->assertSee('Controles do PDF')
            ->assertSee('data-pdf-preview', false)
            ->assertSee(route('demand-reviews.team-material', [$demand, $link]), false);
        $this->get(route('demand-reviews.team-material', [$demand, $link]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $download = $this->get(route('client-reviews.material', ['token' => $token, 'download' => 1]))->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_private_review_file_cannot_be_viewed_after_revocation_or_expiration(): void
    {
        Storage::fake('local');
        [$organization, $manager, $demand] = $this->setupApproval();
        $firstToken = $this->createFileLink($manager, $demand);
        $firstLink = $demand->reviewLinks()->firstOrFail();

        $this->actingAs($manager)->delete(route('demand-reviews.revoke', [$demand, $firstLink]))->assertRedirect();
        $this->get(route('client-reviews.material', $firstToken))->assertStatus(410);

        $secondToken = $this->createFileLink($manager, $demand);
        $secondLink = $demand->reviewLinks()->where('version', 2)->firstOrFail();
        $secondLink->update(['expires_at' => now()->subMinute()]);
        $this->get(route('client-reviews.material', $secondToken))->assertStatus(410);

        $thirdToken = $this->createFileLink($manager, $demand);
        $demand->update(['status' => DemandStatus::InProgress]);
        $this->get(route('client-reviews.material', $thirdToken))->assertStatus(410);
    }

    public function test_private_review_file_requires_same_organization_manager_and_rejects_invalid_uploads(): void
    {
        Storage::fake('local');
        [$organization, $manager, $demand, $professional] = $this->setupApproval();
        $token = $this->createFileLink($manager, $demand);
        $link = $demand->reviewLinks()->firstOrFail();
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'outra-agencia']);
        $otherManager = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);

        $this->actingAs($professional)->get(route('demand-reviews.team-material', [$demand, $link]))->assertForbidden();
        $this->actingAs($otherManager)->get(route('demand-reviews.team-material', [$demand, $link]))->assertNotFound();
        $this->get(route('client-reviews.material', str_repeat('x', 64)))->assertNotFound();

        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('demand-reviews.store', $demand), [
            'material_file' => UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>'),
            'expires_at' => now()->addDay()->toIso8601String(),
        ])->assertSessionHasErrors('material_file');
    }

    public function test_private_review_upload_enforces_twenty_megabyte_limit_and_one_material_source(): void
    {
        Storage::fake('local');
        [$organization, $manager, $demand] = $this->setupApproval();
        $expiry = now()->addDay()->toIso8601String();

        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('demand-reviews.store', $demand), [
            'material_file' => UploadedFile::fake()->create('large.pdf', 20 * 1024 + 1, 'application/pdf'),
            'expires_at' => $expiry,
        ])->assertSessionHasErrors('material_file');

        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('demand-reviews.store', $demand), [
            'material_url' => 'https://preview.example.test/v1',
            'material_file' => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\n%%EOF"),
            'expires_at' => $expiry,
        ])->assertSessionHasErrors('material_file');

        $this->assertDatabaseCount('demand_review_links', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('review-materials'));
    }

    private function setupApproval(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing privado do teste',
            'status' => DemandStatus::ClientApproval,
        ]);

        return [$organization, $manager, $demand, $professional];
    }

    private function createLink(User $manager, Demand $demand, string $materialUrl): string
    {
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_url' => $materialUrl,
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        return basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
    }

    private function createFileLink(User $manager, Demand $demand): string
    {
        $response = $this->actingAs($manager)->post(route('demand-reviews.store', $demand), [
            'material_file' => UploadedFile::fake()->createWithContent('approval.pdf', "%PDF-1.4\nReview material\n%%EOF"),
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ])->assertRedirect();

        return basename(parse_url($response->getSession()->get('review_link_url'), PHP_URL_PATH));
    }
}
