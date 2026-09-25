# Estado atual — Plataforma Mix7 — 2026-09-25

## Objetivo
Levar a plataforma integrada de gestão de equipe e processos de aprovação até uma primeira versão segura e utilizável no dia a dia. A meta permanece maior que a demonstração local atual.

## Estado do produto e do repositório
- Branch `implementation/primeira-jornada-local` sincronizada com `origin`; memória/documentação e captura móvel estão atualizadas.
- PR #9 (implementação), PR #8 (requisitos priorizados) e PR #10 (pesquisa e arquitetura conceitual) estão abertos em rascunho. PR #10 não seleciona stack de produção.
- O protótipo `prototipo/` é local e persiste por perfil de navegador. Não há autenticação, contas reais, matriz de permissões, servidor, armazenamento central, colaboração multiusuário nem implantação de produção.

## Validação mais recente
- Em perfil descartável, edição de briefing e comentário interno passaram em 1440 px e 390 × 844; alteração durante execução exige motivo, volta a Planejamento e só retoma após confirmação do plano. Comentário vazio não cria registro; comentário válido aparece na conversa e no histórico.
- `node --test tests/workflow.test.js`: 26/26 passaram; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Para o commit documental atual, CI push [36143595502](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36143595502) e PR [36143599814](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36143599814) passaram.
- Captura móvel em 390 × 844 foi renderizada e inspecionada em `docs/evidencias/visual/comentario-interno-mobile-390.png`; evidência agora está versionada. Status operacional histórico integral foi preservado em `docs/VALIDACOES-HISTORICAS.md`.

## Pendências que impedem declarar lançamento
- O caso operacional real da Mix7, atores, exceções, permissões e evidências finais por serviço ainda precisam de validação.
- Contas reais não podem ser provisionadas sem nomes/e-mails confirmados, autorização e matriz aprovada. Conhecimento, contatos e onboarding seguem parcialmente informativos; IA, cálculo de disponibilidade/capacidade e integrações não estão implementados.
- Pesquisa/arquitetura e requisitos priorizados ainda estão em PRs rascunho. A prova auto-hospedada não começou; a última verificação registrou Docker/Podman indisponíveis.
- Comparação final com o dashboard autenticado do CRM aguarda as capturas de tela prometidas pelo usuário.
- Atualizar a checklist do cartão 18 no Trello aguarda a confirmação pontual já solicitada para publicar o texto.

## Próximo passo
Continuar a auditoria funcional e fechar lacunas P0/P1 com evidência; detalhar o módulo Conhecimento/Onboarding antes de escolher seus campos e conteúdos. Manter dados sintéticos até existir ambiente com identidade, permissões e armazenamento adequados.

Histórico operacional anterior preservado em [docs/VALIDACOES-HISTORICAS.md](../docs/VALIDACOES-HISTORICAS.md); este arquivo é a fonte vigente de status.
