# Estado em 2026-09-24

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

- 2026-09-24 — Pesquisa inicial de produtos oficiais comparou Planable, Frame.io, Filestage, Wekan e Plane Community. Recomendação registrada: núcleo próprio de processos com módulos configuráveis; testar soluções de gestão e revisão criativa como componentes distintos antes de definir stack. Licenças identificadas para Wekan (MIT) e Plane Community (AGPL-3.0); custos/limites comerciais ainda precisam de validação.
- 2026-09-24 — Proposta de arquitetura de produto documentada sem fixar tecnologias. Inclui núcleo de demandas, tarefas, motor configurável de etapas/decisões, módulo de criativos com versões/comentários, trilha de auditoria, integrações a avaliar e IA sujeita a confirmação humana.
- Esta sessão solicitou um exemplo real para validar briefing, papéis, revisão e encerramento; a resposta ainda está pendente. Até lá, o fluxo é somente desenho-alvo.
- Sincronização desta etapa: [PR #6](https://github.com/diegohenrich/plataforma-processos-mix7/pull/6), commit `15f122bde5d5ea10884148448af444392f947280`; branch publicada e SHA local/remoto conferidos como iguais. `git diff --check` e verificação dos links locais de Markdown passaram. PR permanece aberto para revisão; ainda não foi integrado à `main`.

- 2026-09-24 — Complementada a pesquisa comparativa com releases, licenças, páginas de preço e avisos de segurança publicados nos repositórios e sites oficiais. Wekan v11.95 foi publicado no dia da consulta; Plane v1.4.2 em 23/08/2026. Os repositórios exibem avisos recentes com versões corrigidas, portanto o protocolo da prova exige verificar versão candidata, avisos, permissões, configuração e restauração de backup, sem concluir que releases atuais sejam vulneráveis ou certificadas. Foram registrados limites comerciais disponíveis de Planable, Frame.io e Filestage e a divergência de preço encontrada nas páginas oficiais de Planable para confirmar com fornecedor.
- Validação documental desta atualização: `git diff --check`; links oficiais incluídos na tabela e nas fontes. Não houve teste de produto, cotação, auditoria independente nem execução auto-hospedada.

## Revalidação da pesquisa de soluções — 2026-09-25

- `docs/PESQUISA-SOLUCOES.md` foi complementado com consulta às páginas oficiais atuais: preço do Planable diverge entre a página pública e o centro de ajuda; Frame.io cobra por membro e seu teste exige cancelamento antes da conversão; Filestage oferece plano gratuito limitado a 1 projeto/5 arquivos mensais e seus planos pagos publicam US$ 199/329 por mês; Wekan v11.99 é a release de 24/09 e lista avisos recentes de autorização; Plane Community segue em v1.4.2/AGPL-3.0 com avisos de segurança que precisam ser verificados contra a versão candidata.
- O texto não recomenda compra nem uso de dados reais. Mantém como próximos critérios cotação de Planable, teste sintético sem upgrade, confirmação de licença e validação de avisos/permissões/backup antes de qualquer execução auto-hospedada.
- Fontes primárias consultadas em 25/09: páginas oficiais Planable, Frame.io e Filestage; releases, avisos e licença nos repositórios oficiais Wekan e Plane. `git diff --check` e revisão manual da seção foram executados; links adicionados são URLs oficiais. Teste de produto, criação de contas e compra não foram realizados.
- Commit `e0e1841ddc72020cd385665e00d3cc56e40deac7` publicado na branch `research/comparativo-arquitetura-inicial`; SHA local e remoto conferem. `git diff --check` passou. `gh pr checks 6` não reportou verificações para esta branch documental; não houve CI, teste do produto, cotação ou auditoria.
- Cartão 14 do Trello atualizado com a síntese, limitações, commit e PR: https://trello.com/c/DT5BeB96/14-comparar-solu%C3%A7%C3%B5es-existentes-e-bases-open-source#comment-6ab6102afda8f42058614170. PR #6 permanece em revisão e a prova controlada ainda não foi executada.

## Próximo passo

- Incorporar um caso real de demanda da Mix7 e resolver fluxo, papéis, revisão interna e significado de conclusão.
- Preparar protótipo navegável do fluxo depois de validar os estados e papéis; então testar com equipe e cliente.
- Executar prova ponta a ponta das soluções candidatas, incluindo revisão de arquivo/vídeo, permissões, versões, exportação e integração.
- Manter documentação, GitHub e Trello sincronizados após cada entrega.
