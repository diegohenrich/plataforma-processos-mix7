<?php

namespace Database\Seeders;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandDeliveryEvidence;
use App\Models\DemandEvent;
use App\Models\DemandModuleDefinition;
use App\Models\DemandModuleStep;
use App\Models\DemandReviewLink;
use App\Models\DemandReviewResponse;
use App\Models\DemandTask;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\Organization;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewResponse;
use App\Models\ServiceAccess;
use App\Models\ServiceAccessEvent;
use App\Models\ServiceAccessRequest;
use App\Models\TaskTimeEntry;
use App\Models\TeamCapacitySnapshot;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoShowcaseSeeder
{
    private const MANAGER_COUNT = 20;

    private const PROFESSIONAL_COUNT = 100;

    private const CLIENT_COUNT = 100;

    private const DEMANDS_PER_STAGE = 50;

    /** @return array{users: int, demands: int, tasks: int, review_links: array<int, array{title: string, token: string}>} */
    public function seed(Organization $organization, array $corePeople, string $password): array
    {
        /** @var User $owner */
        $owner = $corePeople['agency_owner'];
        /** @var User $baseManager */
        $baseManager = $corePeople['marketing_manager'];
        /** @var User $baseProfessional */
        $baseProfessional = $corePeople['professional'];
        /** @var User $baseClient */
        $baseClient = $corePeople['client'];

        $managers = [$baseManager];
        foreach (range(2, self::MANAGER_COUNT) as $number) {
            $managers[] = $this->upsertUser($organization, $password, sprintf('Gerência de demonstração %02d', $number), sprintf('gerencia%02d@mix7-demo.test', $number), UserRole::MarketingManager);
        }

        $professionals = [$baseProfessional];
        $specialties = [
            ['Design', 'Criativos'], ['Conteúdo', 'Redação'], ['Desenvolvimento', 'Sites'],
            ['Vídeo', 'Edição'], ['Mídia paga', 'Campanhas'], ['Social', 'Planejamento'],
            ['Design', 'Apresentações'], ['Conteúdo', 'Revisão'], ['Desenvolvimento', 'Integrações'],
        ];
        foreach (range(2, self::PROFESSIONAL_COUNT) as $number) {
            $professionals[] = $this->upsertUser(
                $organization,
                $password,
                sprintf('Profissional de demonstração %02d', $number),
                sprintf('profissional%02d@mix7-demo.test', $number),
                UserRole::Professional,
                $specialties[($number - 2) % count($specialties)],
            );
        }
        $inactiveProfessional = $this->upsertUser(
            $organization,
            $password,
            'Profissional de demonstração inativo',
            'profissional-inativo@mix7-demo.test',
            UserRole::Professional,
            ['Design'],
            active: false,
        );

        $clients = [$baseClient];
        foreach (range(2, self::CLIENT_COUNT) as $number) {
            $clients[] = $this->upsertUser($organization, $password, sprintf('Cliente de demonstração %02d', $number), sprintf('cliente%02d@mix7-demo.test', $number), UserRole::Client);
        }

        $modules = $this->upsertModules($organization, $owner);
        $reviewLinks = [];
        $tasksCreated = 0;
        $statuses = DemandStatus::cases();
        $services = [
            ['label' => 'Criativos de campanha', 'module' => 'social_creative', 'brief' => 'Preparar variações visuais e textos curtos para canais sociais, com revisão antes do envio.'],
            ['label' => 'Site institucional', 'module' => 'website_review', 'brief' => 'Atualizar uma página do site, revisar conteúdo e conferir a versão enviada ao cliente.'],
            ['label' => 'Campanha de e-mail', 'module' => 'email_campaign', 'brief' => 'Planejar assunto, público e conteúdo de uma campanha de e-mail de demonstração.'],
            ['label' => 'Mídia paga', 'module' => 'paid_media_campaign', 'brief' => 'Organizar peças, público e destino de uma campanha fictícia de mídia paga.'],
            ['label' => 'Artigo para blog', 'module' => 'blog_article', 'brief' => 'Produzir um artigo de demonstração com palavra-chave, revisão e chamada para ação.'],
        ];
        $taskTitles = ['Preparar briefing e referências', 'Produzir primeira versão', 'Conferir entrega e registrar retorno'];
        $stageTaskStatuses = [
            DemandStatus::Received->value => [TaskStatus::Todo, TaskStatus::Blocked, TaskStatus::Todo],
            DemandStatus::Planning->value => [TaskStatus::Todo, TaskStatus::Paused, TaskStatus::Todo],
            DemandStatus::InProgress->value => [TaskStatus::InProgress, TaskStatus::Blocked, TaskStatus::Todo],
            DemandStatus::InternalReview->value => [TaskStatus::Completed, TaskStatus::Completed, TaskStatus::Completed],
            DemandStatus::ClientApproval->value => [TaskStatus::Completed, TaskStatus::Completed, TaskStatus::Completed],
            DemandStatus::Adjustments->value => [TaskStatus::Completed, TaskStatus::InProgress, TaskStatus::Todo],
            DemandStatus::Delivery->value => [TaskStatus::Completed, TaskStatus::Completed, TaskStatus::Completed],
            DemandStatus::Completed->value => [TaskStatus::Completed, TaskStatus::Completed, TaskStatus::Completed],
        ];
        $weekStart = CarbonImmutable::now()->startOfWeek()->startOfDay();

        foreach ($statuses as $stageIndex => $status) {
            foreach (range(1, self::DEMANDS_PER_STAGE) as $sample) {
                // Preserve the identifiers of the original 20-per-stage dataset and append new examples after it.
                $sequence = $sample <= 20
                    ? ($sample <= 4
                        ? ($stageIndex * 4) + $sample
                        : ($sample <= 6
                            ? 32 + ($stageIndex * 2) + ($sample - 4)
                            : 48 + ($stageIndex * 14) + ($sample - 6)))
                    : 161 + ($stageIndex * (self::DEMANDS_PER_STAGE - 20)) + ($sample - 21);
                $service = $services[($sequence - 1) % count($services)];
                $manager = $managers[($sequence - 1) % count($managers)];
                $client = $clients[($sequence - 1) % count($clients)];
                $professional = $professionals[($sequence - 1) % count($professionals)];
                $module = $modules[$service['module']] ?? null;
                $title = sprintf('[DEMO] %s #%02d — %s', $service['label'], $sequence, $status->label());
                $fieldsData = $this->sampleModuleData($service['module'], $sequence);
                $demand = Demand::updateOrCreate(
                    ['organization_id' => $organization->id, 'title' => $title],
                    [
                        'created_by' => $manager->id,
                        'client_user_id' => $client->id,
                        'brief_author_id' => $sequence % 3 === 0 ? null : $manager->id,
                        'intake_source' => ['E-mail', 'WhatsApp', 'Reunião', 'Formulário'][($sequence - 1) % 4],
                        'brief' => sprintf('Dado fictício #%02d. %s Público e conteúdo inventados apenas para navegar pela demonstração.', $sequence, $service['brief']),
                        'ai_summary' => $sequence % 4 === 0 ? 'Resumo sintético para demonstrar cartões compactos; não foi gerado por uma IA.' : null,
                        'module_key' => $service['module'],
                        'module_version' => $module?->config_version ?? 1,
                        'module_label' => $module?->label ?? ($service['module'] === 'social_creative' ? 'Criativo para redes sociais' : 'Revisão de site'),
                        'module_fields_schema' => $module?->fields ?? [],
                        'module_fields_data' => $fieldsData,
                        'status' => $status,
                    ],
                );

                $this->createDemandEvent($organization, $demand, $manager, 'demo_stage', '[DEMO] Registro de demonstração na etapa '.$status->label(), null, $status->value, now()->subDays(31 - $sequence));
                if ($module) {
                    $this->seedModuleSteps($organization, $demand, $manager, $module->workflow_steps ?? [], $status, $stageIndex);
                }

                $demandTasks = [];
                foreach ($taskTitles as $taskIndex => $taskTitle) {
                    $taskStatus = $stageTaskStatuses[$status->value][$taskIndex];
                    $assigned = $professionals[($sequence + $taskIndex) % count($professionals)];
                    $dueDate = $weekStart->addDays(($sequence + $taskIndex) % 14 - 4)->addWeeks(intdiv($sequence, 10))->toDateString();
                    $completedAt = $taskStatus === TaskStatus::Completed ? now()->subDays(1 + (($sequence + $taskIndex) % 10)) : null;
                    $task = DemandTask::updateOrCreate(
                        ['demand_id' => $demand->id, 'title' => '[DEMO] '.$taskTitle],
                        [
                            'organization_id' => $organization->id,
                            'created_by' => $manager->id,
                            'assigned_to' => $assigned->id,
                            'description' => 'Exemplo fictício para testar atribuição, estimativa, dependências e andamento.',
                            'status' => $taskStatus,
                            'estimate_minutes' => [60, 120, 180][$taskIndex],
                            'planned_start_on' => CarbonImmutable::parse($dueDate)->subDays(3)->toDateString(),
                            'planned_due_on' => $dueDate,
                            'completed_at' => $completedAt,
                        ],
                    );
                    $demandTasks[] = $task;
                    $tasksCreated++;
                    $this->createDemandEvent(
                        $organization,
                        $demand,
                        $manager,
                        'task_assigned',
                        '[DEMO] '.$manager->name.' atribuiu a tarefa a '.$assigned->name.'.',
                        null,
                        null,
                        now()->subDays(31 - $sequence),
                        $task,
                    );

                    if ($taskIndex === 1 && $sequence % 2 === 0 && isset($demandTasks[0])) {
                        $task->dependencies()->sync([$demandTasks[0]->id]);
                    }
                    if ($taskStatus === TaskStatus::Completed || $taskStatus === TaskStatus::InProgress) {
                        $this->seedTimeEntry($organization, $task, $assigned, $sequence, $taskIndex);
                    }
                }

                if (in_array($status, [DemandStatus::Delivery, DemandStatus::Completed], true)) {
                    $this->seedDeliveryEvidence($organization, $demand, $manager, $sequence);
                    foreach ($demandTasks as $taskIndex => $task) {
                        if ($taskIndex === 0 || $sequence % 2 === 0) {
                            $review = $this->seedPerformanceReview($organization, $task, $manager, $owner, $taskIndex, $sequence);
                            if ($sequence % 3 === 0) {
                                PerformanceReviewResponse::updateOrCreate(
                                    ['performance_review_id' => $review->id, 'user_id' => $task->assigned_to],
                                    ['response' => 'Resposta fictícia: recebi o retorno e registrei o próximo passo de melhoria.'],
                                );
                            }
                        }
                    }
                }

                if ($status === DemandStatus::ClientApproval) {
                    $reviewLinks[] = $this->seedReviewVersions($organization, $demand, $manager, $client, $sequence, count($reviewLinks));
                }
            }
        }

        $this->seedActiveTimer($organization, $this->findDemand($organization, '[DEMO] Campanha de lançamento'), $baseProfessional);
        $this->seedInternalPdf($organization, $owner, $this->findDemand($organization, '[DEMO] Site institucional'));
        $this->seedCapacity($organization, $baseManager, $professionals);
        $this->seedKnowledge($organization, $baseManager, $professionals);
        $this->seedAccessCatalog($organization, $owner, $managers, $professionals);
        $this->seedPendingInvitation($organization, $owner);

        return [
            'users' => 1 + count($managers) + count($professionals) + 1 + count($clients),
            'demands' => count($statuses) * self::DEMANDS_PER_STAGE,
            'tasks' => $tasksCreated,
            'review_links' => $reviewLinks,
            'inactive_user' => $inactiveProfessional->email,
        ];
    }

    private function upsertUser(Organization $organization, string $password, string $name, string $email, UserRole $role, array $specialties = [], bool $active = true): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'organization_id' => $organization->id,
            'role' => $role,
            'specialties' => $specialties,
            'is_active' => $active,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);
    }

    /** @return array<string, DemandModuleDefinition> */
    private function upsertModules(Organization $organization, User $owner): array
    {
        $definitions = [
            'email_campaign' => [
                'label' => 'Campanha de e-mail',
                'description' => 'Campos e conferências fictícias para a demonstração de campanhas de e-mail.',
                'fields' => [
                    ['key' => 'campaign_goal', 'label' => 'Objetivo', 'type' => 'textarea', 'required' => true, 'options' => []],
                    ['key' => 'audience', 'label' => 'Público', 'type' => 'text', 'required' => true, 'options' => []],
                    ['key' => 'subject_line', 'label' => 'Assunto', 'type' => 'text', 'required' => false, 'options' => []],
                ],
                'workflow_steps' => [
                    ['key' => 'briefing', 'label' => 'Briefing conferido'],
                    ['key' => 'copy_review', 'label' => 'Texto revisado'],
                    ['key' => 'links_checked', 'label' => 'Links conferidos'],
                ],
            ],
            'paid_media_campaign' => [
                'label' => 'Campanha de mídia paga',
                'description' => 'Campos e conferências fictícias para planejar peças de mídia paga.',
                'fields' => [
                    ['key' => 'platform', 'label' => 'Plataforma', 'type' => 'select', 'required' => true, 'options' => ['Meta Ads', 'Google Ads', 'LinkedIn Ads']],
                    ['key' => 'landing_page', 'label' => 'Página de destino', 'type' => 'url', 'required' => false, 'options' => []],
                ],
                'workflow_steps' => [
                    ['key' => 'audience', 'label' => 'Público revisado'],
                    ['key' => 'creative', 'label' => 'Criativo conferido'],
                    ['key' => 'destination', 'label' => 'Destino validado'],
                ],
            ],
            'blog_article' => [
                'label' => 'Artigo para blog',
                'description' => 'Campos e conferências fictícias para produzir conteúdo editorial.',
                'fields' => [
                    ['key' => 'keyword', 'label' => 'Palavra-chave', 'type' => 'text', 'required' => true, 'options' => []],
                    ['key' => 'editorial_date', 'label' => 'Data editorial', 'type' => 'date', 'required' => false, 'options' => []],
                ],
                'workflow_steps' => [
                    ['key' => 'research', 'label' => 'Referências conferidas'],
                    ['key' => 'copy_review', 'label' => 'Texto revisado'],
                    ['key' => 'links_checked', 'label' => 'Links revisados'],
                ],
            ],
        ];

        $modules = [];
        foreach ($definitions as $key => $definition) {
            $modules[$key] = DemandModuleDefinition::updateOrCreate(
                ['organization_id' => $organization->id, 'key' => $key],
                [
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'config_version' => 1,
                    'fields' => $definition['fields'],
                    'workflow_steps' => $definition['workflow_steps'],
                    'is_active' => true,
                ],
            );
        }

        return $modules;
    }

    private function sampleModuleData(string $moduleKey, int $sequence): array
    {
        return match ($moduleKey) {
            'email_campaign' => [
                'campaign_goal' => 'Apresentar um serviço fictício e gerar visitas qualificadas.',
                'audience' => 'Público de demonstração '.$sequence,
                'subject_line' => 'Uma novidade para conhecer',
            ],
            'paid_media_campaign' => [
                'platform' => ['Meta Ads', 'Google Ads', 'LinkedIn Ads'][$sequence % 3],
                'landing_page' => 'https://example.com/campanha-'.$sequence,
            ],
            'blog_article' => [
                'keyword' => 'ideias de marketing '.$sequence,
                'editorial_date' => now()->addDays($sequence)->toDateString(),
            ],
            default => [],
        };
    }

    private function seedModuleSteps(Organization $organization, Demand $demand, User $manager, array $steps, DemandStatus $status, int $stageIndex): void
    {
        $completedCount = match ($status) {
            DemandStatus::Received => 0,
            DemandStatus::Planning => 1,
            DemandStatus::InProgress, DemandStatus::Adjustments => 2,
            default => count($steps),
        };

        foreach ($steps as $position => $step) {
            $completed = $position < $completedCount;
            DemandModuleStep::updateOrCreate(
                ['demand_id' => $demand->id, 'key' => $step['key']],
                [
                    'organization_id' => $organization->id,
                    'label' => $step['label'],
                    'position' => $position + 1,
                    'completed_by' => $completed ? $manager->id : null,
                    'completed_at' => $completed ? now()->subDays(max(0, count(DemandStatus::cases()) - $stageIndex)) : null,
                ],
            );
        }
    }

    private function createDemandEvent(Organization $organization, Demand $demand, User $actor, string $type, string $summary, ?string $fromStatus, ?string $toStatus, mixed $createdAt, ?DemandTask $task = null): void
    {
        $query = DemandEvent::query()
            ->where('organization_id', $organization->id)
            ->where('demand_id', $demand->id)
            ->where('event_type', $type)
            ->where('summary', $summary);
        $task ? $query->where('task_id', $task->id) : $query->whereNull('task_id');

        if ($query->exists()) {
            return;
        }

        DemandEvent::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'task_id' => $task?->id,
            'actor_id' => $actor->id,
            'event_type' => $type,
            'summary' => $summary,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'created_at' => $createdAt,
        ]);
    }

    private function seedTimeEntry(Organization $organization, DemandTask $task, User $professional, int $sequence, int $taskIndex): void
    {
        $endedAt = CarbonImmutable::now()->subDays($sequence % 7)->subHours(2 + $taskIndex);
        $startedAt = $endedAt->subMinutes(25 + ($sequence % 6) * 10);
        TaskTimeEntry::firstOrCreate(
            ['task_id' => $task->id, 'user_id' => $professional->id, 'started_at' => $startedAt],
            [
                'organization_id' => $organization->id,
                'last_heartbeat_at' => $endedAt,
                'ended_at' => $endedAt,
            ],
        );
    }

    private function seedActiveTimer(Organization $organization, Demand $demand, User $professional): void
    {
        $task = DemandTask::query()
            ->where('demand_id', $demand->id)
            ->where('assigned_to', $professional->id)
            ->where('status', TaskStatus::InProgress)
            ->firstOrFail();
        $entry = TaskTimeEntry::query()
            ->where('task_id', $task->id)
            ->where('user_id', $professional->id)
            ->whereNull('ended_at')
            ->first();
        $values = [
            'organization_id' => $organization->id,
            'started_at' => now()->subMinutes(4),
            'last_heartbeat_at' => now(),
            'ended_at' => null,
        ];

        if ($entry) {
            $entry->update($values);

            return;
        }

        TaskTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $professional->id,
            ...$values,
        ]);
    }

    private function seedReviewVersions(Organization $organization, Demand $demand, User $manager, User $client, int $sequence, int $index): array
    {
        $oldToken = Str::random(64);
        $oldLink = DemandReviewLink::updateOrCreate(
            ['demand_id' => $demand->id, 'version' => 1],
            [
                'organization_id' => $organization->id,
                'created_by' => $manager->id,
                'token_hash' => hash('sha256', $oldToken),
                'material_url' => 'https://example.com/demo/versao-anterior-'.$sequence,
                'expires_at' => now()->addDays(14),
                'revoked_at' => now()->subDay(),
            ],
        );
        $this->createDemoReviewResponse($oldLink, $client, 'changes_requested', '[DEMO] Ajustar um detalhe antes do novo envio.', ['type' => 'area', 'x' => 48, 'y' => 36, 'width' => 18, 'height' => 14]);

        $token = Str::random(64);
        $linkData = [
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(14),
            'revoked_at' => null,
            'material_url' => 'https://example.com/demo/versao-atual-'.$sequence,
            'material_file_path' => null,
            'material_file_name' => null,
            'material_mime' => null,
            'material_file_size' => null,
        ];
        if ($index === 0) {
            $path = "review-materials/{$organization->id}/{$demand->id}/demo-criativo.pdf";
            $this->writeSamplePdf($path);
            $linkData = array_merge($linkData, [
                'material_url' => null,
                'material_file_path' => $path,
                'material_file_name' => 'Criativo-ficticio.pdf',
                'material_mime' => 'application/pdf',
                'material_file_size' => Storage::disk('local')->size($path),
            ]);
        } elseif ($index === 1) {
            $path = "review-materials/{$organization->id}/{$demand->id}/demo-peca.png";
            Storage::disk('local')->put($path, file_get_contents(public_path('images/mix7-logo-round.png')));
            $linkData = array_merge($linkData, [
                'material_url' => null,
                'material_file_path' => $path,
                'material_file_name' => 'Peca-visual-ficticia.png',
                'material_mime' => 'image/png',
                'material_file_size' => Storage::disk('local')->size($path),
            ]);
        }

        $activeLink = DemandReviewLink::updateOrCreate(['demand_id' => $demand->id, 'version' => 2], $linkData);
        $this->createDemoReviewResponse($activeLink, $client, 'annotation', '[DEMO] Conferir a hierarquia visual e o destaque principal.', ['type' => 'area', 'x' => 48, 'y' => 36, 'width' => 18, 'height' => 14]);

        return ['title' => $demand->title, 'token' => $token];
    }

    private function createDemoReviewResponse(DemandReviewLink $link, User $client, string $type, string $comment, array $anchor): void
    {
        $response = DemandReviewResponse::firstOrNew([
            'demand_review_link_id' => $link->id,
            'reviewer_name' => $client->name,
            'type' => $type,
            'comment' => $comment,
        ]);
        if (! $response->exists) {
            $response->anchor_type = 'area';
            $response->anchor_data = [...$anchor, 'url' => $link->material_url ?? 'arquivo da versão '.$link->version];
            $response->save();
        }
    }

    private function seedDeliveryEvidence(Organization $organization, Demand $demand, User $manager, int $sequence): void
    {
        $outcome = ['delivered', 'scheduled', 'published'][$sequence % 3];
        DemandDeliveryEvidence::firstOrCreate(
            ['demand_id' => $demand->id, 'outcome' => $outcome, 'details' => '[DEMO] Evidência fictícia de '.$outcome.'; não houve publicação real.'],
            [
                'organization_id' => $organization->id,
                'recorded_by' => $manager->id,
                'evidence_url' => 'https://example.com/demo/entrega-'.$sequence,
                'occurred_at' => now()->subDays($sequence % 9),
            ],
        );
    }

    private function seedPerformanceReview(Organization $organization, DemandTask $task, User $manager, User $owner, int $taskIndex, int $sequence): PerformanceReview
    {
        $reviewer = $sequence % 2 === 0 ? $owner : $manager;
        $weight = $reviewer->role === UserRole::AgencyOwner ? 2 : 1;

        return PerformanceReview::updateOrCreate(
            ['task_id' => $task->id, 'reviewer_id' => $reviewer->id],
            [
                'organization_id' => $organization->id,
                'professional_id' => $task->assigned_to,
                'reviewer_role' => $reviewer->role->value,
                'reviewer_weight' => $weight,
                'deadline_assessment' => 'Exemplo fictício: prazo avaliado com base no combinado da demonstração.',
                'quality_assessment' => $taskIndex % 2 === 0 ? 'Exemplo fictício: material conferido antes do envio.' : 'Exemplo fictício: revisão necessária em um trecho específico.',
                'evidence' => 'Registro de demonstração; não representa avaliação de pessoa real.',
                'external_factors' => $sequence % 3 === 0 ? 'Exemplo fictício: dependência aguardando material.' : null,
            ],
        );
    }

    private function seedInternalPdf(Organization $organization, User $owner, Demand $demand): void
    {
        $path = "demand-attachments/{$organization->id}/{$demand->id}/briefing-ficticio.pdf";
        $this->writeSamplePdf($path);
        DemandAttachment::updateOrCreate(
            ['file_path' => $path],
            [
                'organization_id' => $organization->id,
                'demand_id' => $demand->id,
                'uploaded_by' => $owner->id,
                'original_name' => 'Briefing-ficticio-Mix7.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => Storage::disk('local')->size($path),
            ],
        );
    }

    private function writeSamplePdf(string $path): void
    {
        $stream = "BT\n/F1 20 Tf\n72 720 Td\n(Documento ficticio Mix7) Tj\n/F1 12 Tf\n0 -30 Td\n(Demonstracao local de briefing, visualizacao e aprovacao.) Tj\n0 -22 Td\n(Nenhum dado de cliente real foi usado.) Tj\nET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
        Storage::disk('local')->put($path, $pdf);
    }

    private function seedCapacity(Organization $organization, User $manager, array $professionals): void
    {
        $weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
        foreach ($professionals as $index => $professional) {
            TeamCapacitySnapshot::updateOrCreate(
                ['organization_id' => $organization->id, 'professional_id' => $professional->id, 'week_start' => $weekStart],
                [
                    'recorded_by' => $manager->id,
                    'scheduled_minutes' => 2100 + ($index % 4) * 240,
                    'absences' => $index % 3 === 0 ? [['id' => 'demo-absence-'.$professional->id, 'date' => now()->addDays(3)->toDateString(), 'minutes' => 240]] : [],
                    'change_type' => 'availability_set',
                ],
            );
        }
    }

    private function seedKnowledge(Organization $organization, User $manager, array $professionals): void
    {
        $items = [
            ['type' => 'reference', 'title' => '[DEMO] Guia de identidade fictício', 'content' => 'Exemplo: confirmar marca, contraste e arquivos aprovados antes de iniciar uma peça.', 'url' => 'https://example.com/guia-ficticio', 'steps' => null],
            ['type' => 'training', 'title' => '[DEMO] Treinamento do fluxo de aprovação', 'content' => 'Exemplo de treinamento sobre versões, comentários e registro de decisão.', 'url' => null, 'steps' => ['Abrir uma demanda fictícia', 'Conferir o histórico', 'Preparar uma versão de revisão']],
            ['type' => 'contact', 'title' => '[DEMO] Contato de teste da agência', 'content' => 'Contato inventado para testar busca e categorização. Não corresponde a uma pessoa real.', 'url' => null, 'steps' => null],
            ['type' => 'onboarding', 'title' => '[DEMO] Onboarding de primeira semana', 'content' => 'Trilha sintética para testar atribuição e acompanhamento de etapas.', 'url' => null, 'steps' => ['Conhecer a equipe', 'Ler o guia de referência', 'Praticar uma revisão por link']],
        ];

        foreach ($items as $item) {
            $knowledge = KnowledgeItem::updateOrCreate(
                ['organization_id' => $organization->id, 'title' => $item['title']],
                [
                    'created_by' => $manager->id,
                    'updated_by' => $manager->id,
                    'type' => $item['type'],
                    'content' => $item['content'],
                    'owner_name' => $manager->name,
                    'audience' => 'Demonstração local',
                    'review_due_at' => now()->addMonth()->toDateString(),
                    'url' => $item['url'],
                    'steps' => $item['steps'],
                    'archived_at' => null,
                ],
            );

            if ($item['type'] !== 'onboarding') {
                continue;
            }

            foreach ($professionals as $index => $professional) {
                $assignment = OnboardingAssignment::updateOrCreate(
                    ['knowledge_item_id' => $knowledge->id, 'assigned_to' => $professional->id],
                    [
                        'organization_id' => $organization->id,
                        'assigned_by' => $manager->id,
                        'title' => $knowledge->title,
                        'steps' => $item['steps'],
                    ],
                );
                foreach ($item['steps'] as $position => $stepTitle) {
                    $done = $index % 3 === 0 && $position === 0;
                    $assignment->steps()->updateOrCreate(
                        ['position' => $position + 1],
                        [
                            'title' => $stepTitle,
                            'completed_by' => $done ? $professional->id : null,
                            'completed_at' => $done ? now()->subDay() : null,
                        ],
                    );
                }
            }
        }
    }

    private function seedAccessCatalog(Organization $organization, User $owner, array $managers, array $professionals): void
    {
        $services = [
            ['name' => '[DEMO] Canva — convite individual', 'url' => 'https://www.canva.com/', 'method' => 'vendor_invitation'],
            ['name' => '[DEMO] Meta Business — perfil da empresa', 'url' => 'https://business.facebook.com/', 'method' => 'vendor_invitation'],
            ['name' => '[DEMO] Google Drive — conta da equipe', 'url' => 'https://drive.google.com/', 'method' => 'company_sso'],
            ['name' => '[DEMO] Gerenciador externo de acessos', 'url' => null, 'method' => 'external_secret_manager'],
        ];
        $statuses = ['pending', 'granted', 'denied', 'revoked'];
        $manager = $managers[0];

        foreach ($services as $index => $data) {
            $service = ServiceAccess::updateOrCreate(
                ['organization_id' => $organization->id, 'name' => $data['name']],
                [
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'service_url' => $data['url'],
                    'access_method' => $data['method'],
                    'instructions' => 'Exemplo fictício: use conta individual, convite do fornecedor ou SSO. Nenhuma senha é armazenada pela demonstração.',
                    'review_due_on' => now()->addMonths(3)->toDateString(),
                    'archived_at' => null,
                ],
            );
            $status = $statuses[$index];
            $professional = $professionals[$index % count($professionals)];
            $accessRequest = ServiceAccessRequest::updateOrCreate(
                ['service_access_id' => $service->id, 'user_id' => $professional->id],
                [
                    'organization_id' => $organization->id,
                    'reviewed_by' => in_array($status, ['granted', 'denied', 'revoked'], true) ? $owner->id : null,
                    'status' => $status,
                    'active_slot' => in_array($status, ['pending', 'granted'], true) ? $service->id.':'.$professional->id : null,
                    'requested_at' => now()->subDays($index + 1),
                    'reviewed_at' => $status === 'pending' ? null : now()->subDays($index),
                ],
            );

            $this->firstServiceEvent($organization, $service, $owner, 'service_created');
            $this->firstServiceEvent($organization, $service, $professional, 'access_requested', $accessRequest);
            if ($status !== 'pending') {
                $event = match ($status) {
                    'granted' => 'access_granted',
                    'denied' => 'access_denied',
                    default => 'access_revoked',
                };
                $this->firstServiceEvent($organization, $service, $owner, $event, $accessRequest);
            }
        }
    }

    private function firstServiceEvent(Organization $organization, ServiceAccess $service, User $actor, string $type, ?ServiceAccessRequest $request = null): void
    {
        ServiceAccessEvent::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'service_access_id' => $service->id,
                'service_access_request_id' => $request?->id,
                'actor_id' => $actor->id,
                'event_type' => $type,
            ],
            ['created_at' => now()],
        );
    }

    private function seedPendingInvitation(Organization $organization, User $owner): void
    {
        TeamInvitation::updateOrCreate(
            ['organization_id' => $organization->id, 'email' => 'novo-profissional@mix7-demo.test'],
            [
                'invited_by' => $owner->id,
                'name' => 'Pessoa convidada de demonstração',
                'role' => UserRole::Professional,
                'token_hash' => hash('sha256', Str::random(64)),
                'expires_at' => now()->addDays(3),
                'accepted_at' => null,
                'revoked_at' => null,
            ],
        );
    }

    private function findDemand(Organization $organization, string $title): Demand
    {
        return Demand::query()->where('organization_id', $organization->id)->where('title', $title)->firstOrFail();
    }
}
