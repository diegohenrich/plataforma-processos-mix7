@extends('layouts.app')

@section('title', 'Meu perfil · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => ''])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Conta pessoal</p>
            <h1 class="heading">Meu perfil</h1>
            <p class="subheading">Atualize seus dados de contato e a forma como seu cargo aparece para a equipe.</p>
            @include('partials.flash')
            @if ($errors->any())<div class="notice notice-error">Confira os campos indicados abaixo.</div>@endif

            <form class="panel profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-original-email="{{ $user->email }}">
                @csrf @method('PUT')
                <section class="profile-photo-section" aria-label="Foto do perfil">
                    @if ($user->profile_photo_path)<img class="profile-photo-preview" src="{{ route('profile.photo') }}" alt="Foto atual do perfil">@else<span class="profile-photo-preview profile-photo-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>@endif
                    <div class="profile-photo-controls"><strong>Foto do perfil</strong><span class="field-help">JPG, PNG ou WebP. Até 4 MB.</span><label class="secondary-button profile-photo-picker">Escolher foto<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>@error('photo')<span class="error">{{ $message }}</span>@enderror</label>@if ($user->profile_photo_path)<label class="profile-remove-photo"><input type="checkbox" name="remove_photo" value="1"> Remover foto atual</label>@endif</div>
                </section>
                <label class="field">Nome<input name="name" value="{{ old('name', $user->name) }}" maxlength="160" autocomplete="name" required>@error('name')<span class="error">{{ $message }}</span>@enderror</label>
                <label class="field">Cargo<input name="position_title" value="{{ old('position_title', $user->position_title ?: $user->role->label()) }}" maxlength="120" autocomplete="organization-title" required><span class="field-help">Este nome aparece no seu perfil. Ele não altera suas permissões.</span>@error('position_title')<span class="error">{{ $message }}</span>@enderror</label>
                <label class="field">E-mail de acesso<input name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>@error('email')<span class="error">{{ $message }}</span>@enderror</label>

                <section class="profile-access" aria-label="Perfil de acesso">
                    <strong>Permissão no sistema</strong>
                    <span>{{ $user->role->label() }}</span>
                    <p>Esse perfil define o que você pode ver e fazer. Só a direção da agência pode alterar permissões.</p>
                </section>

                <fieldset class="profile-password">
                    <legend>Alterar senha (opcional)</legend>
                    <p class="field-help">Deixe em branco para manter sua senha. A nova senha deve ter pelo menos 12 caracteres e precisa ser confirmada. Para trocar o e-mail ou a senha, abra a confirmação de segurança abaixo.</p>
                    <div class="profile-password-grid">
                        <label class="field">Nova senha<input name="password" type="password" minlength="12" maxlength="200" autocomplete="new-password">@error('password')<span class="error">{{ $message }}</span>@enderror</label>
                        <label class="field">Confirme a nova senha<input name="password_confirmation" type="password" minlength="12" maxlength="200" autocomplete="new-password"></label>
                    </div>
                    <details class="profile-reauth" @if ($errors->has('current_password')) open @endif><summary>Confirmação de segurança para e-mail ou senha</summary><label class="field">Senha atual<input name="current_password" type="password" autocomplete="off">@error('current_password')<span class="error">{{ $message }}</span>@enderror</label></details>
                </fieldset>

                <div class="form-actions"><span class="profile-save-status" data-profile-save-status role="status" aria-live="polite" hidden>Salvando seus dados…</span><button class="primary-button" type="submit" data-profile-save><span data-profile-save-idle>Salvar perfil</span><span data-profile-save-busy hidden>Salvando…</span></button></div>
            </form>
        </div>
    </main>
</div>
<style>
    .profile-form{display:grid;gap:2px;margin-top:22px;padding:24px}.profile-form .field{margin-top:16px}.profile-form .field input:not([type=file]):not([type=checkbox]){display:block;width:100%;margin-top:8px;padding:12px 13px;border:1px solid #d6e0e1;border-radius:11px;background:#fff;color:#202e35;font:inherit;font-size:14px}.profile-form .field input:focus{outline:3px solid #8ecde244;border-color:#52869a}.profile-access{display:grid;gap:5px;margin-top:20px;padding:14px;border:1px solid #deeaeb;border-radius:12px;background:#f8fbfb;color:#38515b;font-size:13px}.profile-access p{margin:0;color:#718087;font-size:11px;line-height:1.5}.profile-password{min-width:0;margin:22px 0 0;padding:0;border:0;border-top:1px solid #e7eceb}.profile-password legend{padding:18px 0 0;color:#202e35;font-size:15px;font-weight:750}.profile-password-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.profile-reauth{margin-top:18px;border:1px solid #e4ebeb;border-radius:10px;padding:12px}.profile-reauth summary{cursor:pointer;color:#38515b;font-size:12px;font-weight:700}.profile-photo-section{display:flex;align-items:center;gap:16px;padding-bottom:18px;border-bottom:1px solid #edf0ef}.profile-photo-preview{width:76px;height:76px;flex:none;border-radius:50%;object-fit:cover}.profile-photo-initial{display:grid;place-items:center;background:#e3f3f8;color:#204b61;font-size:28px;font-weight:750}.profile-photo-controls{display:grid;justify-items:start;gap:5px}.profile-photo-picker{position:relative;margin:3px 0 0!important;cursor:pointer}.profile-photo-picker input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}.profile-remove-photo{display:flex;align-items:center;gap:6px;color:#718087;font-size:12px}.profile-remove-photo input{width:auto!important;margin:0!important}.profile-form .form-actions{margin-top:20px}@media(max-width:600px){.profile-form{padding:18px}.profile-password-grid{grid-template-columns:1fr;gap:0}}
    const form = document.querySelector('.profile-form');
    const passwordInput = form?.querySelector('[name="password"]');
    const emailInput = form?.querySelector('[name="email"]');
    const passwordConfirm = form?.querySelector('[name="current_password"]');
    const saveButton = form?.querySelector('[data-profile-save]');
    const saveIdle = form?.querySelector('[data-profile-save-idle]');
    const saveBusy = form?.querySelector('[data-profile-save-busy]');
    const saveStatus = form?.querySelector('[data-profile-save-status]');
    let submissionStarted = false;
    form?.addEventListener('submit', event => {
        const sensitiveChange = (emailInput?.value.trim().toLowerCase() !== form.dataset.originalEmail.trim().toLowerCase()) || Boolean(passwordInput?.value);
        if (sensitiveChange && !passwordConfirm?.value) {
            event.preventDefault();
            const section = passwordConfirm?.closest('details');
            if (section) section.open = true;
            passwordConfirm?.focus();
            return;
        }

        if (submissionStarted) {
            event.preventDefault();
            return;
        }

        submissionStarted = true;
        form.setAttribute('aria-busy', 'true');
        saveButton.disabled = true;
        saveIdle.hidden = true;
        saveBusy.hidden = false;
        saveStatus.hidden = false;
    });
</style>
<script>
    const photoInput = document.querySelector('.profile-photo-picker input[type="file"]');
    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];
        if (!file) return;
        const preview = document.querySelector('.profile-photo-preview');
        if (!preview) return;
        const imageUrl = URL.createObjectURL(file);
        if (preview.tagName === 'IMG') preview.src = imageUrl;
        else {
            const image = document.createElement('img');
            image.className = 'profile-photo-preview';
            image.alt = 'Prévia da nova foto';
            image.src = imageUrl;
            preview.replaceWith(image);
        }
    });
</script>
@endsection
