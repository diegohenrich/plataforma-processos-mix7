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
                <section class="ai-briefing-guide panel" data-briefing-assistant data-endpoint="{{ route('ai-briefing.suggest') }}">
                    <div class="section-heading"><div><h2>Monte o briefing com a IA</h2><p>Conte o pedido com suas palavras. O assistente pergunta o que falta e organiza um rascunho para você revisar.</p></div><span class="assistant-badge">Você decide e salva</span></div>
                    <p class="ai-briefing-privacy">A conversa e o rascunho são enviados ao provedor configurado para esta agência. Nada é salvo como demanda até você conferir e clicar em “Criar demanda”.</p>
                    @if ($aiConfigured)
                        <div class="ai-briefing-messages" data-briefing-messages role="log" aria-live="polite"><p class="ai-briefing-empty">Escolha o tipo do trabalho acima e descreva o que o cliente pediu para começar.</p></div>
                        <label class="field" for="briefing-assistant-input">O que você já sabe sobre o pedido?</label>
                        <textarea id="briefing-assistant-input" rows="3" maxlength="2000" placeholder="Ex.: preciso de um site para apresentar nossa empresa e captar contatos"></textarea>
                        <div class="ai-briefing-actions"><span class="field-help" data-briefing-status>O rascunho fica editável no formulário.</span><button class="secondary-button" type="button" data-briefing-send>Conversar com a IA</button></div>
                        <div class="ai-briefing-draft" data-briefing-draft hidden><strong>Rascunho organizado</strong><p data-briefing-followup></p><button class="secondary-button" type="button" data-briefing-apply>Usar no formulário para revisar</button></div>
                    @else
                        <div class="notice notice-info">O assistente ainda não está configurado. A direção da agência pode conectar uma API ou o Claude Code local em <a href="{{ route('ai-settings.index') }}">Configuração de IA</a>. Você também pode continuar preenchendo o formulário manualmente.</div>
                    @endif
                </section>
                <form method="post" action="{{ route('demands.store') }}" class="form-card" novalidate>
                    @csrf
                    <label class="field">Nome da demanda<input name="title" value="{{ old('title') }}" maxlength="180" required placeholder="Ex.: Site institucional da empresa" autocomplete="off">@error('title')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Tipo de aprovação<select name="module_key" id="module-key" required><option value="">Escolha o tipo</option>@foreach ($modules as $module)<option value="{{ $module['key'] }}" @selected(old('module_key') === $module['key'])>{{ $module['label'] }}</option>@endforeach</select><span class="field-help">O tipo pode pedir informações próprias da equipe. As etapas de aprovação seguem o fluxo compartilhado.</span>@error('module_key')<span class="error">{{ $message }}</span>@enderror</label>
                    <section id="module-fields" class="form-section module-custom-fields" hidden aria-live="polite"><div class="form-section-heading"><div><h2>Informações deste tipo</h2><p>Visíveis somente à equipe da agência.</p></div></div><div id="module-fields-list"></div></section>
                    <label class="field">Briefing<textarea name="brief" rows="5" maxlength="12000" required placeholder="O que precisa ser feito? Inclua objetivo, público e materiais já recebidos.">{{ old('brief') }}</textarea>@error('brief')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Como o pedido chegou? (opcional)<input name="intake_source" value="{{ old('intake_source') }}" maxlength="120" placeholder="Ex.: WhatsApp, e-mail, reunião"><span class="field-help">Registre o canal informado pela equipe. Não inclua senha ou dado de acesso.</span>@error('intake_source')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Quem preparou o briefing? (opcional)<select name="brief_author_id"><option value="">Não identificado</option>@foreach ($briefAuthors as $author)<option value="{{ $author->id }}" @selected(old('brief_author_id') == $author->id)>{{ $author->name }} · {{ $author->role->label() }}</option>@endforeach</select><span class="field-help">Escolha uma pessoa da equipe ou deixe como não identificado. A criação da demanda continua registrada separadamente.</span>@error('brief_author_id')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Cliente vinculado (opcional)<select name="client_user_id"><option value="">Sem conta de cliente vinculada</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected(old('client_user_id') == $client->id)>{{ $client->name }}</option>@endforeach</select><span class="field-help">O cliente vinculado vê apenas o nome e a etapa das próprias demandas. A aprovação do material continua pelo link privado enviado pela equipe.</span>@error('client_user_id')<span class="error">{{ $message }}</span>@enderror</label>
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
    const modules = @json($modules->values());
    const previousValues = @json(old('module_fields_data', []));
    const validationErrors = @json($errors->getMessages());
    const moduleSelect = document.getElementById('module-key');
    const fieldPanel = document.getElementById('module-fields');
    const fieldList = document.getElementById('module-fields-list');
    const renderModuleFields = () => {
        const selected = modules.find((module) => module.key === moduleSelect.value);
        const fields = selected?.fields ?? [];
        fieldList.replaceChildren();
        fieldPanel.hidden = fields.length === 0;
        for (const definition of fields) {
            const label = document.createElement('label');
            label.className = 'field';
            label.append(document.createTextNode(definition.label));
            let input;
            if (definition.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
                input.maxLength = 4000;
            } else if (definition.type === 'select') {
                input = document.createElement('select');
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Selecione';
                input.append(empty);
                for (const choice of definition.options ?? []) {
                    const option = document.createElement('option');
                    option.value = choice;
                    option.textContent = choice;
                    input.append(option);
                }
            } else {
                input = document.createElement('input');
                input.type = definition.type === 'date' ? 'date' : definition.type === 'url' ? 'url' : 'text';
                input.maxLength = definition.type === 'url' ? 2048 : 1000;
            }
            input.name = `module_fields_data[${definition.key}]`;
            input.required = Boolean(definition.required);
            input.value = previousValues[definition.key] ?? '';
            label.append(input);
            const error = document.createElement('span');
            error.className = 'error';
            error.textContent = validationErrors[`module_fields_data.${definition.key}`]?.[0] ?? validationErrors.module_fields_data?.[0] ?? '';
            if (error.textContent) label.append(error);
            fieldList.append(label);
        }
    };
    moduleSelect.addEventListener('change', renderModuleFields);
    renderModuleFields();
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
@if ($aiConfigured)
<script>
(() => {
    const root = document.querySelector('[data-briefing-assistant]');
    const form = document.querySelector('form[action="{{ route('demands.store') }}"]');
    if (!root || !form) return;
    const messages = [];
    const log = root.querySelector('[data-briefing-messages]');
    const input = root.querySelector('#briefing-assistant-input');
    const send = root.querySelector('[data-briefing-send]');
    const status = root.querySelector('[data-briefing-status]');
    const draftPanel = root.querySelector('[data-briefing-draft]');
    const followUp = root.querySelector('[data-briefing-followup]');
    const renderMessage = (role, text) => {
        log.querySelector('.ai-briefing-empty')?.remove();
        const item = document.createElement('p');
        item.className = `ai-briefing-message ai-briefing-${role}`;
        item.textContent = text;
        log.append(item);
        item.scrollIntoView({block:'nearest'});
    };
    let latestDraft = null;
    let previousModule = form.querySelector('#module-key').value;
    form.querySelector('#module-key').addEventListener('change', (event) => {
        if (previousModule && event.target.value !== previousModule) {
            messages.length = 0;
            latestDraft = null;
            log.replaceChildren();
            draftPanel.hidden = true;
            status.textContent = 'O tipo mudou. Comece uma nova conversa para este briefing.';
        }
        previousModule = event.target.value;
    });
    send.addEventListener('click', async () => {
        const text = input.value.trim();
        const moduleKey = form.querySelector('#module-key').value;
        if (!moduleKey) { status.textContent = 'Escolha primeiro o tipo do trabalho.'; form.querySelector('#module-key').focus(); return; }
        if (text.length < 3) { status.textContent = 'Escreva um pouco sobre o que foi pedido.'; input.focus(); return; }
        messages.push({role:'user', content:text});
        renderMessage('user', text);
        input.value = '';
        send.disabled = true;
        status.textContent = 'A IA está organizando o briefing…';
        try {
            const response = await fetch(root.dataset.endpoint, {
                method:'POST', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('input[name="_token"]').value},
                body:JSON.stringify({module_key:moduleKey,messages:messages.slice(-12)}),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível consultar a IA.');
            messages.push({role:'assistant',content:data.message});
            renderMessage('assistant', data.message);
            latestDraft = data;
            draftPanel.hidden = false;
            followUp.textContent = data.follow_up?.length ? `Ainda vale confirmar: ${data.follow_up.join(' · ')}` : (data.ready ? 'O briefing já pode ser revisado no formulário.' : 'Confira se o rascunho representa corretamente o pedido.');
            status.textContent = 'Rascunho atualizado. Ele ainda não foi salvo como demanda.';
        } catch (error) {
            messages.pop();
            status.textContent = error.message;
        } finally {
            send.disabled = false;
        }
    });
    root.querySelector('[data-briefing-apply]')?.addEventListener('click', () => {
        if (!latestDraft) return;
        const title = form.querySelector('[name="title"]');
        const brief = form.querySelector('[name="brief"]');
        if (latestDraft.title) title.value = latestDraft.title;
        if (latestDraft.brief) brief.value = latestDraft.brief;
        for (const [key, value] of Object.entries(latestDraft.module_fields || {})) {
            const field = [...form.querySelectorAll('[name]')].find((input) => input.name === `module_fields_data[${key}]`);
            if (field) field.value = value;
        }
        title.focus();
        status.textContent = 'Rascunho copiado para os campos editáveis. Revise antes de criar a demanda.';
    });
})();
</script>
@endif
<style>
.ai-briefing-guide{margin-top:24px;background:linear-gradient(120deg,#fff,#f5fbfd)}.ai-briefing-guide h2{font-size:16px;margin:0}.ai-briefing-guide .assistant-badge{border-radius:999px;background:#e8f5fa;color:#204b61;padding:7px 10px;font-size:10px;font-weight:700;white-space:nowrap}.ai-briefing-privacy,.ai-briefing-empty,.ai-briefing-status{color:#718087;font-size:11px;line-height:1.55}.ai-briefing-messages{display:grid;gap:8px;max-height:280px;overflow:auto;margin:16px 0;padding:12px;border:1px solid #e3ecee;border-radius:12px;background:#fff}.ai-briefing-messages:empty{display:none}.ai-briefing-message{max-width:92%;margin:0;padding:10px 12px;border-radius:11px;font-size:12px;line-height:1.6;white-space:pre-wrap}.ai-briefing-user{justify-self:end;background:#eaf6fa;color:#204b61}.ai-briefing-assistant{justify-self:start;background:#f4f7f7;color:#344d56}.ai-briefing-guide textarea{display:block;width:100%;padding:11px 12px;border:1px solid #d6e0e1;border-radius:10px;font:inherit;font-size:13px;resize:vertical}.ai-briefing-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:10px}.ai-briefing-actions button:disabled{opacity:.6;cursor:wait}.ai-briefing-draft{margin-top:14px;padding:13px;border:1px solid #cfe2e8;border-radius:11px;background:#fff}.ai-briefing-draft p{color:#667a82;font-size:12px;line-height:1.55}.ai-briefing-draft[hidden]{display:none}.module-custom-fields{margin:16px 0;padding:16px;border:1px solid #dbe5e9;border-radius:10px;background:#fbfdfe}.module-custom-fields[hidden]{display:none}.module-custom-fields .form-section-heading{margin:0 0 12px}.module-custom-fields #module-fields-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}@media(max-width:600px){.module-custom-fields{padding:12px}.module-custom-fields #module-fields-list{grid-template-columns:1fr}.ai-briefing-guide .section-heading{align-items:flex-start;flex-direction:column}.ai-briefing-actions{align-items:stretch;flex-direction:column}}
</style>
@endif
@endsection
