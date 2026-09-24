# Estado em 2026-09-24

## Continuidade — fluxo-alvo e documentação alinhados

- O usuário aprovou como fluxo-alvo: demanda/briefing → planejamento revisado → execução → revisão interna → aprovação do cliente → ajustes e nova versão quando pedidos → entrega/agendamento/publicação com evidência → conclusão conferida. Isso define o comportamento desejado do produto; não é prova de como a Mix7 opera hoje.
- `.ai/CONTEXT.md`, `README.md`, `docs/PRODUCT.md` e `docs/REQUIREMENTS.md` foram alinhados a essa decisão. O caso real ainda deve revelar quem exerce os papéis, exceções e evidência aplicável por tipo de serviço.
- Verificações executadas nesta rodada: `node --test tests/workflow.test.js` (6/6 passaram), `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram.
- Commit documental `cf16550d6c4792c5cef6ed2b07329539be82cc15` publicado e confirmado idêntico no remoto da branch `implementation/primeira-jornada-local`; PR #9 segue como rascunho. Cartão 18 do Trello atualizado e resposta da ferramenta conferiu descrição, fluxo, limites e link do commit.
- Próximo passo: obter um caso real anonimizado de demanda da Mix7 e comparar os fatos (papéis, exceções e evidências) ao fluxo-alvo; depois revisar protótipo e detalhar arquitetura/modelo de dados para produção. Nenhum dado de cliente deve ser incluído no protótipo local.

## Concluído

- Quadro Trello existente localizado e conexão verificada.
- Criadas as seis listas planejadas e 18 cartões de contexto, requisitos e entregas.
- Aplicadas as cinco cores de etiquetas já existentes no Trello aos cartões pertinentes. Legenda registrada no cartão **Decisões vigentes da primeira entrega**: azul = Gestão de equipe; verde = Aprovações; roxo = IA e automações; amarelo = Conhecimento e acessos; laranja = Pesquisa de soluções.
- Criados checklists nas cinco entregas e no cartão de decisões; entregas ligadas aos cartões de dependência por URLs nas descrições.
- Referência https://mix7.com.br/ inspecionada; preferência explícita por interface clara em branco e azul registrada no cartão visual.
- Criada documentação inicial em `README.md`, `docs/`, `CONTRIBUTING.md` e template de pull request; as duas pastas de módulos agora possuem README sem código. Inicializado Git local e criado repositório privado `diegohenrich/plataforma-processos-mix7`.
- Regra de sincronização GitHub + Trello solicitada pelo usuário e registrada em `AGENTS.md`, `CONTRIBUTING.md`, `.ai/CONTEXT.md` e `.ai/DECISIONS.md`.
- As cinco perguntas sobre o fluxo foram respondidas como proposta de funcionamento, documentada em `docs/FLUXO-PROPOSTO.md` e vinculada aos requisitos. O fluxo ainda precisa ser validado com um caso real da Mix7.

## Estado atual e validação

- Os cartões ainda não representam requisitos aprovados: questões abertas permanecem em **Requisitos a validar**.
- O fluxo proposto define responsáveis e transições para criativos de redes sociais, mas não é descrição confirmada da operação atual; papéis concretos, exceções e critério de conclusão seguem pendentes de validação.
- Após login da Atlassian no navegador, as cinco etiquetas de área e a etiqueta vermelha de **Bloqueio** foram nomeadas diretamente no Trello. O usuário apontou que a legenda isolada não bastava; a descrição do quadro e `docs/TRELLO.md` agora explicam a finalidade de cada uma.
- Validação das etiquetas: leitura pela integração confirmou os seis pares nome/cor e a descrição do quadro; a interface mostrou os nomes nos cartões após ativar a exibição expandida e recarregar o quadro. Nenhuma etiqueta permanece sem nome.
- Validação final pela integração: seis listas na ordem planejada; 18 cartões (3 de contexto, 10 de requisitos, 5 de entregas); etiquetas azul, verde, roxa, amarela e laranja aplicadas conforme a legenda; descrições e links conferidos em cartões de amostra; checklists conferidos pela leitura direta, inclusive as três etapas do protótipo.
- Uma atualização intermediária substituiu a descrição de cinco cartões de entrega; os textos completos foram restaurados e conferidos por leitura posterior.
- Commit inicial `904ca87` publicado em `main` no repositório privado `diegohenrich/plataforma-processos-mix7`. A checagem `git diff --cached --check` passou; 11 arquivos Markdown foram examinados e nenhum link local quebrado foi encontrado. A aplicação ainda não tem código, portanto não há testes ou build aplicáveis.
- Regra de sincronização publicada no [PR #1](https://github.com/diegohenrich/plataforma-processos-mix7/pull/1), integrado à `main` no commit `2a22c3c`. O commit local e o remoto de `main` foram comparados e eram idênticos. O cartão **Decisões vigentes da primeira entrega** no Trello foi atualizado com a regra e o link do PR.
- Marco inicial preservado pela tag anotada `marco-2026-09-24-organizacao-inicial`, enviada ao GitHub e registrada no mesmo cartão do Trello.
- `README.md` atualizado para destacar a regra de sincronização e a tag do marco inicial. A validação da publicação deste ajuste consta no histórico Git.
- Proteção automática da branch `main` indisponível no plano atual do GitHub para este repositório privado (API retornou HTTP 403 e exigiu GitHub Pro ou repositório público). Não alterar a privacidade por esse motivo. O processo de push verificado, pull request e preservação de histórico é a proteção operacional adotada.

- PR #6 publicado na branch `research/comparativo-arquitetura-inicial` para revisão; pesquisa e arquitetura de produto permanecem sem integração à `main` até revisão do PR.
- 2026-09-24 — Protótipo estático navegável criado em `prototipo/`. Usa dados fictícios; quadro, lista, formulário de demanda, painel lateral com versão/comentários, busca e simulações de aprovação/ajustes não salvam dados. Isto não é a aplicação de produção.
- Validação visual/funcional do protótipo: aberto e renderizado em Chromium a 1440×1000 e 390×844; screenshots de quadro, lista e painel inspecionados; viewport móvel ficou com 390 px de largura sem overflow global; painel mediu 510 px em desktop e 390 px em celular; busca por Nativa Saúde retornou 2 cartões; criação gerou uma demanda de briefing; aprovar e solicitar ajustes exibiram a próxima ação esperada; nenhum erro JavaScript de página no percurso principal.
- Ainda faltam revisão do usuário/equipe, validar o fluxo contra um caso real, backend compartilhado, login/permissões reais e decisões de produção. A persistência local da fatia seguinte não é armazenamento operacional. Não há stack de produto definida.
- Sincronização do protótipo: [PR #7](https://github.com/diegohenrich/plataforma-processos-mix7/pull/7), branch `prototype/fluxo-integrado` publicada e SHA local/remoto conferidos como iguais; PR permanece aberto para revisão. Cartão 17 atualizado para **Em revisão** com o resultado e o link do PR.

## Implementação local do fluxo — 2026-09-24

- Fatia funcional em `prototipo/`: demandas persistidas por navegador, briefing, tarefas livres atribuídas por rodada, estados de execução, arquivos imagem/vídeo/PDF no IndexedDB, comentários, decisões por versão, ajustes em nova versão e evidência obrigatória para concluir. Tarefas e comentários anteriores permanecem visíveis no histórico.
- Validação automática da fatia original: `node --check prototipo/workflow.js`, `node --check prototipo/app.js`, `node --test tests/workflow.test.js` (5 passaram) e `git diff --check` passaram. Sem etapa de build/dependências para HTML/CSS/JS nativos.
- Validação no navegador em Chromium da fatia original: fluxo ponta a ponta percorreu briefing → plano com tarefa/responsável → produção e conclusão da tarefa → arquivo sintético → revisão interna → pedido de ajuste ligado à V01 → tarefa concluída na rodada V02 → nova versão → revisão interna → aprovação da V02 → entrega/publicação; recarga preservou as duas rodadas, arquivo, comentário e trilha. Em viewport de 390 px, drawer mediu 390 px e `documentElement.scrollWidth` ficou em 390 px; captura visual revisada. Console sem avisos/erros. Arquivo usado era imagem sintética, não de cliente.
- Esta fatia continua sendo demonstração local sem login, contas/permissões, serviço central, sincronização, backup ou uso de dados reais. Stack de produção continua pendente da pesquisa existente.
- 2026-09-24 — Comentários passaram a aceitar ponto percentual na imagem e timecode no vídeo, ambos vinculados à versão; o atalho reabre essa versão e o instante/ponto. Verificação: 6 testes Node passaram; no Chromium, imagem e vídeo sintéticos foram anexados, os comentários V01 reabriram a V01 depois da criação de V02, controle de versão atual funcionou, recarga preservou as versões e comentários; a tela de 390×844 teve `scrollWidth` de 390 px. Inspeção visual desktop/celular concluída; console sem avisos/erros. Dados sintéticos locais.
- A funcionalidade de âncoras é demonstração para validação com a equipe; comentários em versões antigas não são transferidos para versões novas. Integração operacional, arquivos remotos e confirmação das regras da Mix7 continuam pendentes.
- Branch `implementation/primeira-jornada-local`, derivada de `prototype/fluxo-integrado`; commit `e26a3d14a93d190697026fe0c4228b204ddc7887` enviado e comparado ao head remoto. PR #9 permanece rascunho sobre PR #7. Cartões 7 e 18 atualizados com o comportamento de âncoras, validações e questões operacionais pendentes; cartão 18 continua em **Em revisão**.
- Pendência de requisitos: validar o fluxo com um exemplo real da Mix7; especialmente papéis, exceções, revisão interna e evidência operacional de conclusão.

## Próximo passo

- Incorporar um caso real de demanda da Mix7 e resolver fluxo, papéis, revisão interna e significado de conclusão.
- Revisar o protótipo local com usuário/equipe; esta fatia ainda requer revisão, e o PR de implementação permanece pendente.
- Depois da revisão do fluxo, ajustar estados e tarefas à operação confirmada e avançar na decisão de modelo de dados/arquitetura técnica e teste com equipe/cliente.
- Manter documentação, GitHub e Trello sincronizados após cada entrega.
