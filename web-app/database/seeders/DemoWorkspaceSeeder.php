<?php

namespace Database\Seeders;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandReviewLink;
use App\Models\DemandReviewResponse;
use App\Models\DemandTask;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\Organization;
use App\Models\PerformanceReview;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $connectionConfig = config('database.connections.'.$connection, []);

        if (! app()->environment('local') || ($connectionConfig['driver'] ?? null) !== 'sqlite'
            || ! $this->usesDedicatedDemoDatabase()) {
            throw new RuntimeException('A demonstração só pode ser criada em APP_ENV=local, SQLite e database/mix7-demo.sqlite. Nenhum dado foi alterado.');
        }

        $password = Str::random(32);
        $reviewToken = Str::random(64);

        $showcase = DB::transaction(function () use ($password, $reviewToken): array {
            $organization = Organization::updateOrCreate(
                ['slug' => 'mix7-demo-local'],
                ['name' => 'Mix7 · Demonstração local'],
            );

            $people = [];
            foreach ([
                'agency_owner' => ['Demonstração Direção', 'direcao@mix7-demo.test', UserRole::AgencyOwner],
                'marketing_manager' => ['Demonstração Gerência', 'gerencia@mix7-demo.test', UserRole::MarketingManager],
                'professional' => ['Demonstração Profissional', 'profissional@mix7-demo.test', UserRole::Professional],
                'client' => ['Demonstração Cliente', 'cliente@mix7-demo.test', UserRole::Client],
            ] as $key => [$name, $email, $role]) {
                $people[$key] = User::updateOrCreate(['email' => $email], [
                    'name' => $name,
                    'organization_id' => $organization->id,
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                ]);
            }

            $weekStart = CarbonImmutable::now()->startOfWeek()->startOfDay();
            $dueThisWeek = $weekStart->addDays(2)->toDateString();
            $briefing = Demand::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => '[DEMO] Site institucional'],
                ['created_by' => $people['agency_owner']->id, 'client_user_id' => $people['client']->id, 'brief' => 'Dados fictícios: criar uma página inicial clara para apresentar serviços e facilitar o contato.', 'status' => DemandStatus::Planning],
            );
            $work = Demand::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => '[DEMO] Campanha de lançamento'],
                ['created_by' => $people['marketing_manager']->id, 'client_user_id' => $people['client']->id, 'brief' => 'Dados fictícios: preparar peças e textos para aprovação antes do lançamento.', 'status' => DemandStatus::InProgress],
            );
            $approval = Demand::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => '[DEMO] Criativos para aprovação'],
                ['created_by' => $people['marketing_manager']->id, 'client_user_id' => $people['client']->id, 'brief' => 'Dados fictícios: revisar a peça visual e registrar comentários por link.', 'status' => DemandStatus::ClientApproval],
            );
            $completed = Demand::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => '[DEMO] Atualização de conteúdo'],
                ['created_by' => $people['marketing_manager']->id, 'client_user_id' => $people['client']->id, 'brief' => 'Dados fictícios: atualizar conteúdo aprovado e conferir a entrega.', 'status' => DemandStatus::Completed],
            );

            $this->upsertTask($briefing, $people['agency_owner'], $people['professional'], 'Revisar briefing e propor estrutura', TaskStatus::Todo, 180, $dueThisWeek);
            $this->upsertTask($work, $people['marketing_manager'], $people['professional'], 'Preparar variações do criativo', TaskStatus::InProgress, 120, $dueThisWeek);
            $this->upsertTask($approval, $people['marketing_manager'], $people['professional'], 'Conferir material antes do envio', TaskStatus::Completed, 45, $dueThisWeek, now()->subDay());
            $completedTask = $this->upsertTask($completed, $people['marketing_manager'], $people['professional'], 'Publicar atualização aprovada', TaskStatus::Completed, 60, $weekStart->subDay()->toDateString(), now()->subDays(2));

            $rawToken = $reviewToken;
            $link = DemandReviewLink::updateOrCreate(
                ['demand_id' => $approval->id, 'version' => 1],
                [
                    'organization_id' => $organization->id,
                    'created_by' => $people['marketing_manager']->id,
                    'token_hash' => hash('sha256', $rawToken),
                    'material_url' => 'https://mix7-demo.test/criativo-versao-1',
                    'expires_at' => now()->addDays(14),
                    'revoked_at' => null,
                ],
            );
            DemandReviewResponse::firstOrCreate([
                'demand_review_link_id' => $link->id,
                'reviewer_name' => 'Cliente demonstração',
                'type' => 'annotation',
                'comment' => 'Exemplo fictício: destacar o botão principal.',
            ], [
                'anchor_type' => 'area',
                'anchor_data' => ['url' => $link->material_url, 'x' => 50, 'y' => 55, 'width' => 18, 'height' => 9],
            ]);

            PerformanceReview::updateOrCreate(
                ['task_id' => $completedTask->id, 'reviewer_id' => $people['agency_owner']->id],
                [
                    'organization_id' => $organization->id,
                    'professional_id' => $people['professional']->id,
                    'reviewer_role' => UserRole::AgencyOwner->value,
                    'reviewer_weight' => 2,
                    'deadline_assessment' => 'Exemplo fictício: prazo cumprido conforme o combinado.',
                    'quality_assessment' => 'Exemplo fictício: conteúdo conferido antes da entrega.',
                    'evidence' => 'Checklist de revisão da demonstração.',
                    'external_factors' => null,
                ],
            );

            TeamCapacitySnapshot::updateOrCreate(
                ['organization_id' => $organization->id, 'professional_id' => $people['professional']->id, 'week_start' => $weekStart->toDateString()],
                [
                    'recorded_by' => $people['marketing_manager']->id,
                    'scheduled_minutes' => 2400,
                    'absences' => [['id' => 'demo-absence', 'date' => $weekStart->addDays(4)->toDateString(), 'minutes' => 240]],
                    'change_type' => 'availability_set',
                ],
            );

            $onboarding = KnowledgeItem::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => '[DEMO] Primeira semana na equipe'],
                [
                    'created_by' => $people['marketing_manager']->id,
                    'updated_by' => $people['marketing_manager']->id,
                    'type' => 'onboarding',
                    'content' => 'Trilha fictícia de exemplo; confirme os procedimentos reais com a Mix7.',
                    'owner_name' => 'Demonstração Gerência',
                    'audience' => 'Conta de demonstração',
                    'review_due_at' => $weekStart->addMonth()->toDateString(),
                    'steps' => ['Conhecer a biblioteca interna', 'Revisar uma demanda de exemplo', 'Praticar o fluxo de aprovação'],
                    'archived_at' => null,
                ],
            );
            OnboardingAssignment::updateOrCreate(
                ['knowledge_item_id' => $onboarding->id, 'assigned_to' => $people['professional']->id],
                [
                    'organization_id' => $organization->id,
                    'assigned_by' => $people['marketing_manager']->id,
                    'title' => '[DEMO] Primeira semana na equipe',
                    'steps' => ['Conhecer a biblioteca interna', 'Revisar uma demanda de exemplo', 'Praticar o fluxo de aprovação'],
                ],
            );

            return app(DemoShowcaseSeeder::class)->seed($organization, $people, $password);
        });

        $this->command?->newLine();
        $this->command?->info('Ambiente fictício Mix7 pronto em SQLite local. Contas de demonstração: direcao@mix7-demo.test, gerencia@mix7-demo.test, profissional@mix7-demo.test e cliente@mix7-demo.test.');
        $this->command?->info('Senha temporária desta execução: '.$password);
        $this->command?->info('Link público local de aprovação (versão 1): '.url('/revisao/'.$reviewToken));
        $this->command?->info('Carga fictícia preparada: '.$showcase['users'].' contas (incluindo uma conta profissional inativa), '.($showcase['demands'] + 4).' demandas e '.($showcase['tasks'] + 4).' tarefas.');
        $this->command?->info('Contas adicionais: gerencia02–20@mix7-demo.test, profissional02–100@mix7-demo.test e cliente02–100@mix7-demo.test; todas usam a senha temporária desta execução.');
        foreach ($showcase['review_links'] as $reviewLink) {
            $this->command?->info('Link público de demonstração — '.$reviewLink['title'].': '.url('/revisao/'.$reviewLink['token']));
        }
        $this->command?->warn('Não use estes dados nem esta senha em ambiente compartilhado ou de produção.');
    }

    private function usesDedicatedDemoDatabase(): bool
    {
        $configured = (string) config('database.connections.'.config('database.default').'.database');
        $configured = realpath($configured) ?: (str_starts_with($configured, DIRECTORY_SEPARATOR) ? $configured : base_path($configured));
        $expected = realpath(database_path('mix7-demo.sqlite')) ?: database_path('mix7-demo.sqlite');
        $configured = str_replace('\\', '/', $configured);
        $expected = str_replace('\\', '/', $expected);

        return strtolower(rtrim($configured, DIRECTORY_SEPARATOR)) === strtolower(rtrim($expected, DIRECTORY_SEPARATOR));
    }

    private function upsertTask(
        Demand $demand,
        User $creator,
        User $professional,
        string $title,
        TaskStatus $status,
        int $estimateMinutes,
        string $dueDate,
        ?Carbon $completedAt = null,
    ): DemandTask {
        return DemandTask::updateOrCreate(
            ['demand_id' => $demand->id, 'title' => '[DEMO] '.$title],
            [
                'organization_id' => $demand->organization_id,
                'created_by' => $creator->id,
                'assigned_to' => $professional->id,
                'description' => 'Registro fictício para testar permissões, cronômetro e cronograma.',
                'status' => $status,
                'estimate_minutes' => $estimateMinutes,
                'planned_start_on' => CarbonImmutable::parse($dueDate)->subDays(2)->toDateString(),
                'planned_due_on' => $dueDate,
                'completed_at' => $completedAt,
            ],
        );
    }
}
