@extends('layouts.app')

@section('title', 'Avaliações · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'performance-reviews'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <p class="eyebrow">Desenvolvimento da equipe</p>
            <h1 class="heading">Avaliações</h1>
            <p class="subheading">Registre observações sobre prazo e qualidade com exemplos concretos. A pessoa avaliada pode responder e complementar o histórico.</p>

            @if (session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="notice notice-error" role="alert"><strong>Revise os campos:</strong> {{ $errors->first() }}</div>@endif

            @if ($management)
                <section class="card performance-form-card">
                    <h2>Registrar avaliação de tarefa concluída</h2>
                    <p>Uma avaliação por pessoa avaliadora em cada tarefa. A direção tem peso 2 e a gerência peso 1, conforme indicado nos áudios; os pesos ficam registrados, sem cálculo de nota.</p>
                    @if ($completedTasks->isEmpty())
                        <div class="empty-state"><h3>Nenhuma tarefa disponível para nova avaliação</h3><p>Conclua uma tarefa atribuída a um profissional. Tarefas que você já avaliou não aparecem novamente neste seletor.</p></div>
                    @else
                        <form class="performance-form" method="post" action="{{ route('performance-reviews.store') }}">
                            @csrf
                            <label class="field"><span>Tarefa e profissional</span><select name="task_id" required><option value="">Escolha uma tarefa</option>@foreach ($completedTasks as $task)<option value="{{ $task->id }}" @selected(old('task_id') == $task->id)>{{ $task->title }} · {{ $task->assignee->name }} · {{ $task->demand->title }}</option>@endforeach</select></label>
                            <label class="field"><span>Prazo</span><textarea name="deadline_assessment" rows="3" minlength="10" maxlength="5000" required placeholder="Descreva o que foi combinado e como o prazo foi cumprido.">{{ old('deadline_assessment') }}</textarea></label>
                            <label class="field"><span>Qualidade</span><textarea name="quality_assessment" rows="3" minlength="10" maxlength="5000" required placeholder="Registre o resultado observado e os critérios usados.">{{ old('quality_assessment') }}</textarea></label>
                            <label class="field"><span>Evidências ou exemplos (opcional)</span><textarea name="evidence" rows="2" maxlength="5000" placeholder="Inclua referências verificáveis ao trabalho.">{{ old('evidence') }}</textarea></label>
                            <label class="field"><span>Bloqueios ou mudanças externas (opcional)</span><textarea name="external_factors" rows="2" maxlength="5000" placeholder="Registre fatos que afetaram prazo ou escopo.">{{ old('external_factors') }}</textarea></label>
                            <button class="primary-button" type="submit">Registrar avaliação</button>
                        </form>
                    @endif
                </section>
            @else
                <div class="notice"><strong>Seu espaço de avaliação.</strong> Aqui ficam as observações registradas sobre suas tarefas concluídas. Você pode responder; cada resposta fica no histórico.</div>
            @endif

            <section class="performance-list">
                <div class="section-heading"><div><h2>{{ $management ? 'Histórico da equipe' : 'Seu histórico' }}</h2><p>{{ $management ? 'Registros da sua organização, com autoria e respostas.' : 'Avaliações das suas tarefas, sem acesso às avaliações de outras pessoas.' }}</p></div></div>
                <form class="review-filters" method="get" action="{{ route('performance-reviews.index') }}">
                    <label class="field"><span>De</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
                    <label class="field"><span>Até</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
                    <button class="secondary-button" type="submit">Filtrar período</button>
                    @if (($filters['from'] ?? null) || ($filters['to'] ?? null))<a class="review-filter-clear" href="{{ route('performance-reviews.index') }}">Limpar</a>@endif
                </form>
                @forelse ($reviews as $review)
                    <article class="card performance-review-card">
                        <div class="performance-review-heading"><div><span class="pill">{{ $review->task->status->label() }}</span><h3>{{ $review->task->title }}</h3><p>{{ $review->task->demand->title }} · Profissional: {{ $review->professional->name }}</p></div><div class="performance-review-meta"><strong>{{ $review->reviewer->name }}</strong><span>{{ $review->reviewer_role === App\Enums\UserRole::AgencyOwner->value ? 'Direção · peso 2' : 'Gerência · peso 1' }}</span><time datetime="{{ $review->created_at->toISOString() }}">{{ $review->created_at->format('d/m/Y H:i') }}</time></div></div>
                        <div class="performance-review-fields"><div><h4>Prazo</h4><p>{{ $review->deadline_assessment }}</p></div><div><h4>Qualidade</h4><p>{{ $review->quality_assessment }}</p></div></div>
                        @if ($review->evidence)<div class="performance-review-note"><strong>Evidências</strong><p>{{ $review->evidence }}</p></div>@endif
                        @if ($review->external_factors)<div class="performance-review-note"><strong>Bloqueios ou mudanças externas</strong><p>{{ $review->external_factors }}</p></div>@endif
                        <div class="performance-responses"><h4>Respostas</h4>@forelse ($review->responses as $response)<article><strong>{{ $response->user->name }}</strong><time datetime="{{ $response->created_at->toISOString() }}">{{ $response->created_at->format('d/m/Y H:i') }}</time><p>{{ $response->response }}</p></article>@empty<p>Nenhuma resposta registrada ainda.</p>@endforelse</div>
                        @if (! $management && $review->professional_id === auth()->id())<form class="performance-response-form" method="post" action="{{ route('performance-reviews.respond', $review) }}">@csrf<label class="field"><span>Acrescente sua resposta</span><textarea name="response" rows="3" minlength="3" maxlength="5000" required placeholder="Registre seu contexto ou comentário sobre esta avaliação.">{{ old('response') }}</textarea></label><button class="secondary-button" type="submit">Adicionar ao histórico</button></form>@endif
                    </article>
                @empty
                    <div class="empty-state"><h3>Nenhuma avaliação registrada</h3><p>{{ $management ? 'As avaliações aparecerão aqui depois de registradas.' : 'Quando a gerência registrar uma avaliação de uma tarefa sua, ela aparecerá aqui.' }}</p></div>
                @endforelse
                <div class="pagination-wrap">{{ $reviews->links() }}</div>
            </section>
            <p class="footnote">Este recurso registra avaliações humanas, evidências e respostas. Não calcula nota, ranking ou consequência profissional: escala, fórmula, períodos, contestação e uso dos resultados ainda precisam ser definidos pela Mix7.</p>
        </div>
    </main>
</div>
@endsection
