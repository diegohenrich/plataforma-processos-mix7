# Estado em 2026-09-25

## Matriz de cobertura documental — 2026-09-25

- Criada `docs/MATRIZ-ADEQUACAO-MIX7.md` com requisitos dos áudios e da especificação, cobertura documental por candidato, evidência ausente e ensaios necessários. A classificação P0/P1 é explicitamente proposta com base na primeira jornada aprovada; não foi encontrada priorização item a item aprovada pela Mix7.
- A matriz distingue ferramenta de gestão, aprovação social e revisão de mídia. Segurança de cliente, histórico por versão, timecode, integração e recuperação seguem como testes obrigatórios; funções descritas por fornecedores não são tratadas como observadas.
- README, contexto e comparativo apontam para a matriz; o README descreve corretamente as prioridades como proposta ainda não validada. A matriz separa quatro categorias de participantes dos áudios de contas reais, que não podem ser provisionadas sem nomes/e-mails e permissões fornecidos pela Mix7. `git diff --check` passou e 20 links Markdown locais nos três documentos tocados resolvem. Revisão manual confirmou que estados documentados não são apresentados como testes. Não se aplicam testes de produto à comparação documental. O PR #10 está aberto como rascunho, sem conflitos (`CLEAN`) e sem checks reportados. Commit de conteúdo `1dfcf8b12b5d00e19785540e689f6a10f01a7d18` está publicado e SHA remoto conferido; o registro Trello do commit está pendente da confirmação necessária para publicar o comentário na interface.

## Complemento documental Plane e próximo entregável — 2026-09-25

- Fonte oficial atual confirma Community sob AGPL-3.0 e suporte a armazenamento S3 externo (`USE_MINIO=0`). Registrei que isso torna a substituição configurável, sem validar fornecedor, custo, região, backup/restauração ou operação. A síntese mantém Plane como candidato, não como produto escolhido, e separa avaliação de gestão de trabalho de revisão especializada.
- Próximo entregável de pesquisa: matriz de cobertura P0/P1 com evidência direta por candidato; depois, prova controlada de jornada, permissões, versões, exportação e recuperação, quando houver runtime e condições aprovadas.
- `docs/PESQUISA-SOLUCOES.md` atualizado com documentação oficial consultada em 25/09. `git diff --check` passou; revisão confirmou alteração apenas documental e que a recomendação não escolhe fornecedor. Não se aplicam testes de produto, pois nada foi instalado ou executado. Commit `f4c856cf8937647dd6a1772942d9ed211706f9ef` foi publicado e comparado com a branch remota; cartão Trello 14 ainda precisa receber o resultado desta tarefa.

## Atualização de manutenção do armazenamento do Plane — 2026-09-25

- Reabri o cartão 14 da pesquisa. A consulta direta ao Compose oficial do Plane v1.4.2 confirmou `minio/minio:latest`; a API Registry desafiou com Bearer e o token anônimo não concedeu escopo de leitura. Fonte oficial do [repositório MinIO](https://github.com/minio/minio): arquivado em 25/04/2026, read-only e não mantido. A documentação oficial do [Plane aceita armazenamento S3 externo](https://developers.plane.so/self-hosting/govern/database-and-storage), com `USE_MINIO=0`.
- Atualizei pesquisa e roteiro para tratar o ciclo de manutenção como risco; fixar digest não resolve arquivamento. Um provedor S3 compatível fica como opção a comparar — região, acesso privado, CORS, backup/restauração, custo e exportação ainda sem verificação. Nenhum serviço ou imagem foi iniciado, nenhuma conta ou arquivo foi enviado, e nenhuma alternativa foi escolhida.
- Validação: confirmei diretamente o Compose tag `v1.4.2` do Plane, `git diff --check` passou e os links Markdown locais nos dois documentos resolvem. Revisão documental preserva como desconhecidos provedor, custo e configuração; não se aplicam testes de produto. Runtime de containers continua ausente conforme a pré-verificação anterior. Alteração vinculada ao cartão 14 e PR #10; commit, checks do commit e comentário final no Trello ainda pendentes.

## Referências OCI da prova documental — 2026-09-25

- Atualizados `docs/PESQUISA-SOLUCOES.md` e `docs/PROVA-DE-CONCEITO.md`: Wekan v12.01 e 11 metadados de índices OCI para Wekan, Plane, OpenProject e dependências fixadas do Compose do Plane. A dependência `minio/minio:latest` não retornou metadados anônimos (401); runtime indisponível. Nenhuma imagem foi baixada ou executada, e nenhum fornecedor foi escolhido.
- `git diff --check` e links relativos dos documentos passaram. Commit `ed3e4ba01d6317bae038d331ffedb0d0348d7c82` publicado e SHA local/remoto idêntico. PR #10 permanece aberto como rascunho, sem checks automáticos reportados; descrição atualizada. Cartão 14 registra o resultado; comentário da sincronização deste commit pendente.
- Próximo passo: resolver runtime e MinIO, validar o conjunto exato de imagens e avisos de segurança e, só então, conduzir prova com dados sintéticos. Caso operacional real da Mix7 também segue pendente.

## Pré-verificação da prova auto-hospedada — 2026-09-25

- Consultei `docker`, `podman` e `nerdctl`; nenhum comando está disponível no `PATH`. Verifiquei também os caminhos comuns de instalação do Docker Desktop e Podman no Windows; os executáveis não foram encontrados.
- Não instalei runtime, não baixei imagens e não subi serviços. Não há neste ambiente atual evidência de release instalada, digest, configuração ou ensaio de isolamento, exportação e restauração.
- `docs/PROVA-DE-CONCEITO.md` agora registra o resultado e o pré-requisito para retomar a prova. Portões operacionais/segurança e validação de caso real permanecem vigentes; nenhum produto foi escolhido. `git diff --check` passou; a alteração não acrescenta novos links relativos nem código executável. Commit `96893a56792d8d2ce0c52040fa8e2436849a801e` publicado e SHA local/remoto idêntico; PR #10 continua rascunho e o GitHub não reportou checks automáticos para a branch documental. O cartão 14 do Trello recebeu o resultado; continua Em revisão com checklist 2/3.

## Mapeamento documental de avisos de segurança — 2026-09-25

- Conferidos os intervalos afetados e versões corrigidas declarados nos avisos oficiais selecionados para Wekan v11.99, Plane Community v1.4.2 e OpenProject Community v17.8.0. As releases candidatas são posteriores às correções declaradas para os grupos mapeados. O aviso LDAP do OpenProject limita-se a 17.4.0 e não declara versão corrigida; a configuração correspondente requer teste explícito.
- Pesquisa e roteiro de prova agora mostram os GHSAs, faixas e limites da conclusão. Esta etapa não verificou digest/imagem, dependências integrais, ausência de regressão, segurança operacional ou custo. Nenhum serviço foi instalado nem conta ou arquivo enviado.
- Fontes primárias consultadas em 25/09/2026. `git diff --check` passou; 42 links Markdown em `PESQUISA-SOLUCOES.md` e 31 em `PROVA-DE-CONCEITO.md` foram verificados, sem destinos locais quebrados. Nenhum teste de produto se aplica à atualização documental. Depois da publicação, registrar commit e referência nos cartões Trello 14 (pesquisa) e 16 (arquitetura); manter a prova de produto pendente de caso real, responsável e ambiente de teste.

## Levantamento de identidade e acesso — 2026-09-25

- Criado `docs/IDENTIDADE-E-ACESSOS.md` com evidências dos quatro participantes citados nos áudios, distinção entre identidade, organização, papel e função por demanda, comparação documental entre autenticação no framework, provedor de identidade separado e Auth/RLS integrado, e testes necessários de isolamento por cliente.
- `docs/REQUIREMENTS.md`, `docs/ARQUITETURA-PROPOSTA.md`, README e contexto foram ligados ao levantamento. Nenhum provedor foi selecionado, permissão incerta foi promovida a fato, ou conta real foi criada. Faltam lista de usuários/e-mails e matriz aprovada pela Mix7.
- Fontes oficiais consultadas em 25/09/2026: documentação Laravel, Keycloak, authentik e Supabase. `git diff --check` passou; conferi os links relativos em seis arquivos e não há destinos quebrados; revisão manual confirmou que a mudança fica na documentação e não escolhe tecnologia. Não se aplicam testes de produto a esta atualização documental. GitHub informa que o branch do PR #10 não tem checks automáticos reportados.

## Fonte visual canônica corrigida — 2026-09-25

- A instrução mais recente do usuário restringe a referência visual ao projeto local `CRM-MIX7-RENEW`; `CONTEXT.md`, `DECISIONS.md`, `docs/PRODUCT.md` e `docs/REQUIREMENTS.md` foram alinhados. A menção no histórico de que o site público serviu de referência inicial está marcada como decisão superada, não como fonte vigente.
- O CRM está executando em `127.0.0.1:8198`. Chromium renderizou somente `/login`; `/administrador/dashboard` redireciona para `/login`. A comparação de composição autenticada continua pendente até o usuário abrir a sessão. Nenhuma credencial foi lida ou inserida e nenhum arquivo do CRM foi alterado.
- Esta alteração corrige somente a fonte de verdade documental; nenhum CSS ou componente visual foi modificado. `git diff --check` passou e a busca confirmou que os requisitos atuais não direcionam mais ao site público. Commit `2be097bcfed3599b9f45d2d7f35a794be1854d80` foi enviado e SHA local/remoto conferido; o cartão 25 recebeu o registro em https://trello.com/c/7OQNiG8Y/25-alinhar-visual-da-plataforma-ao-crm-mix7#comment-6ab612eafdc1e8c9132ef081. PR #10 continua rascunho; sem checks automáticos reportados para esta alteração documental.

## Atualização da pesquisa oficial — 2026-09-25

- Revisados `docs/PESQUISA-SOLUCOES.md` e `docs/PROVA-DE-CONCEITO.md` com páginas oficiais consultadas em 25/09: Wekan v11.99; avisos recentes de Wekan e Plane; preço publicado de Filestage Business; e limites iniciais gratuitos do Planable. Nenhum fornecedor foi escolhido. Avisos de segurança foram tratados como pendências de verificar intervalos vulneráveis/corrigidos, não como prova de que a release atual esteja vulnerável.
- O roteiro agora exige registrar release e digest da imagem e fechar uma matriz dos avisos antes de iniciar prova auto-hospedada. Para Wekan v11.99 e Plane v1.4.2 essa matriz ainda não foi feita; nenhum teste externo ou instalação foi executado.
- Fontes primárias consultadas: páginas oficiais de preço do Planable, Frame.io e Filestage; releases e avisos oficiais no GitHub de Wekan e Plane; documentação oficial do OpenProject. Pendentes validação operacional com caso real da Mix7, acesso autenticado ao CRM para referência visual, correspondência individual dos avisos e execução de prova autorizada.
- Validação concluída: `git diff --check` passou; links relativos dos dois documentos resolvem; diff limitado a pesquisa, roteiro e status. Commit de pesquisa `eb4207d3fc86cab97b6d0176d609220e36339402` publicado e SHA local/remoto confirmado na branch `research/atualizar-comparativo-e-arquitetura`. Cartão 14 do Trello atualizado, checklist documental em 2/3; recomendação continua aberta porque não há ferramenta selecionada. Sem testes de produto, instalação ou piloto.

## Arquitetura coerente com o fluxo aprovado — 24/09/2026

- `docs/ARQUITETURA-PROPOSTA.md` agora fixa a revisão interna como etapa do fluxo-alvo e trata o caso real como validação de atores, variações e exceções da operação; não como aprovação da estrutura do fluxo. `.ai/DECISIONS.md` e o resumo do estado também foram limpos de formulações antigas que reabriam a decisão.
- `git diff --check` passou; busca direcionada não encontrou texto dizendo que revisão interna ou o fluxo-alvo aceito ainda aguardam confirmação. Sem teste de produto novo nesta alteração documental.
- Commit `cfdb5d13f72044e586722936bafdc0721cfce66f` publicado e SHA local/remoto confirmado na branch `research/atualizar-comparativo-e-arquitetura`; cartão 16 do Trello atualizado com o alinhamento e o link. Caso real segue pendente para validação factual; nenhum produto/stack foi selecionado.

## Concluído

- Quadro Trello existente localizado e conexão verificada.
- Criadas as seis listas planejadas e 18 cartões de contexto, requisitos e entregas.
- Aplicadas as cinco cores de etiquetas já existentes no Trello aos cartões pertinentes. Legenda registrada no cartão **Decisões vigentes da primeira entrega**: azul = Gestão de equipe; verde = Aprovações; roxo = IA e automações; amarelo = Conhecimento e acessos; laranja = Pesquisa de soluções.
- Criados checklists nas cinco entregas e no cartão de decisões; entregas ligadas aos cartões de dependência por URLs nas descrições.
- Histórico da etapa inicial (decisão superada em 25/09): https://mix7.com.br/ foi inspecionado e a preferência de branco/azul foi registrada no cartão visual; a referência canônica atual é o projeto local CRM-MIX7-RENEW, conforme a decisão acima.
- Criada documentação inicial em `README.md`, `docs/`, `CONTRIBUTING.md` e template de pull request; as duas pastas de módulos agora possuem README sem código. Inicializado Git local e criado repositório privado `diegohenrich/plataforma-processos-mix7`.
- Regra de sincronização GitHub + Trello solicitada pelo usuário e registrada em `AGENTS.md`, `CONTRIBUTING.md`, `.ai/CONTEXT.md` e `.ai/DECISIONS.md`.
- O usuário aprovou `docs/FLUXO-PROPOSTO.md` como direção de comportamento do produto. A documentação distingue essa decisão da operação real, que ainda requer um caso concreto da Mix7.

## Estado atual e validação

- Os requisitos P0/P1/P2 são baseline para revisão; o fluxo-alvo integrado foi aprovado como diretriz do produto. Papéis, exceções, rotina observada e evidências por serviço ainda exigem caso real.
- Revisão interna antes do envio ao cliente e evidência conferida antes da conclusão fazem parte do fluxo-alvo; não são decisões estruturais pendentes. O caso real identifica responsáveis e variações operacionais.
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
- Foi solicitado um exemplo real para validar briefing, papéis, revisão e encerramento; a resposta ainda está pendente. O fluxo-alvo permanece aprovado como comportamento do produto, sem ser apresentado como rotina atual.
- Sincronização desta etapa: [PR #6](https://github.com/diegohenrich/plataforma-processos-mix7/pull/6), commit `15f122bde5d5ea10884148448af444392f947280`; branch publicada e SHA local/remoto conferidos como iguais. `git diff --check` e verificação dos links locais de Markdown passaram. PR permanece aberto para revisão; ainda não foi integrado à `main`.

- 2026-09-24 — Complementada a pesquisa comparativa com releases, licenças, páginas de preço e avisos de segurança publicados nos repositórios e sites oficiais. O release mais recente do Wekan consultado é v11.98 (24/09/2026); Plane v1.4.2 (23/08/2026). Os repositórios exibem avisos recentes com versões corrigidas, portanto o protocolo da prova exige verificar versão candidata, avisos, permissões, configuração e restauração de backup, sem concluir que releases atuais sejam vulneráveis ou certificadas. Foram registrados limites comerciais disponíveis de Planable, Frame.io e Filestage e a divergência de preço encontrada nas páginas oficiais de Planable para confirmar com fornecedor.
- Validação documental desta atualização: `git diff --check`; links oficiais incluídos na tabela e nas fontes. Não houve teste de produto, cotação, auditoria independente nem execução auto-hospedada.

- 2026-09-24 — Revisão de fontes e arquitetura conceitual concluída nesta atualização. `docs/PESQUISA-SOLUCOES.md` agora deixa explícita a diferença entre preços mostrados em duas páginas oficiais do Planable, não afirma o preço dinâmico do Frame.io sem confirmação e registra limites publicados do Filestage. `docs/ARQUITETURA-PROPOSTA.md` inclui conceitos e relações dos dados, fronteiras de acesso/histórico e contratos recomendados entre núcleo, módulos e integrações. Esses elementos são conceitos de produto, não esquema de banco ou especificação técnica.
- Validação: `git diff --check` passou; fontes oficiais conferidas em 24/09/2026. Conteúdo publicado no commit `f2d12a30c58bd69cf276f1e14a94e2b384d995e8`; SHA local e remoto conferidos como iguais. [PR #10](https://github.com/diegohenrich/plataforma-processos-mix7/pull/10) aberto como rascunho sobre PR #6 para revisão encadeada. Sem piloto de produto, cotação ou auditoria independente.

- 2026-09-24 — Revisão documental avançada: corrigido release Wekan para v11.98; incorporados limites/preços atualmente exibidos para Frame.io e Filestage, a diferença de preço ainda presente entre páginas oficiais de Planable e as condições publicadas de aprovação, conta de cliente e retenção. Criado `docs/PROVA-DE-CONCEITO.md` com cenário sintético comum, critérios eliminatórios/comparativos e portões para qualquer ensaio externo. README, produto e arquitetura agora apontam para essa etapa; `.ai/DECISIONS.md` registra o fluxo-alvo aprovado e separa fatos atuais ainda pendentes.
- Validação documental: `git diff --check`, destinos locais dos links Markdown nos nove arquivos alterados e ausência de espaços finais passaram. Fontes primárias consultadas em 24/09/2026. Nenhuma conta externa aberta, nenhum arquivo enviado e nenhum produto/stack selecionado. Conteúdo publicado em `63283ed3b794d1efd70160a389dfd18e483d71b3`, conferido contra o branch remoto `research/atualizar-comparativo-e-arquitetura`. [PR #10](https://github.com/diegohenrich/plataforma-processos-mix7/pull/10) permanece rascunho. Cartões Trello 14 e 16 atualizados após a publicação, mantendo pesquisa em revisão e arquitetura em andamento.

## Revalidação da pesquisa de soluções — 2026-09-25

- `docs/PESQUISA-SOLUCOES.md` foi complementado com consulta às páginas oficiais atuais: preço do Planable diverge entre a página pública e o centro de ajuda; Frame.io cobra por membro e seu teste exige cancelamento antes da conversão; Filestage oferece plano gratuito limitado a 1 projeto/5 arquivos mensais e seus planos pagos publicam US$ 199/329 por mês; Wekan v11.99 é a release de 24/09 e lista avisos recentes de autorização; Plane Community segue em v1.4.2/AGPL-3.0 com avisos de segurança que precisam ser verificados contra a versão candidata.
- O texto não recomenda compra nem uso de dados reais. Mantém como próximos critérios cotação de Planable, teste sintético sem upgrade, confirmação de licença e validação de avisos/permissões/backup antes de qualquer execução auto-hospedada.
- Fontes primárias consultadas em 25/09: páginas oficiais Planable, Frame.io e Filestage; releases, avisos e licença nos repositórios oficiais Wekan e Plane. `git diff --check` e revisão manual da seção foram executados; links adicionados são URLs oficiais. Teste de produto, criação de contas e compra não foram realizados.
- Commit `e0e1841ddc72020cd385665e00d3cc56e40deac7` publicado na branch `research/comparativo-arquitetura-inicial`; SHA local e remoto conferem. `git diff --check` passou. `gh pr checks 6` não reportou verificações para esta branch documental; não houve CI, teste do produto, cotação ou auditoria.
- Cartão 14 do Trello atualizado com a síntese, limitações, commit e PR: https://trello.com/c/DT5BeB96/14-comparar-solu%C3%A7%C3%B5es-existentes-e-bases-open-source#comment-6ab6102afda8f42058614170. PR #6 permanece em revisão e a prova controlada ainda não foi executada.

## Próximo passo

- Incorporar um caso real de demanda da Mix7 para confirmar atores, exceções e evidência de encerramento; a direção do fluxo do produto já foi aprovada.
- Depois da validação operacional, executar o protocolo `docs/PROVA-DE-CONCEITO.md` com dados sintéticos, responsável e conta de teste autorizados; ainda não houve ensaio nas contas dos fornecedores.
- Usar os resultados do piloto para escolher caminho de implementação, detalhar arquitetura técnica e stack; PRs #7–#10 continuam abertos para revisão incremental.
- Manter documentação, GitHub e Trello sincronizados após cada entrega.

## Pesquisa documental — OpenProject incluído (2026-09-24)

- Acrescentado OpenProject Community/Premium à comparação e ao roteiro de prova de conceito, com links oficiais para recursos, API, permissões, licença, preços, release e avisos. A Community cobre gestão de trabalho, Gantt, tempo e disponibilidade/atributos; o planejador de capacidade está no Premium Enterprise. Não foi feita instalação nem teste de produto; cliente externo, revisão de mídia, custo do plano necessário e adequação de interface continuam sem evidência prática.
- Arquitetura conceitual agora registra OpenProject como candidato de gestão interna, sem decisão de adoção/stack. `git diff --check` e resolução dos links relativos nos quatro arquivos alterados passaram; revisão do diff confirma apenas pesquisa, roteiro da prova, arquitetura e este status. Fontes oficiais consultadas em 24/09/2026; teste de produto, cotação e análise jurídica não executados.
- A fatia de implementação foi atualizada separadamente na branch `implementation/primeira-jornada-local`, commit de código `74700857f5c3be0e47c17b4ce051c7c9d4a8e464`, com estado em `2579a4efefb1d8dde0a736a6b62677663d3b57c0`; o cartão 18 foi atualizado. PRs #9 e #10 continuam rascunhos.
- Cartões 14 (comparativo) e 16 (arquitetura) atualizados e relidos pela integração do Trello; ambos apontam para os documentos e o commit `26aab18c490626199f1050d7f20789cf82c62b81`. O PR #10 segue rascunho.
- Próximo passo: comparar critérios P0 da especificação com o protótipo e implementar a próxima lacuna fechável com dados fictícios. A prova de produto continua condicionada a um caso real anonimizado, responsável da Mix7 e conta/ambiente de teste autorizados.

## Atualização desta continuação — 2026-09-25

- A comparação e o roteiro agora registram que o MinIO Community foi arquivado, que o Plane documenta armazenamento externo S3 e que nenhum provedor foi escolhido/testado. As referências visuais do CRM aguardam os prints prometidos; essa pendência não impede pesquisa e documentação independente.
- Resolvidos os conflitos do PR #10 preservando evidências dos dois branches. A branch `research/atualizar-comparativo-e-arquitetura` foi publicada no commit `300679a6a193e51df8ad7e17ac5f8c5e91c9cd46`, SHA local/remoto igual. PR #10 segue rascunho, estado `CLEAN`; `gh run list` não reportou checks automáticos.
- `git diff --check` passou e links locais verificados. Nenhum teste de produto ou instalação foi realizado. Cartão 14 no Trello permanece Em revisão, checklist 2/3; atualização final desta continuação registrada no cartão.
- Fontes primárias: https://github.com/minio/minio e https://developers.plane.so/self-hosting/govern/database-and-storage. Sem decisão de adoção, provedor, compra ou stack.
