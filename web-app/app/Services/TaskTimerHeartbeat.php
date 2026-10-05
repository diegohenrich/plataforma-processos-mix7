<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\DemandEvent;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TaskTimerHeartbeat
{
    public const INTERVAL_SECONDS = 20;

    public const TIMEOUT_SECONDS = 180;

    public function touch(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return false;
            }

            $now = CarbonImmutable::now();
            $lastHeartbeat = $entry->last_heartbeat_at;

            if ($lastHeartbeat && $lastHeartbeat->lessThan($now->subSeconds(self::TIMEOUT_SECONDS))) {
                $this->closeStaleEntry($entry, $lastHeartbeat);

                return false;
            }

            $entry->update(['last_heartbeat_at' => $now]);

            return true;
        });
    }

    public function closeStaleFor(User $user): void
    {
        $cutoff = CarbonImmutable::now()->subSeconds(self::TIMEOUT_SECONDS);
        DB::transaction(function () use ($user, $cutoff): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->whereNotNull('last_heartbeat_at')
                ->where('last_heartbeat_at', '<', $cutoff)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return;
            }

            $this->closeStaleEntry($entry, $entry->last_heartbeat_at);
        });
    }

    public function closeAllStale(): void
    {
        $cutoff = CarbonImmutable::now()->subSeconds(self::TIMEOUT_SECONDS);
        $ids = TaskTimeEntry::query()
            ->whereNull('ended_at')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $cutoff): void {
                $entry = TaskTimeEntry::query()->whereKey($id)->lockForUpdate()->first();
                if (! $entry || $entry->ended_at || ! $entry->last_heartbeat_at || $entry->last_heartbeat_at->greaterThanOrEqualTo($cutoff)) {
                    return;
                }

                $this->closeStaleEntry($entry, $entry->last_heartbeat_at);
            });
        }
    }

    private function closeStaleEntry(TaskTimeEntry $entry, CarbonImmutable $endedAt): void
    {
        $entry->update(['ended_at' => $endedAt]);
        $task = $entry->task()->lockForUpdate()->firstOrFail();

        if ($task->status === TaskStatus::InProgress) {
            $task->update(['status' => TaskStatus::Paused]);
        }

        DemandEvent::create([
            'organization_id' => $entry->organization_id,
            'demand_id' => $task->demand_id,
            'task_id' => $task->id,
            'actor_id' => null,
            'event_type' => 'timer_auto_paused',
            'summary' => 'O sistema encerrou o cronômetro após perder o sinal da sessão; o tempo foi registrado até o último sinal recebido.',
        ]);
    }
}
