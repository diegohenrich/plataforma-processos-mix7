@extends('layouts.app')

@section('title', 'Nova demanda · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <a class="back-link" href="{{ route('demands.index') }}">← Voltar às demandas</a>
            <p class="eyebrow">Novo trabalho</p><h1 class="heading">Criar demanda</h1><p class="subheading">Registre o pedido e quem vai começar a trabalhar nele.</p>
            @include('partials.flash')
            @if ($professionals->isEmpty())
                <div class="notice notice-info">Ainda não há profissionais ativos cadastrados para atribuir tarefas. @can('viewAny', App\Models\User::class)Cadastre a equipe antes de criar uma demanda. <a class="secondary-link" href="{{ route('team.index') }}">Abrir equipe</a>@else Peça ao dono da agência para cadastrar profissionais antes de criar uma demanda.@endcan</div>
            @else
                <form method="post" action="{{ route('demands.store') }}" class="form-card" novalidate>
                    @csrf
                    <label class="field">Nome da demanda<input name="title" value="{{ old('title') }}" maxlength="180" required placeholder="Ex.: Site institucional da empresa" autocomplete="off">@error('title')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Briefing<textarea name="brief" rows="5" maxlength="12000" required placeholder="O que precisa ser feito? Inclua objetivo, público e materiais já recebidos.">{{ old('brief') }}</textarea>@error('brief')<span class="error">{{ $message }}</span>@enderror</label>
                    <div class="form-section-heading"><div><h2>Primeiras tarefas</h2><p>Atribua cada tarefa a uma pessoa da equipe.</p></div><button class="secondary-button" type="button" id="add-task">+ Adicionar tarefa</button></div>
                    <div id="task-list" class="task-form-list">
                        @foreach (old('tasks', [['title' => '', 'assignee_id' => '', 'estimate_minutes' => '']]) as $index => $task)
                            <div class="task-form-row">
                                <label class="field">Tarefa<input name="tasks[{{ $index }}][title]" value="{{ $task['title'] ?? '' }}" maxlength="180" required placeholder="Ex.: Reunir referências para o layout">@error("tasks.$index.title")<span class="error">{{ $message }}</span>@enderror</label>
                                <label class="field">Responsável<select name="tasks[{{ $index }}][assignee_id]" required><option value="">Selecione</option>@foreach ($professionals as $professional)<option value="{{ $professional->id }}" @selected(($task['assignee_id'] ?? '') == $professional->id)>{{ $professional->name }}</option>@endforeach</select>@error("tasks.$index.assignee_id")<span class="error">{{ $message }}</span>@enderror</label>
                                <label class="field estimate-field">Estimativa (min)<input name="tasks[{{ $index }}][estimate_minutes]" type="number" min="1" max="100000" value="{{ $task['estimate_minutes'] ?? '' }}" placeholder="Opcional"></label>
                                @if ($index > 0)<button class="remove-task" type="button" aria-label="Remover tarefa">Remover</button>@endif
                            </div>
                        @endforeach
                    </div>
                    <div class="form-actions"><a class="secondary-link" href="{{ route('demands.index') }}">Cancelar</a><button class="primary-button" type="submit">Criar demanda</button></div>
                </form>
            @endif
        </div>
    </main>
</div>
@if ($professionals->isNotEmpty())
<template id="task-template"><div class="task-form-row"><label class="field">Tarefa<input data-name="title" maxlength="180" required placeholder="Descreva uma tarefa"></label><label class="field">Responsável<select data-name="assignee_id" required><option value="">Selecione</option>@foreach ($professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach</select></label><label class="field estimate-field">Estimativa (min)<input data-name="estimate_minutes" type="number" min="1" max="100000" placeholder="Opcional"></label><button class="remove-task" type="button" aria-label="Remover tarefa">Remover</button></div></template>
<script>
(() => {
    const list = document.getElementById('task-list');
    const template = document.getElementById('task-template');
    let nextIndex = list.children.length;
    document.getElementById('add-task')?.addEventListener('click', () => {
        if (list.children.length >= 20) return;
        const row = template.content.firstElementChild.cloneNode(true);
        const index = nextIndex++;
        row.querySelectorAll('[data-name]').forEach((field) => field.name = `tasks[${index}][${field.dataset.name}]`);
        row.querySelector('.remove-task').addEventListener('click', () => row.remove());
        list.append(row);
    });
})();
</script>
@endif
@endsection
