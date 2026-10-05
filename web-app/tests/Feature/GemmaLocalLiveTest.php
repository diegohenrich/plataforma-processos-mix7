<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\AiPlanningRun;
use App\Models\AiProviderSetting;
use App\Models\Demand;
use App\Models\DemandReviewLink;
use App\Models\Organization;
use App\Models\User;
use App\Services\AiAgentRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GemmaLocalLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MIX7_GEMMA_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Defina MIX7_GEMMA_LIVE_TEST=1 para testar o Ollama local com mensagens fictícias.');
        }
        config(['services.ai_gateway.provider' => 'ollama-gemma-local']);
    }

    public function test_gemma_answers_the_guided_briefing_chat_without_creating_a_demand(): void
    {
        [, $owner] = $this->workspace();

        $initialDemandCount = Demand::count();
        $response = $this->actingAs($owner)->postJson(route('ai-briefing.suggest'), [
            'module_key' => 'social_creative',
            'messages' => [['role' => 'user', 'content' => 'Organize o briefing com base no documento anexado.']],
            'document_name' => 'briefing-ficticio.txt',
            'document_text' => 'Um consultório odontológico em Brasília precisa de três criativos para Instagram para divulgar implantes. A ação desejada é agendamento de avaliação.',
        ])->assertOk()->assertJsonStructure(['message', 'title', 'brief', 'follow_up', 'module_fields', 'tasks', 'ready']);

        $this->assertNotSame('', trim((string) $response->json('title')));
        $this->assertNotSame('', trim((string) $response->json('brief')));
        $this->assertNotEmpty($response->json('tasks'), 'A resposta deve trazer tarefas iniciais para revisar no formulário.');
        $this->assertSame($initialDemandCount, Demand::count(), 'O briefing não deve criar outra demanda.');
    }

    public function test_gemma_answers_the_contextual_area_assistant(): void
    {
        [, , $professional] = $this->workspace();

        $response = $this->actingAs($professional)->postJson(route('contextual-assistant.ask'), [
            'area' => 'approvals',
            'messages' => [['role' => 'user', 'content' => 'Quais informações devo conferir antes de transformar um comentário do cliente em ajuste?']],
        ]);
        $this->assertSame(200, $response->status(), json_encode($response->json(), JSON_UNESCAPED_UNICODE));
        $response->assertJsonStructure(['answer']);
    }

    public function test_gemma_generates_a_reviewable_plan_without_creating_tasks(): void
    {
        [$organization, $owner, , $demand] = $this->workspace();
        $demand->update(['title' => 'Criativos de Instagram para consultório', 'brief' => 'Criar três criativos informativos para Instagram sobre implantes dentários, com chamada para agendamento.']);

        $this->actingAs($owner)->post(route('ai-planning.propose', $demand), ['capacity_week' => null])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('ai_planning_run_id');

        $run = AiPlanningRun::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('pending', $run->status);
        $this->assertNotEmpty($run->proposal['tasks']);
        $this->assertDatabaseCount('demand_tasks', 0);
    }

    public function test_gemma_rewrites_client_feedback_without_losing_comment_version_or_anchor(): void
    {
        [$organization, $owner, , $demand] = $this->workspace();
        $link = DemandReviewLink::create([
            'organization_id' => $organization->id, 'demand_id' => $demand->id, 'created_by' => $owner->id,
            'version' => 7, 'token_hash' => str_repeat('a', 64), 'material_url' => 'https://preview.example.test/version-7', 'expires_at' => now()->addDay(),
        ]);
        $response = $link->responses()->create([
            'reviewer_name' => 'Cliente de teste', 'type' => 'comment', 'comment' => 'A chamada de agendamento precisa aparecer melhor no topo.',
            'anchor_type' => 'text', 'anchor_data' => ['text' => 'Agende sua consulta', 'page' => 'início'],
        ]);

        $result = app(AiAgentRuntime::class)->run($demand, $owner, 'Transforme o comentário em uma orientação objetiva.', 'approval_assistant');
        $proposal = json_decode($result['answer'], true, 16, JSON_THROW_ON_ERROR);
        $adjustment = collect($proposal['adjustments'])->firstWhere('response_id', $response->id);

        $this->assertIsArray($adjustment);
        $this->assertSame($response->comment, $adjustment['original_comment']);
        $this->assertSame(7, $adjustment['version']);
        $this->assertSame(['text' => 'Agende sua consulta', 'page' => 'início'], $adjustment['anchor']);
        $this->assertDatabaseCount('demand_tasks', 0);
        $this->assertSame($response->comment, $response->fresh()->comment);
    }

    /** @return array{Organization, User, User, Demand} */
    private function workspace(): array
    {
        $organization = Organization::create(['name' => 'Mix7 Gemma Teste', 'slug' => 'mix7-gemma-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        AiProviderSetting::create([
            'organization_id' => $organization->id,
            'provider' => 'ollama-gemma-local',
            'base_url' => 'http://127.0.0.1:11434/v1',
            'model' => 'gemma3:4b',
            'enabled' => true,
        ]);
        $demand = Demand::create([
            'organization_id' => $organization->id, 'created_by' => $owner->id,
            'title' => 'Demanda fictícia para validar o Gemma',
            'brief' => 'Demanda criada somente em banco de teste local e revertida ao fim do teste.',
            'status' => DemandStatus::Planning,
        ]);

        return [$organization, $owner, $professional, $demand];
    }
}
