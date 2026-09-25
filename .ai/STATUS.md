# Estado atual — Plataforma Mix7 — 2026-09-25

## Objetivo
Levar a plataforma integrada de gestão de equipe e processos de aprovação até uma primeira versão segura e utilizável no dia a dia. A meta permanece maior que a demonstração local atual.

## Estado do produto e do repositório
- Branch `implementation/primeira-jornada-local` tem uma correção local ainda não publicada; memória, documentação e evidência visual desta correção estão sendo preparadas para sincronização.
- PR #9 (implementação), PR #8 (requisitos priorizados) e PR #10 (pesquisa e arquitetura conceitual) estão abertos em rascunho. PR #10 não seleciona stack de produção.
- O protótipo `prototipo/` é local e persiste por perfil de navegador. A biblioteca Conhecimento já cadastra, edita, filtra e arquiva/restaura referências, treinamentos, contatos e onboarding com passos. Ainda não há autenticação, contas reais, matriz de permissões, servidor, armazenamento central, colaboração multiusuário nem implantação de produção.

## Validação mais recente
- Nova demanda: corrigida atualização do Kanban após salvar. Chromium isolado confirmou exibição imediata, persistência após recarga e renderização literal de texto HTML/JavaScript sem execução ou erro de página. Captura inspecionada: `docs/evidencias/visual/nova-demanda-imediata-1440.png`. `node --test tests/*.test.js`: 31/31; checagens de sintaxe de `app.js` e `workflow.js` e `git diff --check` passaram. O registro está sendo sincronizado com a branch e com o cartão Trello #23; comentário no Trello aguarda confirmação pontual do usuário.
- Chromium isolado criou os quatro tipos da biblioteca; onboarding sem passos foi recusado; edição, busca, filtro, arquivamento, restauração e recarga passaram. A troca para Conhecimento atualiza corretamente o cabeçalho e oculta “Nova demanda”. Auditoria de navegação percorreu as oito páginas; título, página ativa e visibilidade do botão global corresponderam às páginas, sem botões visíveis sem nome acessível nem erros. Em 390 × 844, cadastro móvel passou e `body/document.scrollWidth` foram ambos 390; sem erros de página. Capturas renderizadas e inspecionadas em `docs/evidencias/visual/conhecimento-desktop-1440.png` e `conhecimento-mobile-390.png`.
- `node --test tests/*.test.js`: 31/31 passaram; `node --check` de `workflow.js`, `knowledge.js` e `app.js`, além de `git diff --check`, passaram após a conferência visual; a jornada Playwright desktop/móvel confirmou os quatro tipos, validação de onboarding, edição, busca, filtro, arquivamento, restauração e persistência após recarga. Implementação no commit `bb41353`, remoto igual ao local; CI push e PR passaram nos runs [36147487882](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36147487882) e [36147492969](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36147492969).
- O cartão Trello #30 [Criar biblioteca local de conhecimento e onboarding](https://trello.com/c/9CC2wSxA/30-criar-biblioteca-local-de-conhecimento-e-onboarding) está Em andamento com os cinco itens de entrega marcados e comentário com escopo, evidências, PR e commit. Permanece nessa lista até validação das regras reais pela Mix7.

## Pendências que impedem declarar lançamento
- O caso operacional real da Mix7, atores, exceções, permissões e evidências finais por serviço ainda precisam de validação.
- Contas reais não podem ser provisionadas sem nomes/e-mails confirmados, autorização e matriz aprovada. Conteúdo e contatos reais, responsáveis, públicos, revisão e conclusão pessoal de onboarding aguardam validação; IA, cálculo de disponibilidade/capacidade e integrações não estão implementados.
- Pesquisa/arquitetura e requisitos priorizados ainda estão em PRs rascunho. A prova auto-hospedada não começou; a última verificação registrou Docker/Podman indisponíveis.
- Comparação final com o dashboard autenticado do CRM aguarda as capturas de tela prometidas pelo usuário.
- Atualizar a checklist do cartão 18 no Trello ainda aguarda a confirmação pontual já solicitada para aquele item.

## Próximo passo
Sincronizar esta correção e sua evidência no GitHub e atualizar o cartão Trello #23 conforme a confirmação pontual pendente. Depois, continuar a auditoria funcional das lacunas P0/P1 com evidência, sem bloquear o trabalho pela sessão CRM prometida. Próximos limites verificáveis: validar arraste físico do Explorador para o navegador quando disponível e aguardar caso real anonimizado para campos, papéis e exceções. Manter dados sintéticos até existir ambiente com identidade, permissões e armazenamento adequados.

Histórico operacional anterior preservado em [docs/VALIDACOES-HISTORICAS.md](../docs/VALIDACOES-HISTORICAS.md); este arquivo é a fonte vigente de status.
