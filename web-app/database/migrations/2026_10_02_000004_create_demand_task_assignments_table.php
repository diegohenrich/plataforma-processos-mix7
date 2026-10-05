<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_task_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_task_id')->constrained('demand_tasks')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'professional_id', 'assigned_at'], 'task_assignments_professional_date_idx');
            $table->index(['demand_task_id', 'released_at'], 'task_assignments_task_current_idx');
        });

        $latestAssignmentIds = DB::table('demand_events')
            ->selectRaw('task_id, MAX(id) as event_id')
            ->whereIn('event_type', ['task_assigned', 'task_reassigned'])
            ->groupBy('task_id');
        $firstStartForCurrentAssignment = DB::table('task_time_entries as time_entries')
            ->selectRaw('MIN(time_entries.started_at)')
            ->whereColumn('time_entries.task_id', 'tasks.id')
            ->whereColumn('time_entries.user_id', 'tasks.assigned_to')
            ->whereRaw('time_entries.started_at >= COALESCE(latest_assignment.created_at, tasks.created_at)');

        DB::table('demand_tasks as tasks')
            ->leftJoinSub($latestAssignmentIds, 'latest_assignment_ids', function ($join): void {
                $join->on('latest_assignment_ids.task_id', '=', 'tasks.id');
            })
            ->leftJoin('demand_events as latest_assignment', 'latest_assignment.id', '=', 'latest_assignment_ids.event_id')
            ->select([
                'tasks.id',
                'tasks.organization_id',
                'tasks.created_by',
                'tasks.assigned_to',
                'tasks.created_at',
                'tasks.completed_at',
            ])
            ->selectRaw('COALESCE(latest_assignment.actor_id, tasks.created_by) as assigned_by')
            ->selectRaw('COALESCE(latest_assignment.created_at, tasks.created_at) as assigned_at')
            ->selectSub($firstStartForCurrentAssignment, 'accepted_at')
            ->orderBy('tasks.id')
            ->chunkById(500, function ($tasks): void {
                $rows = [];
                foreach ($tasks as $task) {
                    $rows[] = [
                        'organization_id' => $task->organization_id,
                        'demand_task_id' => $task->id,
                        'professional_id' => $task->assigned_to,
                        'assigned_by' => $task->assigned_by,
                        'assigned_at' => $task->assigned_at,
                        'accepted_at' => $task->accepted_at,
                        'completed_at' => $task->completed_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('demand_task_assignments')->insert($rows);
                }
            }, 'tasks.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_task_assignments');
    }
};
