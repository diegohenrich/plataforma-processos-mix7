@extends('layouts.app')

@section('title', 'Tipos de aprovação · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Configuração da agência</p>
            <h1 class="heading">Configurar tipos de aprovação</h1>
            @include('partials.work-tabs', ['workView' => 'settings'])
            <p class="subheading">Cadastre áreas, dados próprios e etapas de conferência. A trilha principal continua compartilhada; cada tipo pode guardar seu próprio roteiro interno.</p>
            @include('partials.flash')
            @if ($errors->any())<div class="notice notice-error">Confira os campos destacados.</div>@endif

            <section class="panel module-list" aria-labelledby="module-list-heading">
                <div class="section-heading"><div><h2 id="module-list-heading">Tipos disponíveis</h2><p>Os tipos desativados deixam de aparecer em novas demandas. Os registros antigos são preservados.</p></div></div>
                @foreach ($modules as $module)
                    <article class="module-row">
                        <div class="module-row-main"><strong>{{ $module['label'] }}</strong><p>{{ $module['description'] }}</p><span class="module-key">{{ $module['built_in'] ? 'Padrão' : $module['key'] }} · configuração v{{ $module['version'] }} · {{ $module['is_active'] ? 'Ativo' : 'Desativado' }}@if (!$module['built_in']) · Cadastrado por {{ $module['created_by_name'] }}@if ($module['updated_by_name']) · Alterado por {{ $module['updated_by_name'] }}@endif @endif</span>
                            @if (!$module['built_in'])
                                <details class="module-fields-editor"><summary>Configurar campos e etapas internas</summary>
                                    <form method="post" action="{{ route('approval-modules.fields', $module['id']) }}" class="form-card">@csrf @method('PUT')
                                        <p class="field-help">As mudanças valem para novas demandas. As antigas preservam campos, etapas e progresso registrados.</p>
                                        <div class="module-field-list">
                                            @foreach (old('fields', $module['fields']) as $index => $field)
                                                <div class="module-field-row">
                                                    <label class="field">Nome<input name="fields[{{ $index }}][label]" value="{{ $field['label'] }}" maxlength="80" required></label>
                                                    <label class="field">Chave<input name="fields[{{ $index }}][key]" value="{{ $field['key'] }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required></label>
                                                    <label class="field">Tipo<select name="fields[{{ $index }}][type]" required>@foreach (['text' => 'Texto curto', 'textarea' => 'Texto longo', 'date' => 'Data', 'url' => 'Link', 'select' => 'Lista de opções'] as $value => $label)<option value="{{ $value }}" @selected($field['type'] === $value)>{{ $label }}</option>@endforeach</select></label>
                                                    <label class="field options-field">Opções (uma por linha)<textarea name="fields[{{ $index }}][options]" rows="2">{{ implode("\n", $field['options'] ?? []) }}</textarea></label>
                                                    <label class="field required-field"><input type="checkbox" name="fields[{{ $index }}][required]" value="1" @checked($field['required'])> Obrigatório</label>
                                                    <button class="remove-module-field" type="button">Remover campo</button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <button class="secondary-button add-module-field" type="button">+ Adicionar campo</button>
                                        <fieldset class="module-step-builder"><legend>Etapas de conferência ({{ count($module['workflow_steps']) }})</legend><p>Passos internos para acompanhar este tipo; não mudam a etapa principal da demanda.</p><div class="module-step-list">
                                            @foreach (old('workflow_steps', $module['workflow_steps']) as $index => $step)
                                                <div class="module-step-row"><label class="field">Nome da etapa<input name="workflow_steps[{{ $index }}][label]" value="{{ $step['label'] }}" maxlength="80" required></label><label class="field">Identificador<input name="workflow_steps[{{ $index }}][key]" value="{{ $step['key'] }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required></label><button class="remove-module-step" type="button">Remover etapa</button></div>
                                            @endforeach
                                        </div><button class="secondary-button add-module-step" type="button">+ Adicionar etapa</button></fieldset>
                                        <div class="form-actions"><button class="primary-button" type="submit">Salvar configuração</button></div>
                                    </form>
                                </details>
                                <form method="post" action="{{ route('approval-modules.toggle', $module['id']) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">{{ $module['is_active'] ? 'Desativar' : 'Reativar' }}</button></form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="panel module-create">
                <div class="section-heading"><div><h2>Adicionar um tipo</h2><p>Por exemplo, uma área da agência que precisa receber materiais e aprovação do cliente.</p></div></div>
                <form method="post" action="{{ route('approval-modules.store') }}" class="form-card">@csrf
                    <label class="field">Nome do tipo<input name="label" value="{{ old('label') }}" minlength="2" maxlength="120" required placeholder="Ex.: Apresentações"><span class="field-help">Este nome aparece ao criar uma demanda.</span>@error('label')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Para que serve?<textarea name="description" rows="3" minlength="3" maxlength="500" required placeholder="Explique em uma frase o que será revisado.">{{ old('description') }}</textarea><span class="field-help">O tipo usa o fluxo principal da plataforma e pode ter campos e etapas internas próprias.</span>@error('description')<span class="error">{{ $message }}</span>@enderror</label>
                    <fieldset class="module-field-builder"><legend>Campos próprios do tipo (opcional)</legend><p>Essas informações ficam disponíveis somente para a equipe. Cada demanda guarda a versão do formulário usado.</p><div class="module-field-list">
                        @foreach (old('fields', []) as $index => $field)
                            <div class="module-field-row">
                                <label class="field">Nome<input name="fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" maxlength="80" required></label>
                                <label class="field">Chave<input name="fields[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required></label>
                                <label class="field">Tipo<select name="fields[{{ $index }}][type]" required>@foreach (['text' => 'Texto curto', 'textarea' => 'Texto longo', 'date' => 'Data', 'url' => 'Link', 'select' => 'Lista de opções'] as $value => $label)<option value="{{ $value }}" @selected(($field['type'] ?? 'text') === $value)>{{ $label }}</option>@endforeach</select></label>
                                <label class="field options-field">Opções (uma por linha)<textarea name="fields[{{ $index }}][options]" rows="2">{{ $field['options'] ?? '' }}</textarea></label>
                                <label class="field required-field"><input type="checkbox" name="fields[{{ $index }}][required]" value="1" @checked($field['required'] ?? false)> Obrigatório</label>
                                <button class="remove-module-field" type="button">Remover campo</button>
                            </div>
                        @endforeach
                    </div><button class="secondary-button add-module-field" type="button">+ Adicionar campo</button></fieldset>
                    <fieldset class="module-step-builder"><legend>Etapas de conferência (opcional)</legend><p>Adicione os passos internos que a equipe deve acompanhar neste tipo. A etapa principal continua separada.</p><div class="module-step-list">
                        @foreach (old('workflow_steps', []) as $index => $step)
                            <div class="module-step-row"><label class="field">Nome da etapa<input name="workflow_steps[{{ $index }}][label]" value="{{ $step['label'] ?? '' }}" maxlength="80" required></label><label class="field">Identificador<input name="workflow_steps[{{ $index }}][key]" value="{{ $step['key'] ?? '' }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required></label><button class="remove-module-step" type="button">Remover etapa</button></div>
                        @endforeach
                    </div><button class="secondary-button add-module-step" type="button">+ Adicionar etapa</button></fieldset>
                    <details><summary>Identificador técnico (opcional)</summary><label class="field">Chave curta<input name="key" value="{{ old('key') }}" minlength="2" maxlength="60" pattern="[a-z][a-z0-9_]*" placeholder="gerada_a_partir_do_nome"><span class="field-help">Comece com letra minúscula e use letras, números ou sublinhado.</span>@error('key')<span class="error">{{ $message }}</span>@enderror</label></details>
                    <div class="form-actions"><button class="primary-button" type="submit">Adicionar tipo de aprovação</button></div>
                </form>
            </section>
        </div>
    </main>
</div>
<template id="module-field-template"><div class="module-field-row"><label class="field">Nome<input data-field="label" maxlength="80" required placeholder="Ex.: Link de referência"></label><label class="field">Chave<input data-field="key" maxlength="40" pattern="[a-z][a-z0-9_]*" required placeholder="link_referencia"></label><label class="field">Tipo<select data-field="type"><option value="text">Texto curto</option><option value="textarea">Texto longo</option><option value="date">Data</option><option value="url">Link</option><option value="select">Lista de opções</option></select></label><label class="field options-field">Opções (uma por linha)<textarea data-field="options" rows="2" placeholder="Opção 1&#10;Opção 2"></textarea></label><label class="field required-field"><input data-field="required" type="checkbox" value="1"> Obrigatório</label><button class="remove-module-field" type="button">Remover campo</button></div></template>
<template id="module-step-template"><div class="module-step-row"><label class="field">Nome da etapa<input data-step="label" maxlength="80" required placeholder="Ex.: Aprovação interna"></label><label class="field">Identificador<input data-step="key" maxlength="40" pattern="[a-z][a-z0-9_]*" required placeholder="aprovacao_interna"></label><button class="remove-module-step" type="button">Remover etapa</button></div></template>
<script>
(() => {
    const template = document.getElementById('module-field-template');
    const stepTemplate = document.getElementById('module-step-template');
    document.querySelectorAll('.module-field-row').forEach((row) => {
        const type = row.querySelector('[name$="[type]"]');
        const options = row.querySelector('.options-field');
        const toggleOptions = () => options.hidden = type.value !== 'select';
        type.addEventListener('change', toggleOptions);
        toggleOptions();
        row.querySelector('.remove-module-field').addEventListener('click', () => row.remove());
    });
    document.querySelectorAll('.add-module-field').forEach((button) => button.addEventListener('click', () => {
        const list = button.parentElement.querySelector('.module-field-list');
        if (list.children.length >= 20) return;
        const indexes = Array.from(list.querySelectorAll('[name]')).map((field) => Number(field.name.match(/^fields\[(\d+)\]/)?.[1] ?? -1));
        const index = Math.max(-1, ...indexes) + 1;
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-field]').forEach((field) => field.name = `fields[${index}][${field.dataset.field}]`);
        const slug = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        row.querySelector('[data-field="label"]').addEventListener('input', (event) => {
            const key = row.querySelector('[data-field="key"]');
            if (!key.dataset.edited) key.value = slug(event.target.value);
        });
        row.querySelector('[data-field="key"]').addEventListener('input', (event) => event.target.dataset.edited = 'true');
        row.querySelector('[data-field="type"]').addEventListener('change', (event) => row.querySelector('.options-field').hidden = event.target.value !== 'select');
        row.querySelector('.remove-module-field').addEventListener('click', () => row.remove());
        row.querySelector('.options-field').hidden = true;
        list.append(row);
    }));
    document.querySelectorAll('.options-field textarea[name]').forEach((options) => {
        const type = options.closest('.module-field-row').querySelector('[name$="[type]"]');
        options.closest('.options-field').hidden = type.value !== 'select';
    });
    document.querySelectorAll('.module-step-row').forEach((row) => row.querySelector('.remove-module-step').addEventListener('click', () => row.remove()));
    document.querySelectorAll('.add-module-step').forEach((button) => button.addEventListener('click', () => {
        const list = button.parentElement.querySelector('.module-step-list');
        if (list.children.length >= 20) return;
        const indexes = Array.from(list.querySelectorAll('[name]')).map((field) => Number(field.name.match(/^workflow_steps\[(\d+)\]/)?.[1] ?? -1));
        const index = Math.max(-1, ...indexes) + 1;
        const row = stepTemplate.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-step]').forEach((field) => field.name = `workflow_steps[${index}][${field.dataset.step}]`);
        const slug = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        row.querySelector('[data-step="label"]').addEventListener('input', (event) => {
            const key = row.querySelector('[data-step="key"]');
            if (!key.dataset.edited) key.value = slug(event.target.value);
        });
        row.querySelector('[data-step="key"]').addEventListener('input', (event) => event.target.dataset.edited = 'true');
        row.querySelector('.remove-module-step').addEventListener('click', () => row.remove());
        list.append(row);
    }));
})();
</script>
<style>
.module-list,.module-create{margin-bottom:18px}.module-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid #edf0ef}.module-row-main{flex:1}.module-row strong{color:#204b61}.module-row p{margin:5px 0;color:#52666e;font-size:13px}.module-key{color:#718087;font-size:11px}.module-row form{flex:none}.module-create details{margin:14px 0;color:#326c82;font-size:13px}.module-create summary{cursor:pointer;font-weight:700}.module-create .form-card{padding:0;border:0;box-shadow:none}.module-create .form-actions{justify-content:flex-start}.module-fields-editor{margin:12px 0}.module-fields-editor .form-card{margin-top:12px}.module-field-builder{margin:18px 0;padding:14px;border:1px solid #dbe5e9;border-radius:10px}.module-field-builder legend{padding:0 6px;color:#204b61;font-weight:700}.module-field-builder>p,.field-help{color:#65777d;font-size:12px}.module-field-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto auto;align-items:end;gap:10px;padding:12px 0;border-top:1px solid #edf0ef}.module-field-row .field{margin:0}.required-field{display:flex;align-items:center;gap:6px;white-space:nowrap;font-size:12px}.required-field input{width:auto}.remove-module-field{border:0;background:none;color:#9b3a37;text-decoration:underline;cursor:pointer;padding:8px}.module-field-list:empty{display:none}@media(max-width:900px){.module-field-row{grid-template-columns:1fr 1fr}}@media(max-width:560px){.module-row{align-items:flex-start;flex-direction:column}.module-row form{align-self:flex-start}.module-field-row{grid-template-columns:1fr}.module-field-builder{padding:10px}}
.module-step-builder{margin:18px 0;padding:14px;border:1px solid #dbe5e9;border-radius:10px}.module-step-builder legend{padding:0 6px;color:#204b61;font-weight:700}.module-step-builder>p{color:#65777d;font-size:12px}.module-step-row{display:grid;grid-template-columns:1fr 1fr auto;align-items:end;gap:10px;padding:12px 0;border-top:1px solid #edf0ef}.module-step-row .field{margin:0}.module-step-list:empty{display:none}.remove-module-step{border:0;background:none;color:#9b3a37;text-decoration:underline;cursor:pointer;padding:8px}@media(max-width:900px){.module-step-row{grid-template-columns:1fr 1fr}}@media(max-width:560px){.module-step-builder{padding:10px}.module-step-row{grid-template-columns:1fr}}
</style>
@endsection
