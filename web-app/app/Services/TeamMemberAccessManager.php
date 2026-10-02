<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\DemandEvent;
use App\Models\TaskTimeEntry;
use App\Models\TeamMemberEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TeamMemberAccessManager
{
    public function toggle(User $member, User $actor): User
    {
        return DB::transaction(function () use ($member, $actor): User {
            $lockedMember = User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $nextActive = ! $lockedMember->is_active;
            $lockedMember->update(['is_active' => $nextActive]);
            $eventType = $nextActive ? 'access_restored' : 'access_revoked';

            if (! $nextActive) {
                $now = CarbonImmutable::now();
                $activeEntries = TaskTimeEntry::query()
                    ->where('user_id', $lockedMember->id)
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->with('task')
                    ->get();

                foreach ($activeEntries as $entry) {
                    $entry->update(['ended_at' => $now]);
                    if ($entry->task->status === TaskStatus::InProgress) {
                        $entry->task->update(['status' => TaskStatus::Paused]);
                        DemandEvent::create([
                            'organization_id' => $entry->organization_id,
                            'demand_id' => $entry->task->demand_id,
                            'task_id' => $entry->task_id,
                            'actor_id' => $actor->id,
                            'event_type' => 'task_status_changed',
                            'summary' => $actor->name.' pausou "'.$entry->task->title.'" ao desativar o acesso de '.$lockedMember->name,
                            'from_status' => TaskStatus::InProgress->value,
                            'to_status' => TaskStatus::Paused->value,
                        ]);
                    }
                    DemandEvent::create([
                        'organization_id' => $entry->organization_id,
                        'demand_id' => $entry->task->demand_id,
                        'task_id' => $entry->task_id,
                        'actor_id' => $actor->id,
                        'event_type' => 'timer_paused',
                        'summary' => $actor->name.' encerrou o cronômetro de '.$lockedMember->name.' em "'.$entry->task->title.'" ao desativar o acesso',
                    ]);
                }
                $lockedMember->tokens()->delete();

                if (config('session.driver') === 'database' && DB::getSchemaBuilder()->hasTable(config('session.table'))) {
                    DB::table(config('session.table'))->where('user_id', $lockedMember->id)->delete();
                }
            }

            TeamMemberEvent::create([
                'organization_id' => $lockedMember->organization_id,
                'member_id' => $lockedMember->id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'created_at' => CarbonImmutable::now(),
            ]);

            return $lockedMember->fresh();
        });
    }
}
