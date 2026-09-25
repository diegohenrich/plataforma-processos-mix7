# Estado em 2026-09-24

## Ajuste de escala visual com referência do CRM — 2026-09-25

- Executados lado a lado o CRM local e o protótipo. A rota administrativa do CRM segue redirecionando para login; nenhuma senha foi inserida. A tela de login e o CSS confirmado sustentam os tamanhos de corpo, títulos, navegação e cartões; a composição do dashboard autenticado continua sem evidência visual.
- Em `prototipo/styles.css`, aumentada a legibilidade de cartões, detalhes, comentários, formulário de briefing, tarefas e painéis; conteúdo usa 14–16 px e títulos de diálogo/painel 28–32 px. Raio dos cartões de demanda e do formulário foi alinhado a 24 px, preservando cores claras/azuis de conteúdo e navegação existente. Nenhum arquivo do CRM foi copiado.
- Chromium renderizou quadro e formulário em 1270×720 após as mudanças; captura mostrou os novos tamanhos e formulário rolável dentro da janela, sem corte do conteúdo visível. `node --test tests/workflow.test.js`: 20/20 passaram; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram.
- Pendentes: validar em viewport móvel e comparar com o dashboard autenticado depois que o usuário abrir a sessão local. Nenhum teste de fluxo de negócio novo foi necessário para esta mudança de CSS. O commit `1346362a10048920d2fff87ad61db9af6424c55c` foi publicado na branch `implementation/primeira-jornada-local`; o SHA local e remoto coincide. A execução GitHub Actions `36093943883` passou.

## Auditoria dos três áudios — 24/09/2026

- Os três OGG originais existem e têm 69,93 s, 66,09 s e 247,35 s; correspondem às durações apresentadas nas transcrições. `faster-whisper-base` local confirmou independentemente os temas gerais; nomes próprios e termos técnicos tiveram erros de reconhecimento, então a transcrição manual continua sendo a fonte textual.
- Criado `docs/TRACEABILIDADE-AUDIOS.md`, que liga as falas às evidências atuais e mostra lacunas: sem cronômetro, Gantt/capacidade, integrações de IA, avaliação, portal de cliente, gestão real de conhecimento/acessos, contatos, drag-and-drop e integração Windows. O documento diferencia cobertura local de funcionalidade operacional e registra que “tudo do Trello” precisa de decomposição verificável.
- Atualizados `docs/REQUIREMENTS.md` e README para explicitar arrastar arquivos e a questão ainda aberta sobre funções do Trello. Pesquisa/arquitetura e especificação prioritária continuam em PRs #6/#10 e #8, não integrados a esta branch.
- Validação desta auditoria: `ffprobe` conferiu as durações; transcrição local foi executada sem baixar modelos; comparação manual confirmou que os temas e intervalos dos três áudios aparecem na transcrição fornecida. Nenhum OGG ou transcrição foi adicionado ao repositório.
- Próximo: implementar e testar arrastar-e-soltar em arquivos do briefing; depois sincronizar tarefa específica no Trello/GitHub. O dashboard autenticado do CRM permanece aguardando o usuário abrir uma sessão local para inspeção visual direta.

## Mapa de revisão do GitHub — 24/09/2026

- PRs verificados como abertos e em rascunho: #6 pesquisa inicial (`main`); #10 pesquisa/arquitetura detalhada (base #6); #7 protótipo (`main`); #8 requisitos priorizados (`main`); #9 implementação local (base #7). A sequência proposta respeita as bases: revisar #6 antes de #10 e #7 antes de #9; #8 pode ser revisado em paralelo, contra `main`.
- O conteúdo de #10 é pesquisa documental, proposta conceitual e protocolo de prova sintética; não escolhe fornecedor nem stack. #9 valida comportamento local, sem backend nem dados operacionais. Casos reais anonimizados ainda são necessários para atores, variações e evidências da Mix7.
- Simulação sem alterar branches (`git merge-tree`): #6 + #7 combina sem conflito; #6 + #8 e #7 + #8 conflitam em `.ai/STATUS.md`. Preservar os dois registros e resolver esse arquivo explicitamente ao integrar o #8. Os cinco PRs retornam `statusCheckRollup` vazio; não há checagem automática reportada nesta consulta.
- Nenhum PR foi mesclado nem rebaseado nesta conferência. Antes de fechar as revisões, reconciliar a especificação #8 e a arquitetura #10 com a implementação #9; escolher stack somente após prova técnica controlada e decisão registrada.
- Validação desta atualização: `gh pr list --state open` confirmou cinco PRs abertos/rascunho; inspeção de `files` confirmou suas bases e arquivos tocados; `git merge-tree` verificou os conflitos indicados sem alterar branches; `git diff --check` passou.

## CI no GitHub — 24/09/2026

- Criado `.github/workflows/validate.yml` para executar testes e checks de sintaxe em pushes e pull requests das branches do projeto. Usa `ubuntu-24.04`, Node.js 22 e permissões somente de leitura; actions fixadas em SHA completo.
- Validação local: `node --test tests/workflow.test.js` passou 14/14; `node --check prototipo/workflow.js` e `node --check prototipo/app.js` passaram; `git diff --check` passou.
- GitHub Actions executou com sucesso no push da branch `implementation/primeira-jornada-local`, commit `804616f67a36160b2d2e44d3b15e3978ced4ab0c`, execução [36061087791](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36061087791). Atualizações documentais posteriores voltarão a disparar a mesma checagem.
- A branch ainda está em rascunho no PR #9; a regra agora também cobre os demais PRs depois que o workflow estiver na base deles ou em `main`.
- Cartão 18 do Trello atualizado e relido: inclui a execução aprovada do CI, o mapa/risco de integração e os links do repositório; permaneceu em “Em revisão” com etiquetas Gestão de equipe e Aprovações.

## Glossário e critério de conclusão — 24/09/2026

- Criado `docs/GLOSSARIO.md` com termos do fluxo-alvo e critério explícito: aprovação não encerra; registrar resultado e evidência, uma pessoa designada confere e então a demanda pode ser concluída. Os papéis e comprovantes específicos seguem pendentes do caso real.
- `README.md` aponta para o glossário. Código e documentação publicados em `ceef477ef856c5270351da1f0ee1279baf360f8f` e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`.
- Cartão 3 do Trello atualizado com o critério aprovado e mantido em contexto/decisões enquanto faltam fatos operacionais; PR #9 atualizado com o glossário e segue em rascunho.
- Validação: `git diff --check` passou; verificador percorreu 18 arquivos Markdown e não encontrou links locais quebrados.

## MVP-05: recuperar rascunho de arquivo substituído — 24/09/2026

- Ao substituir arquivo durante a execução, o evento preserva o nome e a chave local do arquivo anterior. O histórico oferece seu download quando o arquivo ainda existe no IndexedDB. Isso recupera rascunhos da mesma versão antes do envio; não cria versão aprovada nem recupera conteúdo removido do armazenamento do navegador.
- Cobertura e percurso documentados em `docs/PRIMEIRA-IMPLEMENTACAO.md` e `prototipo/README.md`.
- Validação: `node --test tests/workflow.test.js` passou 14/14; checks de sintaxe JS e `git diff --check` passaram. Chromium anexou dois PDFs sintéticos, baixou o arquivo anterior e conferiu seus bytes; histórico mostrou nomes antigo e atual. Em 1440×960 e 390×844, documento/painel sem overflow e console sem erros. Prints em `%LOCALAPPDATA%/Temp/mix7-draft-recovery-desktop.png` e `mix7-draft-recovery-mobile.png`.
- Limite: arquivos e histórico continuam no perfil local do navegador; não há armazenamento central, sincronização ou auditoria protegida.
- Código `d8b0a463a2e67ebdacf47a64a3eeab97173c169e` enviado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`; comportamento e validação registrados aqui. Cartão 18 do Trello e PR #9 atualizados com MVP-05, testes e limites; PR segue em rascunho.

## MVP-08: planejar tarefas a partir do feedback — 24/09/2026

- Durante ajustes, comentários atuais do cliente com identificador podem preencher um rascunho editável de tarefa. A equipe revisa o título, informa o responsável e confirma a criação; tarefa, comentário de origem, versão e evento de histórico ficam ligados. Comentários demonstrativos legados sem identificador não oferecem o atalho.
- `docs/PRIMEIRA-IMPLEMENTACAO.md` e `prototipo/README.md` descrevem a cobertura e o percurso de revisão. IA continua não implementada: não há decomposição automática, atribuição ou envio a provedor.
- Validação: `node --test tests/workflow.test.js` passou 14/14; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Chromium percorreu pedido de ajustes V03 → rascunho → atribuição manual → tarefa V04; lista e histórico referenciaram o comentário/V03. Em 1440×960 e 390×844, documento/drawer sem overflow; console sem erros. Prints em `%LOCALAPPDATA%/Temp/mix7-feedback-task-desktop.png` e `mix7-feedback-task-mobile.png`.
- Limite: vínculo e autoria seguem no perfil local do navegador. O recurso é manual e não substitui a futura consolidação cronológica assistida por IA.
- Código `f78657609d56155d558610e02cd1edb7b5e9fa91` enviado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`; validação registrada aqui. Cartão 18 do Trello e PR #9 atualizados com MVP-08, validação e limites; PR permanece em rascunho.

## Consistência do fluxo aprovado — 24/09/2026

- Corrigida a linguagem desatualizada em `.ai/DECISIONS.md` e `docs/FLUXO-PROPOSTO.md`: revisão interna e registro/conferência de evidência antes da conclusão são partes do fluxo-alvo aceito. O caso real documentará responsáveis, aplicação por serviço, variações e exceções; não será usado para reabrir essas decisões sem nova evidência ou decisão explícita.
- `git diff --check` passou. Busca direcionada em `.ai/`, `README.md` e `docs/` não encontrou mais afirmações de que a revisão interna, o fluxo-alvo ou a necessidade de evidência para concluir seguem por decidir.
- Commit `70dfccc5c8d218d084220132797908ed3c12bb48` enviado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`. Cartões 15 (especificação) e 18 (implementação) atualizados no Trello com o fluxo-alvo e as pendências factuais; as respostas de escrita retornaram as descrições atualizadas e os links dos documentos/commits.
- Permanece necessário um caso real anonimizado para confirmar atores, variações, exceções e evidências por serviço. PR #8 segue rascunho; esta atualização não altera nem integra outros PRs.

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
- As cinco perguntas sobre o fluxo foram respondidas como comportamento-alvo e aceitas pelo usuário; `docs/FLUXO-PROPOSTO.md` documenta a diretriz, enquanto um caso real ainda é necessário para identificar a rotina observada e suas variações.

## Estado atual e validação

- Os requisitos P0/P1/P2 são um baseline priorizado para revisão; o usuário aprovou o fluxo-alvo como diretriz do produto. Questões sobre a rotina observada, os papéis concretos, exceções e evidências por serviço permanecem em **Requisitos a validar**.
- A revisão interna antes do envio ao cliente e a conclusão após registrar e conferir a evidência fazem parte do fluxo-alvo aprovado. Não são mais decisões estruturais pendentes; o caso real identifica responsáveis e eventuais exceções operacionais.
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

- Incorporar um caso real anonimizado para confirmar atores, variações, exceções e evidências por serviço; preservar o fluxo-alvo aprovado salvo nova evidência ou decisão explícita.
- Revisar o protótipo local com usuário/equipe; esta fatia ainda requer revisão, e o PR de implementação permanece pendente.
- Prosseguir com prova de conceito controlada e detalhamento de arquitetura/modelo de dados, sem selecionar stack ou enviar dados reais antes dos portões documentados.
- Manter documentação, GitHub e Trello sincronizados após cada entrega.

## Atualização da jornada — alteração do briefing durante execução (2026-09-24)

- Implementado em `prototipo/`: uma alteração de briefing durante a execução exige motivo, preserva briefing anterior e novo, registra auditoria, pausa a execução e retorna a demanda ao planejamento. A retomada exige confirmação humana do plano; a confirmação fica registrada. Nesta fatia, alteração do briefing só está disponível durante execução.
- `node --test tests/workflow.test.js`: 8/8 passaram; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram.
- Chromium: fluxo com briefing fictício confirmou pausa, histórico/motivo, bloqueio até confirmação, retomada e persistência após recarga. Desktop e viewport 390×844 inspecionados; largura móvel sem overflow e console sem avisos/erros.
- Commit de código `74700857f5c3be0e47c17b4ce051c7c9d4a8e464` enviado e SHA local/remoto comparado na branch `implementation/primeira-jornada-local`. Cartão 18 do Trello atualizado e resposta conferida com regra, validação, limites e links. PR #9 segue como rascunho.
- Uso limitado a dados sintéticos; permanece sem backend, login/permissões reais, sincronização ou publicação. Alteração de briefing após envio ao cliente e revisão operacional dependem de caso real anonimizado.
# Estado em 2026-09-24

## Captura mínima do briefing — MVP-01

- A nova demanda agora registra origem do pedido, canal/peça e critérios de aceite. Prazo e referências/links são opcionais nesta demonstração. O briefing não avança ao planejamento sem os três campos mínimos; demandas antigas podem ser completadas e a alteração entra no histórico antes da liberação.
- A regra dos campos mínimos é provisória para prototipagem: serviços diferentes podem exigir dados adicionais. Confirmar com caso real anonimizado da Mix7 antes de tratar como requisito operacional.
- Validação: `node --test tests/workflow.test.js` passou 9/9; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. No Chromium isolado, criei briefing com dados sintéticos, confirmei a exibição dos metadados, bloqueio de briefing antigo incompleto, registro de alteração no histórico, liberação para planejamento e persistência após recarga. Em 390×844, o formulário abriu em modal rolável; a aplicação mantém uma faixa inferior cortada horizontalmente por causa do quadro de colunas em tela estreita, comportamento preexistente fora da mudança do formulário. Console sem avisos/erros durante o percurso.
- Sem backend, login ou sincronização. Nenhuma informação real de cliente foi usada. PR #9 continua como rascunho.
- Próximo passo: validar os campos e papéis com um caso real anonimizado; depois ajustar requisitos e protótipo.

## Preparação da validação operacional — 2026-09-24

- Criada `docs/VALIDACAO-CASO-REAL.md`, uma ficha para mapear demanda anonimizada ponta a ponta, identificar papéis, gatilhos, sistemas, evidências, tempos e exceções, e comparar requisitos dos áudios sem misturar fatos da rotina com fluxo-alvo aprovado.
- README e `docs/REQUIREMENTS.md` apontam para a ficha. Os requisitos de avaliação agora registram explicitamente como pendência finalidade, transparência e tratamento de bloqueios/mudanças; nenhuma política de pontuação foi presumida. A ficha proíbe inserir credenciais ou material confidencial.
- Nenhum caso operacional foi preenchido nem validado; permanece necessária informação real anonimizada fornecida pela equipe Mix7. Não houve mudança no protótipo nem nas decisões vigentes.
- Validação documental e sincronização concluídas nos registros e commits logo abaixo.
- Validação documental: `git diff --check` passou; um verificador Node conferiu os links locais em README e nos três documentos relacionados; conferi a ficha completa para garantir que os campos estão em branco, sem fatos inventados ou dados reais.
- Commit `d2f899e8385d6ae3034210681ce644b1a7f80f6d` publicado na branch `implementation/primeira-jornada-local`; SHA local e remoto conferidos iguais. Cartão 4 atualizado e relido em `Requisitos a validar`, com fluxo-alvo corretamente descrito, critério de aceite e link para a ficha.
- PR #9 teve a descrição atualizada para refletir a captura mínima de briefing, seus limites provisórios, validação 9/9, teste no Chromium em 390×844 e o vínculo com os cartões 4 e 18. `gh pr view 9` confirmou que continua aberto como rascunho; o cartão 18 foi atualizado e a resposta de escrita confirmou a descrição revisada.

## MVP-02: visão por profissional e auditoria P0 — 2026-09-24

- O quadro/lista ganhou seletor de profissional derivado das tarefas, e filtra demandas com tarefas pendentes da rodada atual dessa pessoa. O filtro funciona junto da busca, atualiza contagens por etapa e deixa explícito que os indicadores superiores são gerais e que a visão não impõe permissões. Tarefas concluídas e rodadas antigas não mantêm a demanda na lista pessoal.
- `docs/PRIMEIRA-IMPLEMENTACAO.md` agora compara MVP-01…MVP-12 com a fatia executável: parcial local, ausente e limites específicos. Essa cobertura deixa visíveis necessidades ainda não implementadas, sem declarar a primeira jornada completa. `docs/PRODUCT.md` e o roteiro de revisão do protótipo refletem a visão pessoal.
- Validação automática: `node --test tests/workflow.test.js` passou 10/10; `node --check prototipo/workflow.js`, `node --check prototipo/app.js`, `git diff --check` e verificação de links locais nos documentos alterados passaram.
- Chromium isolado com dados fictícios: uma demanda atribuída apareceu na visão da pessoa; busca e lista mantiveram a seleção; ao concluir sua única tarefa, a demanda saiu da visão pessoal, mas permaneceu no quadro geral. Em viewport 390×844, texto do filtro e quadro inspecionados; largura do documento correspondeu à largura útil do viewport e console não registrou avisos/erros. O servidor de teste registrou o 404 esperado da solicitação automática de `/favicon.ico`; não afetou a aplicação.
- Limite: filtro é local e organizacional, sem identidade autenticada nem controle de acesso. O caso real da Mix7 ainda precisa validar papéis e visão individual antes de produção. Sem backend ou dados reais.
- GitHub/Trello: commit `c9a1695e1b410210e4a890c5b91e03aecbae4d26` publicado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`. Cartões 15 e 18 atualizados e relidos com o MVP-02, cobertura P0 e links; PR #9 atualizado, confirmado aberto e em rascunho. A memória agora registra a sincronização concluída.

## MVP-03: dependências e impedimentos em tarefas — 2026-09-24

- Tarefas podem depender de várias tarefas da mesma rodada; a interface sinaliza o que ainda falta e impede conclusão fora de ordem. Para reabrir uma tarefa prévia, primeiro é necessário reabrir as tarefas que dependem dela.
- Durante a execução, pode-se registrar, atualizar ou remover impedimento com motivo. O impedimento pausa a conclusão da tarefa e cada mudança, motivo e relação entre tarefas entra no histórico local.
- Compatibilidade: tarefas existentes no armazenamento `schemaVersion: 1` recebem defaults para dependências/impedimento ao carregar; nenhum upgrade de versão do armazenamento necessário nesta fatia.
- Validação: `node --test tests/workflow.test.js` passou 12/12; `node --check prototipo/workflow.js`, `node --check prototipo/app.js`, `git diff --check` e links locais passaram. Chromium percorreu criação, dependência, bloqueio, motivo, remoção, desbloqueio, reabertura ordenada e histórico. Viewport 390×844 inspecionado, painel/documento sem overflow, console sem erros/avisos.
- Código e docs em `a59ba5de49e7cf710ddbde1d41e3b20c853e9a5c`, publicado na branch `implementation/primeira-jornada-local`; SHA local/remoto idêntico. Cartões 8 e 18 foram atualizados e relidos no Trello; PR #9 teve descrição atualizada e continua em rascunho.
- Limite: isso demonstra comportamento local; não há identidade, notificação, coordenação multiusuário ou medição de tempo. A rotina e os papéis da Mix7 continuam sujeitos a validação com caso anonimizado.

## MVP-10: resultado pós-aprovação — 2026-09-24

- O registro final diferencia material entregue ao cliente, publicação agendada e material publicado. Tipo e evidência são obrigatórios antes de concluir; o tipo aparece na demanda concluída e no histórico. Nenhuma ação é executada em serviço externo.
- Cobertura P0/limites e roteiro do protótipo atualizados. Validação: `node --test tests/workflow.test.js` passou 13/13; `node --check prototipo/workflow.js`, `node --check prototipo/app.js`, `git diff --check` e links locais passaram. Chromium cobriu os três tipos e bloqueio até evidência; inspeção desktop e 390×844 sem overflow/erros de console.
- Commit `9e427e9f9ac004bd7d897348fe951dc184924814` publicado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`. Cartão 18 do Trello atualizado com MVP-10, teste e limites; PR #9 atualizado, aberto e em rascunho.
- Limite: tipo e evidência ficam no armazenamento local. Datas, canais e metadados por serviço seguem pendentes de validação da rotina real.

## MVP-11: histórico com contexto recuperável — 2026-09-24

- Eventos locais agora ligam transições a versão/arquivo, substituição de nome de arquivo, comentário/motivo, tarefa ou resultado pós-aprovação quando esses dados se aplicam. A interface mostra resumos legíveis e limita o trecho visual de textos longos sem remover o conteúdo canônico de comentários/evidências. Registros antigos sem número de versão continuam legíveis como “Versão”, sem inferência.
- Validação: 13/13 testes; checks JS/diff e links locais passaram. Chromium percorreu pedido de ajuste, nova versão V04 e aprovação interna; histórico exibiu referência à V03, motivo, V04 e arquivo novo. Mobile 390×844 sem overflow, resumo de comentário extenso e console sem avisos/erros.
- Commit `7c585d92e747d933c7101311ef360e1cd9aa9c72` publicado e SHA local/remoto confirmado na branch `implementation/primeira-jornada-local`. Cartão 18 do Trello atualizado com MVP-11 e validação; PR #9 atualizado, aberto e em rascunho.
- Limite: o histórico permanece editável no perfil do navegador, sem identidade autenticada, armazenamento central, retenção ou assinatura de auditoria.

## Simulações manuais de demandas — 24/09/2026

- Navegador com armazenamento local isolado e dados fictícios: briefing incompleto bloqueado; briefing mínimo liberado até o planejamento; execução exige tarefa atribuída; dependência e impedimento bloqueiam conclusão; ausência de arquivo impede revisão interna.
- Pedido de ajuste fictício na V03 permaneceu ligado à versão; tarefa foi criada manualmente a partir do comentário e, após atribuição e conclusão, liberou nova versão. Demandas de vídeo e newsletter foram aceitas com combinações de prazo/referência opcionais.
- Aprovação direta levou à etapa de entrega/publicação, sem concluir a demanda. A conclusão exigiu selecionar entrega, agendamento ou publicação e preencher evidência fictícia; o histórico registrou resultado e evidência.
- Não houve upload nem uso de dados reais. A jornada nova até mídia, revisão e aprovação do cliente não foi completada; estes cenários exercitam o protótipo, não confirmam a rotina real.
- Validação: `node --test tests/workflow.test.js` 14/14; checks JS e `git diff --check` passaram; CI passou na execução 36063126012. Commit `e5de980e1d6e3e701a87c2357e6be14872539b98`, SHA local/remoto igual. Cartão 18 do Trello relido em “Em revisão” com descrição atualizada; PR #9 continua aberto como rascunho.

## Trello simplificado e rastreável — 24/09/2026

- A pedido do usuário, o quadro foi ajustado para ser compreensível sem a conversa: descrições curtas em 22 cartões, com objetivo, estado/resultado, aceite e link para detalhes no GitHub. `docs/TRELLO.md` agora define a regra de escrita simples e a divisão de entregas em cartões próprios; etiquetas representam apenas áreas pertinentes.
- O cartão 18 virou índice da primeira entrega e foi movido para “Em andamento”. Criados cartões 19–21 para três grupos de simulação, com checklists de 11 passos conferidos no total; os três estão em “Concluído”. Criado cartão 22 para anexos no briefing, em “Em andamento”, com checklist de 15 passos: 9 concluídos, 6 pendentes.
- Etiquetas conferidas: todos os 22 cartões têm ao menos uma etiqueta de área apropriada. Leitura do quadro confirmou 3 cartões de contexto, 10 requisitos, 4 em andamento, 2 em revisão e 3 concluídos. Nenhum cartão original estava fechado ou marcado como concluído antes dessa reorganização.
- Validação: leitura posterior confirmou nomes, descrições (máximo 707 caracteres), listas e etiquetas; as simulações 19–21 aparecem como concluídas e a implementação 22 como aberta/em andamento. Próximo: terminar MVP-01 e publicar a atualização desta rodada no GitHub e nos cartões relacionados.

## Cartões separados por entrega — 24/09/2026

- Após o usuário apontar que uma entrega inteira não deve ficar resumida em um cartão, o cartão 18 foi reescrito em linguagem simples como mapa da primeira entrega, com links para os cartões 19–22. Cada cartão menor deve manter seus próprios passos e resultado.
- O cartão 22 já contém a checklist atual do MVP-01. Uma checklist duplicada no cartão 18 foi renomeada para “Cópia antiga do MVP-01 — cartão 22 é o atual”; seu conteúdo foi preservado. A remoção dessa cópia via interface do Trello requer confirmação do usuário, conforme a política de ações de exclusão da skill computer-use; nenhum dado foi apagado.
- `docs/TRELLO.md` atualizado para estabelecer que cartões principais só funcionam como índice e que checklists não devem ser duplicadas. Trello lido após atualização; cartão 18 e a cópia antiga retornaram com a identificação esperada.
- Próximo: obter autorização antes de remover a checklist antiga; concluir e sincronizar separadamente a alteração de anexos que já estava em andamento.

## MVP-01: anexos no briefing — validação do navegador — 24/09/2026

- Testes de regra passaram 15/15; `node --check` passou em `prototipo/workflow.js` e `prototipo/app.js`; `git diff --check` passou.
- No servidor isolado `127.0.0.1:4191`, criei uma demanda sintética com PDF de teste. O painel mostrou o arquivo e seu tipo, o histórico listou a referência, os dados continuaram após recarregar e o download teve SHA-256 idêntico ao fixture. Nenhum dado real foi usado. O campo e o modal foram inspecionados visualmente em desktop.
- Commit de implementação `0bc2c0b4651164e220e5ddcf0252c29c1e01fc53` publicado; SHA local e remoto conferem. CI do push [36069099172](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36069099172) e CI do PR [36069104400](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36069104400) passaram. PR #9 atualizado e continua aberto como rascunho: https://github.com/diegohenrich/plataforma-processos-mix7/pull/9.
- Trello: cartão 22 atualizado e relido; checklist 15/15, marcado como concluído e movido para Concluído. A descrição registra aceite, validação móvel, limite local e links. O cartão 18 continua como mapa e aponta para o cartão 22.
- Validação móvel concluída em Chromium 390×844: formulário de demanda e drawer couberam na largura útil; campo de arquivo, botões, dica e rolagem inspecionados. Um PDF sintético foi escolhido, salvo localmente, permaneceu após recarga e apareceu no briefing e no histórico com nome e tipo. A largura do documento e drawer foi 390 px; console sem erros ou avisos. O botão de download apareceu; não repeti a inspeção de bytes, já validada na rodada de anexos anterior.

## Navegação da primeira versão: atalhos e demanda minimizada — 24/09/2026

- Tornadas acionáveis as áreas da primeira versão: visão geral/demandas, aprovações, equipe, clientes, calendário, conhecimento e acessos; busca, filtros combinados, filtro por coluna, pendências e perfil; criação de demanda sempre no briefing; exportação CSV/JSON informativa. As telas sem especificação de contas ou permissões explicam os limites atuais em vez de simular capacidade inexistente.
- Ao minimizar uma demanda, seu atalho com título, cliente e etapa permanece em bandeja fixa; reabre o painel correto, e o atalho sobrevive a recarga. Remover o atalho não apaga a demanda. Só o comportamento foi reaproveitado; nenhuma aparência de outro projeto foi copiada. Cores/tipografia vêm da inspeção do tema claro do CRM-MIX7-RENEW e mantêm branco e azul-claro.
- Validação local: `node --test tests/workflow.test.js` passou 15/15; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Chromium percorreu navegação, filtro etapa+cliente, nova demanda sintética, minimização/reabertura após recarga, exportação (retorno de sucesso no app) e telas estreitas 390×844. Documento sem overflow horizontal; bandeja visível no celular. Console sem avisos/erros. A inspeção de exportação confirmou o retorno da interface, não os bytes do arquivo.
- Limite: dados e atalhos permanecem no perfil local do navegador; sem autenticação, sincronização, gestão real de contas ou operação em serviços externos. Commit `29ef514ff93f777c1258e66695aebbcb429b0ed3` enviado; SHA local/remoto conferido. CI do push `36079370527` e do PR `36079373626` passou. PR #9 segue aberto em rascunho. Cartão 23 [Navegação da primeira versão: atalhos e demanda minimizada](https://trello.com/c/S9ZKrAwB/23-mvp-12-navega%C3%A7%C3%A3o-e-demanda-minimizada) criado em Em revisão, com etiquetas Aprovações e Gestão de equipe e checklist 5/5. O nome evita colisão com o requisito MVP-12 da especificação. A descrição contém objetivo, resultado, aceite, limite local, validação, guia, commits e PR; leitura posterior confirmou os dados.


## Referência visual alinhada ao CRM Mix7 — 24/09/2026

- CRM-MIX7-RENEW executado em ambiente local sem modificar arquivos do CRM. Chromium renderizou `/login` a 1270×713; `/administrador/dashboard` redirecionou para `/login`, portanto o dashboard autenticado não foi visualmente inspecionado. Nenhuma credencial foi tentada.
- Referência complementada pela leitura de `public/css/dashboard-light.css`: corpo 16 px, títulos 32–46 px, navegação 15 px, cartões com raio 24 px e botões com altura mínima 44 px; cores e limite da inspeção estão em `docs/REFERENCIA-VISUAL.md`.
- `prototipo/styles.css` ajustado para essa escala e acabamento, com navegação/superfícies brancas e acentos azul-claro como pediu o usuário. Não foram copiados assets ou a composição da tela de login. README, guia do protótipo, `.ai/CONTEXT.md` e `.ai/DECISIONS.md` atualizados.
- Chromium inspecionou demandas e formulário “Nova demanda” em 1270×713 e 390×844. No celular, nav compacto em ícones; documento 375 px numa viewport de 390 px; modal 337 px de largura (x=19–356), com rolagem interna e campos contidos.
- Validação automática: `node --test tests/workflow.test.js` passou 15/15; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Chromium inspecionou página, modal e drawer em 1270×713 e 390×844; viewport móvel de 390 px, documento 375 px, modal 337 px com rolagem interna e navegação sem rótulos sobrepostos.
- GitHub: commit `2796fd82bb38ed31078ab34fd1faa121fc26646f` enviado e SHA local/remoto conferido. CI do push [36082468104](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36082468104) e do PR [36082472320](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36082472320) passaram; PR #9 segue aberto como rascunho e sua descrição foi atualizada com a referência e seu limite de autenticação.
- Trello: cartão 23 atualizado e relido; objetivo, aceite, referência, verificações, limites e links conferidos. Checklist 5/5; continua em `Em revisão`.
- A conferência visual autenticada do dashboard CRM permanece pendente de sessão de demonstração autorizada; a rota local redireciona para login. A referência visual e as medidas conferidas estão documentadas em `docs/REFERENCIA-VISUAL.md`.

## Revisão da proximidade visual com o CRM — 24/09/2026

- Em resposta ao usuário, o CRM local foi executado novamente. A tela `/login` foi renderizada; `/administrador/dashboard` redireciona para autenticação. O CSS confirma lateral escura em degradê `#243943` → `#10252f`, largura 252/230 px, navegação de 15 px e item ativo `#e8f3f8`. A sessão ainda está aguardando login manual para comparar o dashboard renderizado.
- `prototipo/styles.css` agora aplica a lateral escura do CRM com rótulos legíveis, estado ativo claro, contraste no contador, largura responsiva de 252/230 px e raios maiores em cartões. O conteúdo de trabalho e as superfícies continuam claros e usam os azuis confirmados. Não foram copiados arquivos do CRM.
- Chromium renderizou a plataforma em 1270 × 720 após a alteração. Lateral, navegação, estado ativo, cartões de resumo e quadro apareceram com contraste e sem sobreposição visível. A revisão desta mudança em viewport móvel permanece pendente; os breakpoints documentados no CSS mantêm a navegação compacta.
- Validação automática local: `node --test tests/workflow.test.js` passou 15/15; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Commit `5734a95f8b2d7d5f487d0450bc1290b33c8af826` está no GitHub e seu SHA confere com a branch local. Os checks do push e do PR #9 passaram ([36087005472](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36087005472), [36087009696](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36087009696)); PR #9 segue aberto em rascunho.
- Trello: cartão 25 [Alinhar visual da plataforma ao CRM Mix7](https://trello.com/c/7OQNiG8Y/25-alinhar-visual-da-plataforma-ao-crm-mix7) continua em `Em andamento`, com descrição atualizada, etiqueta de Aprovações e Gestão de equipe, commit anexado e checklist em 25% (1/4). A etapa de aplicar diferenças confirmadas foi concluída; dashboard autenticado, comparação completa e conferência móvel seguem pendentes.

## CRM executado para nova comparação visual — 25/09/2026

- O servidor local do `CRM-MIX7-RENEW` respondeu na porta 8198. Chromium mostrou `/login`; `/administrador/dashboard` continuou redirecionando para login. A tela autenticada não foi conferida e nenhuma credencial foi inserida.
- Reconfirmado no `public/css/dashboard-light.css` o padrão já registrado: menu lateral em degradê escuro, superfícies claras, navegação de 15 px, recuos responsivos e azul-petróleo/azul-claro. O login renderizado não é substituto para a composição do dashboard.
- A aba `Entrar | CRM Mix7` permanece aberta. Foi solicitado ao usuário que entre com a própria senha e deixe o painel aberto; a comparação detalhada e qualquer novo ajuste de layout aguardam essa sessão.

## Arquivos por arraste no briefing — 25/09/2026

- `prototipo/` agora oferece uma área indicada para arraste e mantém o seletor. Arquivos aceitos são imagem, vídeo ou PDF até 15 MB cada. A validação aceita `FileList`; lote recusado preserva a lista anterior, e o estado explica recusas ou indisponibilidade do recurso de arraste.
- Chromium isolado em `localhost:4193` usou PNG e PDF sintéticos: ambos apareceram pelo nome, foram salvos em uma demanda fictícia e continuaram visíveis no painel após recarga. `.txt` e PDF de 15 MB + 1 byte foram recusados. A área foi inspecionada em 390×844. Não houve teste físico de soltar arquivo do sistema operacional; essa confirmação permanece pendente.
- Validação automática: `node --test tests/workflow.test.js` passou 16/16; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Corrigido durante a inspeção o uso de `FileList` sem convertê-la em array; a seleção no navegador foi repetida e passou.
- Commit `5c86c808696c7cf91e0f1e5ef1f7d4e630782e79` publicado e SHA local/remoto conferido. CI do push [36089175613](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36089175613) e do PR #9 [36089179546](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36089179546) passaram. O PR #9 foi atualizado e continua aberto em rascunho.
- Cartão 26 [Permitir arrastar arquivos para o briefing](https://trello.com/c/VEQAdHRk/26-permitir-arrastar-arquivos-para-o-briefing) atualizado: descrição registra validação, links de commit/PR e pendência; checklist está em 4/5, com teste real de arraste explicitamente aberto.

## Cronômetro local por tarefa — 25/09/2026

- Implementados botões iniciar/parar em tarefas da rodada em execução. Uma sessão ativa por vez entre as demandas carregadas na tela; início persiste após recarga, parada soma segundos às sessões da tarefa e grava horários/duração/total no histórico. Tarefa concluída, bloqueada ou impedida não pode iniciar; não se conclui nem se bloqueia tarefa enquanto seu timer está ativo.
- Registro é local, não identifica pessoa autenticada, não coordena abas/usuários diferentes e não deve alimentar avaliação. Cronômetro não equivale a capacidade, calendário ou Gantt.
- `node --test tests/workflow.test.js` passou 20/20; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Chromium em localhost isolado criou demanda sintética, planejou tarefa, iniciou timer, recarregou a página, confirmou contagem retomada, parou duas sessões e conferiu o total de 23 segundos no histórico. Em 390×844, demanda/drawer ocuparam 390 px sem overflow horizontal; controles permaneceram acessíveis; console sem erros/avisos. Nenhum dado real usado.
- Documentação da primeira implementação, roteiro local e rastreabilidade dos áudios atualizados para identificar a cobertura parcial do requisito. Implementação publicada no commit `99a5aa2d482dab357d3c6378ee0126444cb56f2e`; a atualização do estado foi publicada no `0ec483580e8cb7f7af79e86cccfb70ff886c2c6d`. SHA remoto confere; CI dos pushes [36090504124](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36090504124) e [36090569061](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36090569061), e do PR [36090572030](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36090572030), passaram. PR #9 continua aberto como rascunho. Cartão 27 foi relido e atualizado com os commits e as verificações; checklist 5/6, resta coordenação entre abas e usuários após autenticação/serviço central.

## Tipos de usuário e funções por demanda — 25/09/2026

- A matriz de requisitos agora separa quatro tipos citados nas fontes (direção, gestor/gerente, profissional da equipe, aprovador do cliente) das funções atribuídas em cada demanda (briefing/conta, gestão, execução, revisão interna, entrega/publicação). A pessoa da direção exerce avaliação peso 2 e o gestor peso 1; cargo e permissões não estão definidos. Nenhuma lista de nomes/e-mails foi fornecida.
- Cartão 5 do Trello atualizado com a distinção, perguntas de autenticação e acesso, dependência do caso real #4 e checklist (1/5: a distinção conta/função foi documentada; matriz, fronteira de cliente, administração/login e validação permanecem pendentes).
- Contas reais e permissões ainda não foram implementadas. Próximo passo: resolver decisões de identidade/isolamento na pesquisa e arquitetura, validar matriz com caso anonimizado e obter dados de provisionamento antes de criar contas operacionais. Nenhuma decisão de fornecedor foi inferida.

## Reinspeção visual do CRM — 25/09/2026

- Iniciado novamente o servidor local do `CRM-MIX7-RENEW` em `127.0.0.1:8198` e conferida a tela de login renderizada. A rota `/administrador/dashboard` ainda redireciona para `/login`; nenhuma senha foi inserida e não houve acesso autenticado.
- Comparação de código confirmou uma diferença de escala na plataforma em larguras até 1399 px (navegação 14 px, títulos de coluna 13 px, títulos de cartão 14 px, descrição 12 px) ante o CRM (corpo 16 px, navegação 15 px, títulos do painel 32–46 px e cartões com raio de 24 px). Isso é evidência para revisar a escala, mas não basta para inferir a composição final do dashboard.
- Nenhum CSS de produto foi alterado nesta rodada. A comparação completa de cabeçalho, composição e cartões depende do dashboard aberto pelo usuário; após a sessão, aplicar os ajustes comprovados e validar desktop/móvel. Evidência e limites registrados em `docs/REFERENCIA-VISUAL.md`.
## Fechamento de briefing incompleto — 2026-09-25

- Corrigido um defeito no formulário de nova demanda: os controles “Fechar” e “Cancelar” agora fecham o diálogo sem acionar validação ou salvamento. O rascunho incompleto não cria uma demanda.
- Navegador Chromium em origem de teste isolada `127.0.0.1:4197`: abrir formulário vazio, clicar em “Fechar” e depois repetir com “Cancelar”; em ambos os casos o diálogo fechou e o quadro permaneceu com 8 demandas. Nenhum dado real foi usado.
- Validação concluída: `node --test tests/workflow.test.js` passou 20/20; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Chromium isolado confirmou que Fechar e Cancelar fecham o briefing vazio sem alterar as 8 demandas. Commit `588230cd93c805270210e1298a382c5826d3a283` está na branch local e remota; CI do push [36094542883](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36094542883) e do PR [36094546009](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36094546009) passou. PR #9 e cartão 18 do Trello foram atualizados.

## Verificação de navegação da demonstração — 2026-09-25

- Chromium na aplicação local (perfil já existente, sem editar demandas): quadro ↔ lista; páginas Aprovações, Equipe, Clientes, Calendário, Conhecimento e Acessos abriram e exibiram o conteúdo correspondente. Aprovações filtrou a demanda em espera do cliente; Clientes agrupou 8 demandas em 3 nomes fictícios; Calendário mostrou os prazos registrados; Equipe explicou que responsáveis são texto livre e mostrou estado vazio; Acessos expôs claramente a ausência de login/permissões.
- Retornados ao quadro e à visualização de quadro; dados não foram alterados. É uma verificação manual de navegação e apresentação acessível, não uma auditoria de todos os controles ou de segurança.
- Sem mudança de código. Registrar este teste no cartão 18 do Trello e no PR #9. Próxima lacuna a priorizar: melhorar completude funcional do gerenciamento de tarefas e seguir teste de cada fluxo antes de alegar cobertura total.

## Exportação CSV segura para planilhas — 2026-09-25

- A inspeção do exportador revelou que campos livres eram apenas colocados entre aspas; planilhas ainda poderiam interpretar valores iniciados por `=`, `+`, `-` ou `@` como fórmulas. Adicionada neutralização na serialização CSV: tabulador antes de prefixos ASCII e de largura completa, incluindo entradas com espaço/tab/CR/LF inicial. Cada valor continua entre aspas e aspas internas são duplicadas; os dados salvos no app e o JSON não mudam.
- A mitigação tabular é específica para CSV destinado a planilhas e altera a primeira célula em bruto; não usar este CSV como formato de importação sem considerar o tabulador. OWASP observa que não há uma estratégia universal e recomenda validar no software de planilha em uso: https://community.owasp.org/attacks/CSV_Injection . A validação interativa no Excel/Calc ainda não foi feita.
- Teste unitário cobre `=`, `+`, `-`, `@`, prefixos Unicode de largura completa, tab/CR/LF, espaços iniciais, aspas e vírgula. `node --test tests/workflow.test.js` passou 21/21; ambos `node --check` e `git diff --check` passaram. Código no commit `40796366df4e9f9e94a9ba57e924a8b73732954b`, SHA local/remoto conferido. CI push [36095197468](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36095197468) e PR [36095200552](https://github.com/diegohenrich/plataforma-processos-mix7/actions/runs/36095200552) passaram. Descrição do PR #9 e comentário do cartão 23 foram atualizados e relidos.
