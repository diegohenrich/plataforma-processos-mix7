<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DemandTask;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamCapacityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->organization_id !== null && $user->role !== UserRole::Client, 403);
        [$week, $weekStart, $weekEnd] = $this->week($request->query('week'));
        $canManage = in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true);
        $professionals = $canManage
            ? User::query()->where('organization_id', $user->organization_id)->where('role', UserRole::Professional->value)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        if (! $canManage) {
            $professional = $user;
        } elseif ($professionals->isEmpty()) {
            $professional = null;
        } else {
            $requestedId = $request->query('professional_id');
            $professional = $requestedId
                ? $professionals->firstWhere('id', (int) $requestedId)
                : $professionals->first();
            abort_unless($professional, 404);
        }

        $snapshot = $professional
            ? $this->latestSnapshot($user->organization_id, $professional->id, $weekStart)
            : null;
        $tasks = $professional
            ? DemandTask::query()
                ->with('demand:id,title,status')
                ->where('organization_id', $user->organization_id)
                ->where('assigned_to', $professional->id)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereHas('demand', fn ($query) => $query->where('status', '!=', DemandStatus::Completed->value))
                ->get(['id', 'demand_id', 'title', 'status', 'estimate_minutes', 'planned_due_on'])
            : collect();
        $datedTasks = $tasks->filter(fn ($task) => $task->planned_due_on && $task->planned_due_on->betweenIncluded($weekStart, $weekEnd))->values();
        $undatedTasks = $tasks->whereNull('planned_due_on')->values();
        $missingEstimateTasks = $datedTasks->filter(fn ($task) => ! $task->estimate_minutes || $task->estimate_minutes < 1)->values();
        $plannedMinutes = (int) $datedTasks->sum(fn ($task) => max(0, (int) $task->estimate_minutes));
        $absences = $snapshot?->absences ?? [];
        $absenceMinutes = (int) collect($absences)->sum('minutes');
        $history = $professional
            ? TeamCapacitySnapshot::query()
                ->where('organization_id', $user->organization_id)
                ->where('professional_id', $professional->id)
                ->whereDate('week_start', $weekStart->toDateString())
                ->with('recorder:id,name')
                ->latest('id')->limit(10)->get()
            : collect();

        return view('team.capacity', [
            'week' => $week,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'canManage' => $canManage,
            'professionals' => $professionals,
            'professional' => $professional,
            'snapshot' => $snapshot,
            'tasks' => $datedTasks,
            'undatedTasks' => $undatedTasks,
            'missingEstimateTasks' => $missingEstimateTasks,
            'scheduledMinutes' => $snapshot?->scheduled_minutes,
            'absenceMinutes' => $absenceMinutes,
            'availableMinutes' => $snapshot?->scheduled_minutes === null ? null : max(0, $snapshot->scheduled_minutes - $absenceMinutes),
            'plannedMinutes' => $plannedMinutes,
            'history' => $history,
        ]);
    }

    public function setScheduledHours(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'professional_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $request->user()->organization_id)
                ->where('role', UserRole::Professional->value)->where('is_active', true))],
            'scheduled_hours' => ['required', 'numeric', 'min:0', 'max:168', 'multiple_of:0.25'],
        ]);
        [$week, $weekStart] = $this->week($data['week']);
        $professional = User::query()->whereKey($data['professional_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $current = $this->latestSnapshot($request->user()->organization_id, $professional->id, $weekStart);
        $minutes = (int) round((float) $data['scheduled_hours'] * 60);
        $absences = $current?->absences ?? [];
        if (collect($absences)->sum('minutes') > $minutes) {
            throw ValidationException::withMessages(['scheduled_hours' => 'A jornada semanal não pode ser menor que as ausências já registradas.']);
        }

        $this->recordSnapshot($request, $professional, $weekStart, $minutes, $absences, 'availability_set');

        return to_route('team.capacity', ['week' => $week, 'professional_id' => $professional->id])
            ->with('success', 'Disponibilidade semanal registrada. Esta é uma previsão manual, sem jornada padrão.');
    }

    public function addAbsence(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        [$week, $weekStart, $weekEnd] = $this->week($request->input('week'));
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'professional_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $request->user()->organization_id)
                ->where('role', UserRole::Professional->value)->where('is_active', true))],
            'work_date' => ['required', 'date_format:Y-m-d', function (string $attribute, string $value, \Closure $fail) use ($weekStart, $weekEnd): void {
                $date = CarbonImmutable::parse($value);
                if ($date->lt($weekStart) || $date->gt($weekEnd)) {
                    $fail('A ausência precisa estar dentro da semana selecionada.');
                }
            }],
            'absence_hours' => ['required', 'numeric', 'min:0.25', 'max:168', 'multiple_of:0.25'],
        ]);
        $professional = User::query()->whereKey($data['professional_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $current = $this->latestSnapshot($request->user()->organization_id, $professional->id, $weekStart);
        if (! $current || $current->scheduled_minutes === null) {
            throw ValidationException::withMessages(['work_date' => 'Informe primeiro as horas disponíveis para essa semana.']);
        }
        $absences = $current->absences ?? [];
        $minutes = (int) round((float) $data['absence_hours'] * 60);
        if (collect($absences)->sum('minutes') + $minutes > $current->scheduled_minutes) {
            throw ValidationException::withMessages(['absence_hours' => 'As ausências não podem superar as horas disponíveis na semana.']);
        }
        $absences[] = ['id' => bin2hex(random_bytes(8)), 'date' => $data['work_date'], 'minutes' => $minutes];

        $this->recordSnapshot($request, $professional, $weekStart, $current->scheduled_minutes, $absences, 'absence_added');

        return to_route('team.capacity', ['week' => $week, 'professional_id' => $professional->id])
            ->with('success', 'Ausência registrada na previsão da semana.');
    }

    public function removeAbsence(Request $request, string $absence): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'professional_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $request->user()->organization_id)
                ->where('role', UserRole::Professional->value)->where('is_active', true))],
        ]);
        [$week, $weekStart] = $this->week($data['week']);
        $professional = User::query()->whereKey($data['professional_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $current = $this->latestSnapshot($request->user()->organization_id, $professional->id, $weekStart);
        abort_unless($current, 404);
        $absences = $current->absences ?? [];
        $remaining = collect($absences)->reject(fn (array $entry) => ($entry['id'] ?? null) === $absence)->values()->all();
        abort_unless(count($remaining) < count($absences), 404);

        $this->recordSnapshot($request, $professional, $weekStart, $current->scheduled_minutes, $remaining, 'absence_removed');

        return to_route('team.capacity', ['week' => $week, 'professional_id' => $professional->id])
            ->with('success', 'Ausência removida da previsão; a alteração anterior continua no histórico.');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->is_active && $request->user()->organization_id !== null
            && in_array($request->user()->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
    }

    /** @return array{string, CarbonImmutable, CarbonImmutable} */
    private function week(?string $value): array
    {
        $week = $value ?: CarbonImmutable::now()->format('o-\WW');
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches)) {
            abort(422, 'Selecione uma semana ISO válida.');
        }

        try {
            $start = CarbonImmutable::now()->setISODate((int) $matches[1], (int) $matches[2], 1)->startOfDay();
        } catch (\Throwable) {
            abort(422, 'Selecione uma semana ISO válida.');
        }
        if ($start->format('o-\WW') !== $week) {
            abort(422, 'Selecione uma semana ISO válida.');
        }

        return [$week, $start, $start->addDays(6)->endOfDay()];
    }

    private function latestSnapshot(int $organizationId, int $professionalId, CarbonImmutable $weekStart): ?TeamCapacitySnapshot
    {
        return TeamCapacitySnapshot::query()
            ->where('organization_id', $organizationId)
            ->where('professional_id', $professionalId)
            ->whereDate('week_start', $weekStart->toDateString())
            ->latest('id')->first();
    }

    /** @param list<array{id: string, date: string, minutes: int}> $absences */
    private function recordSnapshot(
        Request $request,
        User $professional,
        CarbonImmutable $weekStart,
        ?int $scheduledMinutes,
        array $absences,
        string $changeType,
    ): void {
        DB::transaction(fn () => TeamCapacitySnapshot::create([
            'organization_id' => $request->user()->organization_id,
            'professional_id' => $professional->id,
            'recorded_by' => $request->user()->id,
            'week_start' => $weekStart->toDateString(),
            'scheduled_minutes' => $scheduledMinutes,
            'absences' => $absences,
            'change_type' => $changeType,
        ]));
    }
}
