@if (auth()->check() && auth()->user()->role !== App\Enums\UserRole::Client)
    @php
        $copilotArea = match (true) {
            request()->routeIs('demands.create', 'demands.store') => 'demands',
            request()->routeIs('demands.*', 'ai-planning.*') => 'demands',
            request()->routeIs('approvals.*', 'demand-reviews.*', 'client-reviews.*') => 'approvals',
            request()->routeIs('demand-tasks.*') => 'tasks',
            request()->routeIs('team.capacity*') => 'capacity',
            request()->routeIs('team.activity*', 'team.index', 'team.members.*') => 'team',
            request()->routeIs('performance-reviews.*') => 'evaluations',
            request()->routeIs('knowledge.*') => 'knowledge',
            request()->routeIs('service-access.*') => 'service-access',
            request()->routeIs('organization-assistant.*') => 'overview',
            request()->routeIs('dashboard') => 'overview',
            default => 'overview',
        };
    @endphp
    <div class="mix7-copilot" data-copilot data-endpoint="{{ route('contextual-assistant.ask') }}" data-area="{{ $copilotArea }}">
        <button type="button" class="mix7-copilot-launch" aria-expanded="false" aria-controls="mix7-copilot-panel">✦ Ajuda com IA</button>
        <section id="mix7-copilot-panel" class="mix7-copilot-panel" hidden aria-label="Ajuda contextual da Mix7">
            <header><div><strong>Assistente Mix7</strong><span>Orientação para esta área</span></div><button type="button" data-copilot-close aria-label="Fechar assistente">×</button></header>
            <div class="mix7-copilot-log" data-copilot-log role="log" aria-live="polite"><p class="mix7-copilot-welcome">Pergunte como usar esta área ou como conferir uma etapa. O assistente não vê os dados da tela nem altera processos.</p></div>
            <form data-copilot-form><input type="hidden" name="_token" value="{{ csrf_token() }}"><label class="sr-only" for="mix7-copilot-question">Sua pergunta</label><textarea id="mix7-copilot-question" rows="2" maxlength="2000" placeholder="O que você precisa entender?" required></textarea><button type="submit">Perguntar</button><small data-copilot-status>Não inclua senhas ou dados de cliente desnecessários.</small></form>
        </section>
    </div>
    <script>
    (() => {
        const root=document.querySelector('[data-copilot]'); if(!root)return;
        const launch=root.querySelector('.mix7-copilot-launch'), panel=root.querySelector('.mix7-copilot-panel'), form=root.querySelector('[data-copilot-form]'), box=form.querySelector('textarea'), log=root.querySelector('[data-copilot-log]'), status=form.querySelector('[data-copilot-status]'), messages=[];
        const add=(role,text)=>{log.querySelector('.mix7-copilot-welcome')?.remove();const p=document.createElement('p');p.className=`mix7-copilot-message ${role}`;p.textContent=text;log.append(p);log.scrollTop=log.scrollHeight;};
        launch.addEventListener('click',()=>{const open=panel.hidden;panel.hidden=!open;launch.setAttribute('aria-expanded',String(open));if(open)box.focus();});
        root.querySelector('[data-copilot-close]').addEventListener('click',()=>{panel.hidden=true;launch.setAttribute('aria-expanded','false');launch.focus();});
        form.addEventListener('submit',async(event)=>{event.preventDefault();const text=box.value.trim();if(text.length<3)return;messages.push({role:'user',content:text});add('user',text);box.value='';const button=form.querySelector('button[type="submit"]');button.disabled=true;status.textContent='Consultando o assistente…';try{const response=await fetch(root.dataset.endpoint,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({area:root.dataset.area,messages})});const data=await response.json();if(!response.ok)throw new Error(data.message||'A consulta não foi concluída.');messages.push({role:'assistant',content:data.answer});add('assistant',data.answer);status.textContent='Orientação geral: confira a tela antes de executar qualquer ação.';}catch(error){messages.pop();status.textContent=error.message;}finally{button.disabled=false;box.focus();}});
    })();
    </script>
    <style>
        .mix7-copilot{position:fixed;z-index:1000;right:22px;bottom:22px;font-family:inherit}.mix7-copilot-launch{min-height:46px;padding:0 17px;border:1px solid #d3e8ee;border-radius:999px;background:#204b61;color:#fff;font:inherit;font-size:13px;font-weight:700;box-shadow:0 8px 24px #204b6130;cursor:pointer}.mix7-copilot-panel{position:absolute;right:0;bottom:58px;display:grid;grid-template-rows:auto minmax(150px,1fr) auto;width:min(390px,calc(100vw - 28px));height:min(560px,calc(100vh - 110px));overflow:hidden;border:1px solid #d6e5e8;border-radius:16px;background:#fff;box-shadow:0 16px 48px #173b4b2a}.mix7-copilot-panel[hidden]{display:none}.mix7-copilot-panel header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e6eff1;background:#f4fbfd}.mix7-copilot-panel header div{display:grid;gap:4px}.mix7-copilot-panel header strong{color:#204b61;font-size:14px}.mix7-copilot-panel header span,.mix7-copilot-panel small{color:#718087;font-size:10px}.mix7-copilot-panel header button{width:32px;height:32px;border:1px solid #d9e5e7;border-radius:50%;background:#fff;color:#52666e;font-size:20px;cursor:pointer}.mix7-copilot-log{display:flex;flex-direction:column;gap:9px;overflow:auto;padding:14px}.mix7-copilot-welcome{margin:0;color:#718087;font-size:12px;line-height:1.55}.mix7-copilot-message{max-width:92%;margin:0;padding:9px 11px;border-radius:11px;font-size:12px;line-height:1.55;white-space:pre-wrap}.mix7-copilot-message.user{align-self:flex-end;background:#eaf6fa;color:#204b61}.mix7-copilot-message.assistant{align-self:flex-start;background:#f3f7f8;color:#344d56}.mix7-copilot-panel form{display:grid;gap:8px;padding:12px;border-top:1px solid #e6eff1}.mix7-copilot-panel textarea{width:100%;resize:vertical;border:1px solid #d6e0e1;border-radius:9px;padding:9px 10px;color:#202e35;font:inherit;font-size:12px}.mix7-copilot-panel form>button{justify-self:end;min-height:36px;padding:7px 14px;border:0;border-radius:9px;background:#204b61;color:#fff;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.mix7-copilot-panel form>button:disabled{opacity:.6}.mix7-copilot-panel small{line-height:1.4}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}@media(max-width:500px){.mix7-copilot{right:12px;bottom:12px}.mix7-copilot-panel{position:fixed;right:12px;bottom:68px;width:calc(100vw - 24px);height:min(620px,calc(100dvh - 90px))}}
    </style>
@endif
