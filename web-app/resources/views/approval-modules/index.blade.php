@extends('layouts.app')

@section('title', 'Tipos de aprovação · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'approval-modules'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Configuração da agência</p>
            <h1 class="heading">Tipos de aprovação</h1>
            <p class="subheading">Cadastre outras áreas que precisam de aprovação. Nesta etapa, todos usam o mesmo fluxo de demanda, tarefas e revisão por link.</p>
            @include('partials.flash')
            @if ($errors->any())<div class="notice notice-error">Confira os campos destacados.</div>@endif

            <section class="panel module-list" aria-labelledby="module-list-heading">
                <div class="section-heading"><div><h2 id="module-list-heading">Tipos disponíveis</h2><p>Os tipos desativados deixam de aparecer em novas demandas. Os registros antigos são preservados.</p></div></div>
                @foreach ($modules as $module)
                    <article class="module-row">
                        <div><strong>{{ $module['label'] }}</strong><p>{{ $module['description'] }}</p><span class="module-key">{{ $module['built_in'] ? 'Padrão' : $module['key'] }} · {{ $module['is_active'] ? 'Ativo' : 'Desativado' }}@if (!$module['built_in']) · Cadastrado por {{ $module['created_by_name'] }}@if ($module['updated_by_name']) · Alterado por {{ $module['updated_by_name'] }}@endif @endif</span></div>
                        @if (!$module['built_in'])
                            <form method="post" action="{{ route('approval-modules.toggle', $module['id']) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">{{ $module['is_active'] ? 'Desativar' : 'Reativar' }}</button></form>
                        @endif
                    </article>
                @endforeach
            </section>

            <section class="panel module-create">
                <div class="section-heading"><div><h2>Adicionar um tipo</h2><p>Por exemplo, uma área da agência que precisa receber materiais e aprovação do cliente.</p></div></div>
                <form method="post" action="{{ route('approval-modules.store') }}" class="form-card">@csrf
                    <label class="field">Nome do tipo<input name="label" value="{{ old('label') }}" minlength="2" maxlength="120" required placeholder="Ex.: Apresentações"><span class="field-help">Este nome aparece ao criar uma demanda.</span>@error('label')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Para que serve?<textarea name="description" rows="3" minlength="3" maxlength="500" required placeholder="Explique em uma frase o que será revisado.">{{ old('description') }}</textarea><span class="field-help">Use somente o fluxo compartilhado nesta etapa. Etapas e campos exclusivos ainda precisam de requisitos aprovados.</span>@error('description')<span class="error">{{ $message }}</span>@enderror</label>
                    <details><summary>Identificador técnico (opcional)</summary><label class="field">Chave curta<input name="key" value="{{ old('key') }}" minlength="2" maxlength="60" pattern="[a-z][a-z0-9_]*" placeholder="gerada_a_partir_do_nome"><span class="field-help">Comece com letra minúscula e use letras, números ou sublinhado.</span>@error('key')<span class="error">{{ $message }}</span>@enderror</label></details>
                    <div class="form-actions"><button class="primary-button" type="submit">Adicionar tipo de aprovação</button></div>
                </form>
            </section>
        </div>
    </main>
</div>
<style>
.module-list,.module-create{margin-bottom:18px}.module-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid #edf0ef}.module-row strong{color:#204b61}.module-row p{margin:5px 0;color:#52666e;font-size:13px}.module-key{color:#718087;font-size:11px}.module-row form{flex:none}.module-create details{margin:14px 0;color:#326c82;font-size:13px}.module-create summary{cursor:pointer;font-weight:700}.module-create .form-card{padding:0;border:0;box-shadow:none}.module-create .form-actions{justify-content:flex-start}@media(max-width:560px){.module-row{align-items:flex-start;flex-direction:column}.module-row form{align-self:flex-start}}
</style>
@endsection
