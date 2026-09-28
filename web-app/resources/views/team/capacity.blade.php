@extends('layouts.app')

@section('title', 'Disponibilidade · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'team'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <p class="eyebrow">{{ $canManage ? 'Planejamento da equipe' : 'Meu planejamento' }}</p>
            <h1 class="heading">Disponibilidade e carga prevista</h1>
            <p class="subheading">Compare as horas informadas com as estimativas das tarefas que vencem na semana. O sistema não distribui horas nem muda prazos ou responsáveis.</p>
            @include('partials.team-tabs', ['teamView' => 'capacity'])

            @if (session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif

            <section class="capacity-notice">
                <strong>Previsão manual, ainda não é regra oficial da Mix7</strong>
                <span>Não há jornada padrão. Só as horas inseridas para esta pessoa e semana entram no cálculo. Ausências são descontadas; tarefas entram pela estimativa integral quando o prazo cai nesta semana.</span>
            </section>

            <form method="get" action="{{ route('team.capacity') }}" class="capacity-filter">
                <label class="field" for="capacity-week">Semana</label>
                <input id="capacity-week" type="week" name="week" value="{{ $week }}" required>
                @if ($canManage)
                    <label class="field" for="capacity-professional">Profissional</label>
                    <select id="capacity-professional" name="professional_id" required>
                        @foreach ($professionals as $member)<option value="{{ $member->id }}" @selected($professional?->id === $member->id)>{{ $member->name }}</option>@endforeach
                    </select>
                    <button class="secondary-button" type="submit">Ver semana</button>
                @else
                    <button class="secondary-button" type="submit">Ver semana</button>
                @endif
            </form>

            @if (! $professional)
                <div class="empty-state"><h2>Nenhum profissional ativo</h2><p>Cadastre uma pessoa ativa na equipe para planejar a disponibilidade.</p></div>
            @else
                <section class="capacity-person">
                    <div><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($professional->name, 0, 1)) }}</span><strong>{{ $canManage ? $professional->name : 'Sua semana' }}</strong></div>
                    <span>{{ $weekStart->format('d/m/Y') }} a {{ $weekEnd->format('d/m/Y') }}</span>
                </section>

                <div class="capacity-metrics" aria-label="Resumo semanal">
                    <article><span>Horas informadas</span><strong>{{ $scheduledMinutes === null ? 'Não informadas' : number_format($scheduledMinutes / 60, 2, ',', '.').' h' }}</strong></article>
                    <article><span>Ausências</span><strong>{{ number_format($absenceMinutes / 60, 2, ',', '.') }} h</strong></article>
                    <article><span>Disponíveis</span><strong>{{ $availableMinutes === null ? '—' : number_format($availableMinutes / 60, 2, ',', '.').' h' }}</strong></article>
                    <article><span>Estimativas com prazo nesta semana</span><strong>{{ number_format($plannedMinutes / 60, 2, ',', '.') }} h</strong></article>
                    <article class="{{ $availableMinutes !== null && $plannedMinutes > $availableMinutes ? 'capacity-over' : '' }}"><span>Saldo previsto</span><strong>{{ $availableMinutes === null ? '—' : number_format(($availableMinutes - $plannedMinutes) / 60, 2, ',', '.').' h' }}</strong></article>
                </div>

                @if ($canManage)
                    <section class="card capacity-form-card">
                        <div class="section-heading"><div><h2>Horas disponíveis nesta semana</h2><p>Informe o total manualmente. Nenhum horário ou jornada padrão é presumido.</p></div></div>
                        <form method="post" action="{{ route('team.capacity.schedule') }}" class="capacity-form">@csrf
                            <input type="hidden" name="week" value="{{ $week }}"><input type="hidden" name="professional_id" value="{{ $professional->id }}">
                            <label class="field" for="scheduled-hours">Horas disponíveis<input id="scheduled-hours" type="number" name="scheduled_hours" min="0" max="168" step="0.25" value="{{ old('scheduled_hours', $scheduledMinutes === null ? '' : number_format($scheduledMinutes / 60, 2, '.', '')) }}" required></label>
                            <button class="primary-button" type="submit">Salvar disponibilidade</button>
                        </form>
                    </section>
                    <section class="card capacity-form-card">
                        <div class="section-heading"><div><h2>Registrar ausência</h2><p>Informe o dia e as horas fora. Não é necessário escrever um motivo.</p></div></div>
                        <form method="post" action="{{ route('team.capacity.absences.store') }}" class="capacity-form">@csrf
                            <input type="hidden" name="week" value="{{ $week }}"><input type="hidden" name="professional_id" value="{{ $professional->id }}">
                            <label class="field" for="absence-date">Dia<input id="absence-date" type="date" name="work_date" min="{{ $weekStart->format('Y-m-d') }}" max="{{ $weekEnd->format('Y-m-d') }}" required></label>
                            <label class="field" for="absence-hours">Horas fora<input id="absence-hours" type="number" name="absence_hours" min="0.25" max="168" step="0.25" required></label>
                            <button class="secondary-button" type="submit" @disabled($scheduledMinutes === null)>Adicionar ausência</button>
                        </form>
                        @if ($scheduledMinutes === null)<p class="field-help">Informe primeiro as horas disponíveis da semana.</p>@endif
                        @if ($snapshot && count($snapshot->absences ?? []))
                            <ul class="capacity-absence-list">
                                @foreach ($snapshot->absences as $absence)
                                    <li><span>{{ \Carbon\CarbonImmutable::parse($absence['date'])->format('d/m/Y') }} · {{ number_format($absence['minutes'] / 60, 2, ',', '.') }} h fora</span><form method="post" action="{{ route('team.capacity.absences.destroy', $absence['id']) }}">@csrf @method('DELETE')<input type="hidden" name="week" value="{{ $week }}"><input type="hidden" name="professional_id" value="{{ $professional->id }}"><button class="text-button" type="submit">Remover</button></form></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="empty-inline">Nenhuma ausência registrada nesta semana.</p>
                        @endif
                    </section>
                @endif

                <section class="capacity-task-section">
                    <div class="section-heading"><div><h2>Tarefas com prazo nesta semana</h2><p>A estimativa inteira entra na semana do prazo. Isso é uma aproximação e não reparte tarefas entre dias.</p></div></div>
                    @forelse ($tasks as $task)
                        <article class="capacity-task"><div><a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong></a><span>{{ $task->demand->title }} · {{ $task->status->label() }} · prazo {{ $task->planned_due_on->format('d/m/Y') }}</span></div><strong>{{ $task->estimate_minutes ? number_format($task->estimate_minutes / 60, 2, ',', '.').' h' : 'Sem estimativa' }}</strong></article>
                    @empty
                        <p class="empty-inline">Nenhuma tarefa aberta tem prazo nesta semana.</p>
                    @endforelse
                    @if ($missingEstimateTasks->isNotEmpty())<p class="field-help">{{ $missingEstimateTasks->count() }} tarefa(s) com prazo não têm estimativa e não entram na soma.</p>@endif
                </section>

                <section class="capacity-task-section">
                    <div class="section-heading"><div><h2>Tarefas sem prazo</h2><p>Elas não entram no total semanal.</p></div></div>
                    @forelse ($undatedTasks as $task)
                        <article class="capacity-task"><div><a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong></a><span>{{ $task->demand->title }} · {{ $task->status->label() }}</span></div><strong>{{ $task->estimate_minutes ? number_format($task->estimate_minutes / 60, 2, ',', '.').' h' : 'Sem estimativa' }}</strong></article>
                    @empty
                        <p class="empty-inline">Todas as tarefas abertas têm prazo.</p>
                    @endforelse
                </section>

                @if ($canManage && $history->isNotEmpty())
                    <details class="capacity-history">
                        <summary>Ver histórico de alterações da previsão</summary>
                        <ul>@foreach ($history as $change)<li><strong>{{ match ($change->change_type) {'availability_set' => 'Disponibilidade alterada', 'absence_added' => 'Ausência adicionada', 'absence_removed' => 'Ausência removida', default => 'Previsão alterada'} }}</strong><span>{{ $change->recorder->name }} · {{ $change->created_at->format('d/m/Y H:i') }}</span></li>@endforeach</ul>
                    </details>
                @endif
            @endif
        </div>
    </main>
</div>
<style>
.capacity-notice{display:flex;flex-direction:column;gap:5px;margin:22px 0;padding:14px 16px;border:1px solid #d9e8ec;border-radius:13px;background:#f4fafc;color:#52666e;font-size:12px;line-height:1.55}.capacity-notice strong{color:#204b61}.capacity-filter{display:flex;align-items:end;gap:12px;flex-wrap:wrap;padding:16px;background:#fff;border:1px solid #e3e9e8;border-radius:15px}.capacity-filter .field{margin:0}.capacity-filter input,.capacity-filter select,.capacity-form input{min-height:40px;border:1px solid #d6e0e1;border-radius:10px;padding:8px 10px;font:inherit;color:#202e35;background:#fff}.capacity-person{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:22px 0 12px;color:#718087;font-size:12px}.capacity-person>div{display:flex;align-items:center;gap:10px;color:#204b61}.capacity-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.capacity-metrics article{display:flex;flex-direction:column;justify-content:space-between;gap:12px;min-height:95px;padding:15px;background:#fff;border:1px solid #e3e9e8;border-radius:14px}.capacity-metrics span,.capacity-task span,.capacity-history li span{color:#718087;font-size:11px;line-height:1.5}.capacity-metrics strong{color:#204b61;font-size:18px}.capacity-metrics .capacity-over{border-color:#efc7bf;background:#fff9f7}.capacity-metrics .capacity-over strong{color:#a74336}.capacity-form-card{margin-top:16px;padding:18px}.capacity-form{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.capacity-form .field{margin:0}.capacity-form input{display:block;margin-top:7px;min-width:170px}.capacity-absence-list,.capacity-history ul{list-style:none;padding:0;margin:14px 0 0}.capacity-absence-list li,.capacity-history li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-top:1px solid #edf0ef;color:#52666e;font-size:12px}.capacity-absence-list li form{margin:0}.text-button{border:0;background:none;color:#39758b;font:inherit;font-size:12px;cursor:pointer}.capacity-task-section{margin-top:26px}.capacity-task{display:flex;justify-content:space-between;gap:12px;padding:13px 15px;border:1px solid #e3e9e8;border-radius:12px;background:#fff;margin-top:9px}.capacity-task>div{display:flex;flex-direction:column;gap:4px}.capacity-task a{color:#204b61;text-decoration:none}.capacity-task a:hover{text-decoration:underline}.capacity-task>strong{white-space:nowrap;color:#204b61;font-size:12px}.capacity-history{margin-top:24px;padding:14px 16px;background:#fff;border:1px solid #e3e9e8;border-radius:13px}.capacity-history summary{color:#204b61;font-weight:700;font-size:13px;cursor:pointer}.capacity-history li{align-items:flex-start;flex-direction:column}@media(max-width:1050px){.capacity-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.capacity-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.capacity-filter,.capacity-form{align-items:stretch;flex-direction:column}.capacity-filter>* ,.capacity-form>*{width:100%}.capacity-filter .field,.capacity-form .field{display:block}.capacity-form input{width:100%}.capacity-person{align-items:flex-start;flex-direction:column}.capacity-task{align-items:flex-start;flex-direction:column}.capacity-task>strong{align-self:flex-end}}
</style>
@endsection
