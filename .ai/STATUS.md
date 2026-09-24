# Estado em 2026-09-24

## Mapa de revisão do GitHub — 24/09/2026

- PRs verificados como abertos e em rascunho: #6 pesquisa inicial (`main`); #10 pesquisa/arquitetura detalhada (base #6); #7 protótipo (`main`); #8 requisitos priorizados (`main`); #9 implementação local (base #7). A sequência proposta respeita as bases: revisar #6 antes de #10 e #7 antes de #9; #8 pode ser revisado em paralelo, contra `main`.
- O conteúdo de #10 é pesquisa documental, proposta conceitual e protocolo de prova sintética; não escolhe fornecedor nem stack. #9 valida comportamento local, sem backend nem dados operacionais. Casos reais anonimizados ainda são necessários para atores, variações e evidências da Mix7.
- Nenhum PR foi mesclado nem rebaseado nesta conferência. Antes de fechar as revisões, reconciliar a especificação #8 e a arquitetura #10 com a implementação #9; escolher stack somente após prova técnica controlada e decisão registrada.
- Validação desta atualização: `gh pr list --state open` confirmou os cinco PRs e suas bases; `git diff --check` passou. Registro publicado como `dcb0aee79c69d139e4f0575398642d8863e98b12`, SHA remoto conferido; cartão 18 do Trello atualizado e resposta confirmou descrição, lista e etiquetas. PR #9 continua em rascunho.

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
