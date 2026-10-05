<?php

namespace App\Services;

use App\Models\DemandTaskAssignment;
use App\Models\PerformanceReview;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyTeamHistory
{
    /**
     * @param  Collection<int, User>  $professionals  People whose rows may be shown to this viewer.
     * @param  Collection<int, User>  $cohort  Active professionals used only to calculate the comparison threshold.
     * @return Collection<int, array{key: string, label: string, current: bool, rows: Collection<int, array<string, mixed>>}>
     */
    public function build(int $organizationId, Collection $professionals, Collection $cohort, CarbonImmutable $now): Collection
    {
        $professionalIds = $cohort->modelKeys();
        $history = [];
        $monthKeys = [$now->format('Y-m')];

        $recordFor = function (int $professionalId, string $month) use (&$history, &$monthKeys): array {
            $monthKeys[] = $month;
            $history[$professionalId][$month] ??= [
                'completed' => 0,
                'recorded_seconds' => 0,
                'reviews' => 0,
                'task_scores' => [],
                'acceptance_seconds' => [],
                'execution_seconds' => [],
            ];

            return $history[$professionalId][$month];
        };

        if ($professionalIds !== []) {
            DemandTaskAssignment::query()
                ->where('organization_id', $organizationId)
                ->whereIn('professional_id', $professionalIds)
                ->whereNotNull('completed_at')
                ->orderBy('completed_at')
                ->cursor()
                ->each(function (DemandTaskAssignment $assignment) use (&$history, $recordFor): void {
                    $month = $assignment->completed_at->format('Y-m');
                    $recordFor($assignment->professional_id, $month);
                    $history[$assignment->professional_id][$month]['completed']++;

                    if ($assignment->accepted_at) {
                        $history[$assignment->professional_id][$month]['acceptance_seconds'][] = max(0, (int) $assignment->assigned_at->diffInSeconds($assignment->accepted_at));
                        $history[$assignment->professional_id][$month]['execution_seconds'][] = max(0, (int) $assignment->accepted_at->diffInSeconds($assignment->completed_at));
                    }
                });

            TaskTimeEntry::query()
                ->where('organization_id', $organizationId)
                ->whereIn('user_id', $professionalIds)
                ->orderBy('started_at')
                ->cursor()
                ->each(function (TaskTimeEntry $entry) use (&$history, $recordFor, $now): void {
                    $month = $entry->started_at->format('Y-m');
                    $recordFor($entry->user_id, $month);
                    $end = $entry->ended_at ?? $now;
                    $history[$entry->user_id][$month]['recorded_seconds'] += max(0, (int) $entry->started_at->diffInSeconds($end));
                });

            PerformanceReview::query()
                ->join('demand_tasks', 'demand_tasks.id', '=', 'performance_reviews.task_id')
                ->where('performance_reviews.organization_id', $organizationId)
                ->whereIn('performance_reviews.professional_id', $professionalIds)
                ->whereNotNull('demand_tasks.completed_at')
                ->orderBy('demand_tasks.completed_at')
                ->whereNotNull('performance_reviews.deadline_score')
                ->whereNotNull('performance_reviews.quality_score')
                ->orderBy('demand_tasks.id')
                ->cursor([
                    'performance_reviews.professional_id',
                    'performance_reviews.task_id',
                    'performance_reviews.deadline_score',
                    'performance_reviews.quality_score',
                    'demand_tasks.completed_at',
                ])
                ->each(function (object $review) use (&$history, $recordFor): void {
                    $month = CarbonImmutable::parse($review->completed_at)->format('Y-m');
                    $recordFor((int) $review->professional_id, $month);
                    $history[$review->professional_id][$month]['reviews']++;
                    $taskId = (int) $review->task_id;
                    $history[$review->professional_id][$month]['task_scores'][$taskId][] = (int) $review->deadline_score + (int) $review->quality_score;
                });
        }

        $scoresByMonth = [];
        foreach ($history as $professionalId => $monthsForProfessional) {
            foreach ($monthsForProfessional as $month => $row) {
                $taskScores = collect($row['task_scores'])->map(fn (array $scores): float => array_sum($scores) / count($scores));
                $scoresByMonth[$month][$professionalId] = [
                    'score' => $taskScores->isEmpty() ? null : round($taskScores->avg(), 2),
                    'scored_tasks' => $taskScores->count(),
                ];
            }
        }

        $topQuarterByMonth = [];
        foreach ($scoresByMonth as $month => $monthlyScores) {
            $eligible = collect($monthlyScores)
                ->filter(fn (array $score): bool => $score['scored_tasks'] >= 3 && $score['score'] !== null)
                ->sortByDesc('score');
            if ($eligible->count() < 4) {
                $topQuarterByMonth[$month] = [];

                continue;
            }

            $topCount = max(1, (int) floor($eligible->count() * 0.25));
            $cutoff = $eligible->values()->get($topCount - 1)['score'];
            $topQuarterByMonth[$month] = $eligible->filter(fn (array $score): bool => $score['score'] >= $cutoff)->keys()->map(fn ($id): int => (int) $id)->all();
        }

        $firstMonth = min($monthKeys);
        $first = CarbonImmutable::createFromFormat('!Y-m', $firstMonth);
        $months = [];
        for ($month = $first; $month->lessThanOrEqualTo($now->startOfMonth()); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return collect($months)->reverse()->values()->map(function (CarbonImmutable $month) use ($professionals, $history, $scoresByMonth, $topQuarterByMonth, $now): array {
            $key = $month->format('Y-m');

            return [
                'key' => $key,
                'label' => $month->format('m/Y'),
                'current' => $key === $now->format('Y-m'),
                'rows' => $professionals->map(function (User $professional) use ($history, $scoresByMonth, $topQuarterByMonth, $key): array {
                    $row = $history[$professional->id][$key] ?? [];
                    $average = static fn (array $values): ?int => $values === [] ? null : (int) round(array_sum($values) / count($values));

                    return [
                        'user' => $professional,
                        'completed' => $row['completed'] ?? 0,
                        'recorded_seconds' => $row['recorded_seconds'] ?? 0,
                        'reviews' => $row['reviews'] ?? 0,
                        'monthly_score' => $scoresByMonth[$key][$professional->id]['score'] ?? null,
                        'scored_tasks' => $scoresByMonth[$key][$professional->id]['scored_tasks'] ?? 0,
                        'top_quartile' => in_array($professional->id, $topQuarterByMonth[$key] ?? [], true),
                        'average_acceptance_seconds' => $average($row['acceptance_seconds'] ?? []),
                        'average_execution_seconds' => $average($row['execution_seconds'] ?? []),
                        'average_acceptance_label' => $this->formatDuration($average($row['acceptance_seconds'] ?? [])),
                        'average_execution_label' => $this->formatDuration($average($row['execution_seconds'] ?? [])),
                    ];
                }),
            ];
        });
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Sem dados';
        }

        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours.' h'.($remainingMinutes > 0 ? ' '.$remainingMinutes.' min' : '');
    }
}
