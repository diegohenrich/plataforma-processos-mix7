<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DemandEvent;
use App\Models\TaskTimeEntry;
use App\Models\TeamInvitation;
use App\Models\TeamMemberEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $professionals = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Professional->value)
            ->orderBy('name')
            ->paginate(20);

        $clients = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Client->value)
            ->orderBy('name')
            ->paginate(20, ['*'], 'clients_page');

        $invitations = TeamInvitation::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();
        $accessEvents = TeamMemberEvent::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['member:id,name', 'actor:id,name'])
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('team.index', compact('professionals', 'clients', 'invitations', 'accessEvents'));
    }

    public function updateAccess(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        $this->authorize('updateAccess', $member);

        $nextActive = ! $member->is_active;
        DB::transaction(function () use ($member, $request, $nextActive): void {
            $lockedMember = User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ($lockedMember->is_active === $nextActive) {
                return;
            }

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
                            'actor_id' => $request->user()->id,
                            'event_type' => 'task_status_changed',
                            'summary' => $request->user()->name.' pausou "'.$entry->task->title.'" ao desativar o acesso de '.$lockedMember->name,
                            'from_status' => TaskStatus::InProgress->value,
                            'to_status' => TaskStatus::Paused->value,
                        ]);
                    }
                    DemandEvent::create([
                        'organization_id' => $entry->organization_id,
                        'demand_id' => $entry->task->demand_id,
                        'task_id' => $entry->task_id,
                        'actor_id' => $request->user()->id,
                        'event_type' => 'timer_paused',
                        'summary' => $request->user()->name.' encerrou o cronômetro de '.$lockedMember->name.' em "'.$entry->task->title.'" ao desativar o acesso',
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
                'actor_id' => $request->user()->id,
                'event_type' => $eventType,
                'created_at' => CarbonImmutable::now(),
            ]);
        });

        return back()->with('success', $nextActive
            ? 'Acesso restaurado. A pessoa poderá entrar novamente.'
            : 'Acesso desativado. Sessões e tokens foram encerrados; tarefas abertas continuam atribuídas à pessoa.');
    }
}
