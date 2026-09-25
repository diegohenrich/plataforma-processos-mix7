# Primeira implementação: fluxo local de demandas

**Estado:** fatia funcional para demonstração e validação; não é ambiente de produção nem decisão de stack.

### Atualização imediata após criar demanda — 2026-09-25

Corrigi a criação de briefing para redesenhar o quadro logo após salvar, sem exigir recarga ou outra interação. Em Chromium isolado, uma demanda sintética apareceu imediatamente e continuou visível após recarregar. Repeti o percurso com texto contendo marcação HTML e JavaScript: o conteúdo permaneceu literal, nenhum elemento foi injetado nem código executado, e não houve erros de página. Em nova rodada pela interface, criei uma demanda na visualização Lista em 1440 px e outra no Quadro em 390 px; ambas apareceram sem recarga e permaneceram após recarregar, sem overflow horizontal da página ou erros JavaScript. A captura renderizada em 1440 × 1000 está em [nova demanda no quadro](evidencias/visual/nova-demanda-imediata-1440.png). `node --test tests/*.test.js` passou 31/31; `node --check prototipo/app.js`, `node --check prototipo/workflow.js` e `git diff --check` passaram. Os ensaios usaram armazenamento descartável e dados sintéticos.

### Visualização Lista entre celular e desktop — 2026-09-25

Uma varredura real em Chromium isolado reproduziu excesso horizontal em 601 px: as linhas tabulares precisavam de 820 px e escapavam do espaço disponível. No breakpoint seguinte, a barra de filtros também extrapolava a tela antes de se reorganizar. A Lista agora usa cartões compactos até 1125 px e põe os controles de busca/filtro abaixo do seletor de visualização enquanto o espaço horizontal é limitado; acima disso, mantém a tabela larga quando ela cabe.

Validei quadro e lista nas larguras 390, 600, 601, 768, 900, 901, 935, 936, 1024, 1100, 1125, 1126, 1200 e 1440 px. Em todas, `body/documentElement.scrollWidth` coincidiu com a largura da janela e a Lista não teve largura interna maior que o painel. Em 601 px, criei uma demanda fictícia pela interface, confirmei que apareceu sem recarregar e continuou após recarga; não houve erro JavaScript. A captura renderizada em [Lista no tablet a 601 px](evidencias/visual/nova-demanda-lista-tablet-601.png) foi inspecionada visualmente. Os dados foram mantidos em contexto de teste isolado.

### Biblioteca local de Conhecimento e onboarding — 2026-09-25

Implementei `prototipo/knowledge.js` e a página Conhecimento com cadastro, edição, busca, filtro por tipo, arquivamento e restauração de referências, treinamentos, contatos e trilhas de onboarding. Todos os itens pedem responsável, público e data de revisão conforme o critério do cartão Trello #10; isso é texto informativo, não define permissões. Treinamentos e trilhas guardam passos ordenados; onboarding sem passo, data impossível ou link fora de HTTP(S) são recusados. O fluxo-alvo aprovado continua visível em um guia recolhível na página.

Chromium isolado percorreu a criação dos quatro tipos; onboarding sem passo foi recusado; edição, busca, filtro, arquivo/restauração e recarga mantiveram os itens locais. Em 390 × 844, também criei e renderizei uma trilha; documento e corpo ficaram em 390 px sem overflow horizontal. Não houve erros JavaScript. Renderizei e inspecionei capturas em [desktop 1440 px](evidencias/visual/conhecimento-desktop-1440.png) e [celular 390 px](evidencias/visual/conhecimento-mobile-390.png). Os nomes e materiais usados eram fictícios. A suíte adicionada cobre validação, filtros, criação/edição e restauração.

Limites: persistência apenas em `localStorage` do navegador; sem contas, sincronização, acesso individual ou conclusão de onboarding por pessoa. Campos, responsáveis, públicos, conteúdos e contatos reais precisam de validação com a Mix7. Não armazenar credenciais. Ver [especificação e pendências do módulo](CONHECIMENTO-ONBOARDING.md).

### Alteração do briefing durante a execução — 2026-09-25

Em Chromium/Playwright, com demanda e perfil descartáveis na origem `127.0.0.1:4232`, confirmei o briefing, criei uma tarefa atribuída e iniciei a execução. Enviar a revisão sem os campos obrigatórios não alterou a etapa. Com novo contexto e motivo, a demanda voltou a Planejamento; o painel exibiu o motivo e o texto anterior, e o histórico registrou a alteração. A interface só retomou Em produção após “Confirmar revisão do plano”. Não ocorreram erros JavaScript. Renderizei e inspecionei o estado em 1440 × 1000; os dados eram fictícios e nenhuma conta ou colaboração multiusuário foi testada.

### Dados estruturados do briefing e comentário interno — 2026-09-25

Em contexto descartável no Chromium (`127.0.0.1:4233`), editei origem, canal/peça, critérios de aceite e referência de uma demanda sintética. Os valores atualizados apareceram no painel e o histórico registrou `briefing_details_updated`. Depois avancei até Em produção: enviar comentário interno vazio mostrou “Escreva um comentário antes de enviar.” e não acrescentou comentário ou evento; texto válido apareceu na conversa e no histórico, ligado à V01. Sem erros JavaScript. Esta é persistência local de demonstração, sem teste de visibilidade por papel ou sincronização multiusuário.

### Verificação móvel dos formulários e comentários — 2026-09-25

Repeti os formulários e o envio de comentário em Chromium, viewport 390 × 844 e origem descartável `127.0.0.1:4234`. O painel ocupou 390 px, o campo de comentário ficou visível após rolagem interna e o comentário apareceu na conversa e no histórico. O documento manteve 390 px de largura, sem rolagem horizontal; o rodapé mostrou os controles e a orientação de conclusão de tarefas. Captura renderizada e inspecionada: [comentário interno em 390 × 844](evidencias/visual/comentario-interno-mobile-390.png). Sem erros JavaScript; nenhum dado do perfil de uso foi acessado.

## O que já executa

### Revisão interna pela interface — 2026-09-25

Em Chromium/Playwright, numa origem e perfil descartáveis (`127.0.0.1:4231`), criei uma demanda sintética, avancei o briefing, atribuí e concluí uma tarefa, anexei um PNG sintético e enviei V01 para revisão interna. Tentar “Devolver” sem justificativa manteve a etapa em Revisão interna e mostrou “Registre o motivo da devolução.” Com justificativa, a demanda voltou a Em produção, o texto ficou registrado como comentário e o histórico recebeu “Devolvido pela revisão interna” ligado a V01. Não houve erros JavaScript. Esta execução valida a resposta da interface e o estado local; não valida identidade, papéis reais nem colaboração entre usuários.

A tela `prototipo/` mantém os cartões no quadro e na lista, registra briefing, busca e detalhes, permite filtrar por etapa, cliente, prazo ou profissional e exportar texto em JSON/CSV. Na exportação CSV, valores que podem iniciar uma fórmula recebem um tabulador antes do conteúdo, conforme mitigação indicada pela OWASP para planilhas; o tabulador permanece no arquivo e pode afetar importações automáticas. Não há sanitização universal segura para todos os programas de planilha e fluxos. Tem consultas locais por equipe, cliente e prazo; Conhecimento permite administrar itens locais de quatro tipos e passos de onboarding, enquanto Acessos explica as categorias sem simular contas. Um painel de demanda pode ser minimizado em atalho fixo e reaberto sem apagar dados. Também permite planejar tarefas com responsável, estimativa, início planejado opcional e prazo final, registrar dependências entre tarefas da mesma rodada e sinalizar impedimentos com motivo durante a execução. O calendário exibe cronograma Gantt dessas datas: intervalos aparecem como barras, uma única data como marco e tarefas sem datas ficam listadas sem previsão; não calcula disponibilidade nem capacidade. Dependências e impedimentos bloqueiam a conclusão até serem resolvidos; alterações entram no histórico. O briefing oferece seletor e área de arraste para imagens, vídeos ou PDFs de até 15 MB por arquivo; cria versões, grava comentários e eventos, aplica decisões e avança a demanda pelas regras do fluxo. Fechar ou cancelar uma demanda ainda incompleta descarta o rascunho sem executar a validação de salvamento. Comentários podem ser ligados a um ponto percentual da imagem ou ao instante pausado do vídeo; atalhos no histórico abrem a versão correspondente. Em Chromium, o seletor e o evento de arraste foram validados com arquivos sintéticos, incluindo recusas, persistência e download em viewport desktop; o seletor, o briefing e o download também foram inspecionados em 390 × 844. O arraste físico do sistema operacional ainda aguarda teste ponta a ponta.

## Cobertura dos requisitos P0

O baseline P0 está na [especificação priorizada em revisão no PR #8](https://github.com/diegohenrich/plataforma-processos-mix7/blob/spec/requisitos-priorizados/docs/REQUIREMENTS.md). “Parcial local” indica comportamento demonstrável neste navegador; não significa requisito pronto para operação.

| Requisito | Cobertura atual no protótipo | Limite ainda aberto |
| --- | --- | --- |
| MVP-01 Briefing | Parcial local: cliente, tipo, origem, canal/peça, critérios e opcionais de prazo, links e arquivos de referência; bloqueia planejamento se os campos mínimos faltarem. Arquivos de referência podem ser selecionados ou arrastados, ficam separados da versão de entrega e podem ser baixados no painel. Seletor, evento de arraste, recusas, persistência, download e layout móvel em 390 × 844 foram verificados. | Arraste real do sistema operacional ainda não foi validado; campos obrigatórios por serviço não são configuráveis; prazo e links não são validados; arquivo, nome e metadados dependem do mesmo perfil local do navegador. |
| MVP-02 Visões e trabalho por pessoa | Parcial local: Kanban, lista, busca e filtro das demandas com tarefas pendentes da rodada atual por profissional. A página Equipe mostra uma fila própria das tarefas pendentes da rodada atual, ordenada por prazo, com responsável, cliente, bloqueios e atalho para abrir a demanda. | A fila usa nomes de texto e filtros locais, não autentica a pessoa nem impõe permissão de acesso; tarefas sem prazo aparecem por último. |
| MVP-03 Tarefas | Parcial local: responsável, status, estimativa, início planejado, prazo final, cronograma Gantt, marcos e tarefas sem data; dependências múltiplas dentro da rodada; impedimento com motivo e histórico; cronômetro iniciar/parar, retomada após recarga, sessões somadas e histórico. | Gantt representa apenas datas informadas; disponibilidade/capacidade, regras de expediente/feriados, identidade, notificações e coordenação entre usuários permanecem ausentes. Uma tarefa concluída só pode ser reaberta após reabrir tarefas que dependem dela. |
| MVP-04 IA no planejamento | Não implementado; o plano continua manualmente editável. | Provedor, dados enviados e experiência de confirmação humana ainda precisam de definição. |
| MVP-05 Versões | Parcial local: versões e arquivos no IndexedDB do navegador. Se um rascunho for substituído antes do envio, o histórico mantém o arquivo anterior disponível para baixar. | Sem armazenamento central ou auditoria imutável; rascunhos anteriores dependem do mesmo perfil do navegador. |
| MVP-06 Decisão do cliente | Parcial local: aprovar ou solicitar ajustes em versão compartilhada. | Sem identidade autenticada, isolamento por cliente ou portal externo. |
| MVP-07 Comentários de mídia | Parcial local: comentário por versão, ponto percentual de imagem e timecode de vídeo. | A referência depende do mesmo perfil local; acesso remoto e armazenamento seguro de mídia faltam. |
| MVP-08 Ajustes e nova versão | Parcial local: comentário e decisão permanecem na versão; em ajustes, um comentário do cliente pode iniciar um rascunho editável de tarefa, mantendo vínculo ao feedback e à versão. A equipe confirma a descrição e atribui responsável antes de criar. | Consolidação cronológica assistida por IA não existe; não há geração nem atribuição automática de tarefas. |
| MVP-09 Revisão interna | Parcial local: a etapa bloqueia compartilhamento até aprovar; devolução pede motivo. | Papel e identidade do revisor não são verificados; exceções e substituições não são configuráveis. |
| MVP-10 Entrega e conclusão | Parcial local: resultado pós-aprovação registrado como material entregue, publicação agendada ou material publicado; exige evidência e mostra o tipo no histórico/quadro. | Sem integração externa, data/destino ou metadados próprios do serviço; a evidência é texto livre local. |
| MVP-11 Histórico | Parcial local: transições ligam versão, arquivo, comentário/motivo, tarefa e resultado com horário, identificadores e resumo legível. Substituição mostra o rascunho anterior e permite baixá-lo quando disponível. | Dados e autoria ficam no navegador; sem identidade confiável, histórico central, retenção ou proteção contra alteração local. |
| MVP-12 Módulos futuros | Não implementado: estados do fluxo estão definidos no código. | Aprovações configuráveis e cadastros compartilhados ainda são arquitetura conceitual, não comportamento executável. |

O filtro de MVP-02 é uma demonstração da visão individual solicitada; ele combina com quadro/lista e busca e esconde demandas sem tarefa pendente atribuída à pessoa selecionada, mas não é segurança. A página Equipe já oferece uma fila de tarefas separada; ambas as superfícies reúnem apenas dados locais existentes nas demandas e tarefas. As telas de clientes e calendário também são consultas locais. Conhecimento é gestão local demonstrativa; não gerencia publicação central, vínculo de conteúdo a pessoas ou conclusão individual de treinamento. Acessos é informativo. Gestão de contas, avaliação e permissões reais dependem de requisitos e arquitetura de produção.

No briefing inicial, a demonstração coleta origem do pedido, canal/peça e critérios de aceite. A demanda não segue para planejamento enquanto faltar um desses dados; referências, prazo e arquivos de referência podem ficar vazios. Arquivos opcionais de imagem, vídeo ou PDF (até 15 MB cada nesta demonstração) podem ser selecionados ou arrastados para a área indicada. Arquivos incompatíveis ou maiores são recusados; vários arquivos válidos podem ser anexados juntos. Os arquivos ficam separados do criativo final e são recuperáveis pelo painel enquanto permanecerem no IndexedDB deste navegador. Chromium percorreu seletor e evento sintético de arraste com PNG/PDF, tipo não permitido e arquivo acima do limite; salvamento, recarga e downloads foram conferidos byte a byte. Em viewport móvel 390 × 844, o seletor, a zona de anexos e o botão de download foram renderizados, inspecionados e testados. O evento físico de arraste do Explorador de Arquivos ainda requer validação manual. A criação registra nome, tipo e tamanho no histórico. Alterações nos campos iniciais são preservadas no histórico. Esta regra mínima e os limites de arquivo ainda precisam de validação por tipo de serviço com um caso real da Mix7.

O caminho implementado é:

`Briefing → Planejamento → Produção → Revisão interna → Aprovação do cliente → (Ajustes → nova versão → revisão) → Entrega/publicação → Concluída`

Durante a execução, a equipe pode registrar uma alteração do briefing e seu motivo. A demanda retorna a planejamento, preserva o texto anterior e pausa a execução. Uma pessoa precisa revisar o briefing e as tarefas e confirmar o plano para retomar. Esta regra é exercitada com dados fictícios; ainda não há permissões reais que identifiquem a pessoa revisora.

O plano exige ao menos uma tarefa atribuída; revisão interna exige que as tarefas da rodada estejam concluídas e que haja um arquivo. O cliente só decide uma versão compartilhada após a revisão interna. Pedido de ajustes requer justificativa e fica preso à versão; a nova versão exige tarefas da rodada de ajuste concluídas e preserva a decisão anterior. Após a aprovação, a demonstração registra se houve entrega, agendamento ou publicação e exige evidência antes de concluir; não executa essas ações em canais externos.

## Implementação atual

- Interface: HTML, CSS e JavaScript nativos, reutilizando o protótipo visual.
- Regras de transição puras e testadas em `prototipo/workflow.js`.
- Regras da biblioteca, filtragem e arquivamento em `prototipo/knowledge.js`; registros ficam em `localStorage` (`mix7.knowledge.v1`) separado das demandas.
- Demandas, versões, comentários e trilha local em `localStorage` (`mix7.workflow.v1`).
- Arquivos binários em `IndexedDB` (`mix7.workflow.assets`).
- Sem dependências de build; roda servindo a pasta `prototipo/` por HTTP local.
- Itens iniciais são ficcionais; comentários e material ilustrativo também são fictícios.

## Limites e tratamento de dados

O navegador serve apenas para validar comportamento. Cada perfil de navegador tem dados próprios, sem sincronização ou backup gerenciado. Limpar os dados do site pode removê-los. Não use conteúdo de clientes, credenciais ou informação pessoal. O filtro por profissional é uma conveniência visual local; não limita o acesso a dados e não substitui login, isolamento por cliente ou autorização no servidor. Também não há portal do cliente, trilha de auditoria à prova de adulteração, controle de retenção ou recuperação de backup. Os controles locais não constituem aprovação enviada ao cliente.

O ponto em imagem usa coordenadas percentuais relativas à mídia e o vídeo registra o instante em segundos; são comportamentos locais de demonstração a validar com a equipe. Os arquivos precisam estar presentes neste mesmo perfil do navegador para abrir a referência. As tarefas usam nomes livres e não são contas de membros: ainda não há gestão de equipe/autorização compartilhada ou capacidade. O cronômetro da demonstração é local ao navegador, não atribui autoria e não deve ser usado como avaliação. Também não há notificações, IA, integrações, nem registro separado de agendamento vs. publicação. Esses requisitos seguem na especificação priorizada.

## Verificação

Executar da raiz:

```powershell
node --test tests/*.test.js
node --check prototipo/workflow.js
node --check prototipo/knowledge.js
node --check prototipo/app.js
```

Os testes cobrem briefing incompleto e registro histórico de correções, plano com tarefa atribuída, dependências entre tarefas, reabertura ordenada, impedimento com motivo/histórico, bloqueio de revisão com tarefas incompletas, fluxo feliz, aprovação de versão não compartilhada, motivo obrigatório na revisão interna, pedido de alteração ligado à versão antiga, preservação do histórico na versão seguinte, âncoras de imagem/vídeo, alteração de briefing com retomada bloqueada até revisão humana, evidência obrigatória, metadados de auditoria por arquivo/decisão/comentário/versão e serialização integral JSON/CSV. Os testes de exportação verificam schema, data e histórico no JSON; no CSV verificam BOM UTF-8, CRLF, colunas, linhas e neutralização de fórmulas. Em Chromium, dependência, impedimento, pedido de ajuste, geração da próxima versão e aprovação interna foram percorridos; histórico mostra versão, nome de arquivo e comentário/motivo. Viewport de 390×844: painel e documento sem overflow horizontal; resumo de comentário longo limitado visualmente e screenshot móvel inspecionado. Console sem avisos/erros. A integração ainda requer validação da equipe Mix7.

## Simulações manuais de demandas — 24/09/2026

Foram percorridos cenários fictícios isolados no navegador:

- **Briefing novo e incompleto:** o formulário impediu salvar sem os campos mínimos. Com origem, canal/peça e critério de aceite, a demanda avançou até planejamento; a execução ficou bloqueada até existir tarefa atribuída.
- **Planejamento e dependências:** uma tarefa atribuída foi criada e outra ficou dependente dela. A segunda não pôde ser concluída antes da primeira. Um impedimento com motivo também bloqueou a conclusão; ao removê-lo e concluir a tarefa anterior, a dependente foi liberada. Sem arquivo anexado, o envio para revisão interna permaneceu bloqueado.
- **Ajuste do cliente:** um pedido fictício na V03 foi preservado com vínculo à versão. A equipe transformou manualmente o comentário em rascunho de tarefa, atribuiu a uma pessoa fictícia e concluiu a tarefa; o fluxo então liberou a anexação da nova versão.
- **Variações de demanda:** foram criadas demandas fictícias para vídeo e newsletter, incluindo e omitindo prazo e referência opcionais.
- **Aprovação e conclusão:** em sessões isoladas, testar aprovação direta e publicação agendada. Aprovar moveu a demanda para entrega/publicação, mas não a concluiu. O registro só foi aceito após escolher o resultado e informar evidência fictícia; o histórico guardou ambos.

As sessões anteriores usaram armazenamento local separado por origem do navegador e dados fictícios. Em 24/09/2026, uma sessão isolada também criou uma demanda de teste com um PDF sintético: nome e tipo apareceram no painel e no histórico, permaneceram após recarregar, e o arquivo baixado teve o mesmo SHA-256 do original (`B8AE055C146242ADCA8F1487651495FAFF9B9D29A22CA262FD9AA86572C29A25`). Nenhum dado real de cliente foi usado. Ainda não foi validada a jornada completa de uma demanda nova com mídia de referência, revisão interna, versão enviada ao cliente e aprovação. A apresentação móvel dos anexos também permanece por conferir. Os cenários confirmam regras da demonstração, não a rotina real da Mix7; atores, exceções e evidências precisam do caso anonimizado descrito em [VALIDACAO-CASO-REAL.md](VALIDACAO-CASO-REAL.md).

## Próximas decisões

Antes de usar dados reais, fechar o caso real da Mix7 e sua matriz de papéis; escolher persistência central, autenticação, isolamento, hospedagem, backup, retenção e integração de arquivos; validar o comportamento das âncoras com equipe e cliente. A escolha final de tecnologias deve seguir a pesquisa e as restrições operacionais.

### Auditoria isolada dos controles principais — 25/09/2026

Em perfil Chromium descartável na origem `127.0.0.1:4228`, confirmei navegação nas oito páginas, cancelamento sem salvar briefing vazio, criação de demanda com dados sintéticos e persistência após recarga. Uma demanda minimizada reapareceu na bandeja, reabriu com o mesmo título e continuou disponível após outra recarga. Os filtros reduziram as oito demandas de demonstração a duas ao escolher uma etapa e o botão Limpar restaurou a visão. O atalho Buscar saiu de Conhecimento, abriu Demandas e focou o campo; “Botânica” mostrou uma demanda e limpar a busca restaurou as oito. Lista e Quadro alternaram sem perder cartões. O menu da coluna Planejamento abriu o diálogo correspondente; JSON e CSV iniciaram downloads com nomes esperados. Os cinco atalhos “Nova demanda” nas colunas abriram o formulário no briefing; cancelar não criou registros. Os quatro tipos de participante puderam ser selecionados em Acessos; janelas de informações do espaço e perfil abriram e fecharam. Abri cada uma das três pendências fictícias: a janela fechou, a demanda correta abriu e, ao fechar o painel, o foco retornou ao botão Pendências. Não houve erro JavaScript. O perfil descartável foi fechado ao final e não tocou a origem de uso `4173`. Esta auditoria verifica interação funcional; não substitui validação de papéis, sincronização entre pessoas ou operação com clientes reais. Acessos ainda não cria contas nem impõe permissões.

### E2E adicional de briefing e tarefas — 25/09/2026

Em Chromium numa origem isolada, uma demanda sintética avançou do formulário para planejamento, recebeu tarefa com responsável e estimativa, foi confirmada e chegou à execução. A página Equipe mostrou a atribuição e abriu a demanda. Ao concluir a tarefa, o evento entrou no histórico e a fila pendente ficou vazia. O seletor de arquivo nativo não é exposto pelo harness CUA; por isso, esta execução não percorreu anexo, revisão, aprovação do cliente ou conclusão completa. Essa limitação não substitui nem invalida testes anteriores de seleção de arquivo sintético, mas o E2E integrado dessas etapas segue aberto.

### Jornada completa de aprovação em Chromium — 2026-09-25

Com Playwright 1.63 e Chromium local, sem adicionar dependências ao projeto, foi executado um contexto novo e isolado em `127.0.0.1:4199`. O roteiro criou briefing sintético; confirmou plano com profissional/estimativa; concluiu V1; anexou PNG sintético por `filechooser`; submeteu à revisão interna; compartilhou; registrou pedido de ajuste do cliente ligado à V1; criou/concluiu tarefa de ajuste; anexou a nova V2; repetiu revisão interna; aprovou V2; registrou publicação e evidência; e conferiu estado final Concluídas. O histórico exibiu a sequência e o comentário antigo permaneceu visível; `pageerror` não registrou erros.

A segunda execução capturou e inspecionou a tela final em 1440×1000: drawer, V02, feedback em V01, etapa Concluídas, resultado e toast de sucesso aparecem juntos, sem corte horizontal. A imagem de um pixel é apenas arquivo sintético para validar o percurso, não uma amostra visual de criativo.

Limite: a mesma sessão local representou os cliques de cliente e equipe; portanto, isto valida estados e transições, não portal remoto, identidade, permissões ou isolamento real entre usuários. O navegador foi descartável, sem dados de cliente.

### Devolução interna e comentário temporal de vídeo — 2026-09-25

Em Chromium/Playwright 1.63, contexto descartável na origem `127.0.0.1:4201`, percorri com demanda e mídia sintéticas: briefing de vídeo → planejamento e tarefa atribuída → V1 de vídeo WebM → revisão interna. “Devolver” sem motivo manteve a etapa; com motivo, voltou à produção e registrou comentário interno. A demanda foi reenviada e aprovada para o cliente.

O pedido de ajustes do cliente também foi bloqueado sem justificativa. Com justificativa, o comentário ficou ligado à V1 e ao instante pausado do vídeo (aproximadamente 1,56 s). Reabrir a âncora levou o player novamente ao ponto registrado. Uma tarefa de ajuste permitiu anexar V2; revisão interna e aprovação do cliente levaram ao registro de agendamento com evidência fictícia e à conclusão.

Na revisão do cliente, “Solicitar ajustes” começa desabilitado e só é habilitado depois que a justificativa contém texto diferente de espaços. A regra de domínio também recusa texto vazio ou só com espaços sem mudar etapa, decisão ou comentários. Em Chromium, o campo vazio e o texto só com espaços mantiveram o botão desabilitado; texto válido habilitou a ação. Ao acioná-la no perfil sintético isolado, a demanda saiu de Aprovações, entrou em Ajustes e mostrou o comentário registrado ligado à V3. Nesse fluxo, “reprovar” significa pedir alterações e devolver para correção; rejeição final sem reenvio ainda não foi confirmada como requisito.

### Verificação de apresentação no LibreOffice Calc — 2026-09-25

Abri uma cópia temporária do CSV de demonstração no importador/planilha do LibreOffice Calc e renderizei a saída usando o próprio LibreOffice. As sete colunas foram reconhecidas separadamente, os acentos permaneceram legíveis e as oito demandas apareceram. Na impressão A4 em retrato, a largura total divide as colunas em duas páginas; nomes longos também são cortados conforme a largura padrão. Isso é uma limitação da apresentação/impressão automática, não perda dos campos no CSV. Capturas renderizadas: [página 1](evidencias/visual/exportacao-csv-libreoffice-pagina-1.png) e [página 2](evidencias/visual/exportacao-csv-libreoffice-pagina-2.png). O arquivo original em Downloads foi copiado para uma pasta temporária; não foi modificado.

Em outra sessão isolada, aprovei diretamente a versão demonstrativa V03 pela página Aprovações. A demanda saiu da fila de decisão e foi para Entrega e publicação; a interface informou que aprovação, sozinha, não conclui o trabalho. Com “Publicação agendada” selecionado, evidência vazia ou só com espaços não liberou a conclusão. Uma URL demonstrativa habilitou “Registrar resultado e concluir”; após acionar, o cartão apareceu em Concluídas e o histórico registrou a aprovação e o agendamento com evidência. Captura inspecionada em 1280 × 720. Nenhum dado real ou a origem de uso `127.0.0.1:4173` foi utilizado.

Asserções finais passaram: decisão da V1 preservada como `changes_requested`, V2 aprovada, histórico contendo devolução interna, pedido de ajuste e conclusão, destino `scheduled`, e nenhum erro JavaScript. A captura do comentário temporal em tela foi inspecionada em `%LOCALAPPDATA%/Temp/mix7-video-comment-anchor.png`. Os testes automatizados seguem em 21/21; checks de sintaxe e diff passaram.

Limite: os papéis de cliente e equipe foram simulados na mesma sessão e todos os dados/mídias eram fictícios. A execução valida a interface local e a persistência no perfil do navegador; não valida contas, portal remoto, permissões nem o piloto real da Mix7.

### Auditoria de navegação e filtros — 2026-09-25

Uma checagem de interface em Chromium/Playwright 1.62.1, com perfil e dados descartáveis na origem `127.0.0.1:4201`, revelou que cada abertura do diálogo de filtros acrescentava outra cópia das etapas e dos clientes. A lista de etapas crescia de 9 para 17 e 25 opções nas três primeiras aberturas. `populateFilterDialog()` agora recria as opções-base antes de preencher os valores atuais; três aberturas consecutivas mantiveram 9 etapas, 4 clientes e 5 opções de prazo, e filtrar por Ajustes mostrou somente a coluna correspondente.

No mesmo perfil descartável, passaram: navegação nas oito páginas; alternância quadro/lista; busca por “Botânica”; filtros de etapa; abrir/fechar perfil, pendências e opções; downloads JSON e CSV; cancelar briefing vazio sem criar demanda; e abrir, minimizar e reabrir uma demanda. Nenhum erro JavaScript ocorreu. A janela do filtro foi renderizada e inspecionada após a correção. A origem de uso `4173` não foi aberta nem alterada.

Este smoke test cobre esses controles e não equivale a teste de produção nem à auditoria de cada campo e transição. Autenticação, contas e permissões permanecem ausentes no protótipo local.

### Cronograma Gantt local — 2026-09-25

As tarefas podem guardar início planejado e prazo final opcionais; prazo anterior ao início é recusado, e as datas são registradas no histórico. O calendário mostra barras para períodos, marcos quando há somente uma data e uma lista própria para tarefas sem data. A linha da tarefa abre sua demanda. O Gantt cobre tarefas da rodada atual; não calcula disponibilidade, capacidade, jornada ou feriados.

Validação de interface em Chromium, na origem distinta `localhost:4173`, com dados fictícios: a tarefa sintética de 26 a 29 de setembro apareceu com quatro marcas de data, responsável e cliente, e seu botão abriu a demanda correta. A captura desktop foi inspecionada; em 390×844, a faixa do Gantt rolou horizontalmente dentro do painel e a página não ganhou rolagem horizontal. O armazenamento de `127.0.0.1:4173` permaneceu intocado. `node --test tests/workflow.test.js` passou 22/22; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Dados e horas deste teste são fictícios; regras operacionais de disponibilidade seguem pendentes.

### Abrir demanda a partir de pendências — 2026-09-25

Uma verificação isolada encontrou que clicar numa pendência abria sua demanda atrás do diálogo modal, deixando-a inacessível. O botão agora fecha o diálogo antes de abrir o drawer. Chromium no perfil sintético `127.0.0.1:4203` confirmou que clicar em “Briefing precisa ser completado” fecha a janela de pendências e deixa a demanda correta aberta; o botão Fechar do drawer retorna ao quadro. A sessão foi isolada das abas de uso em `127.0.0.1:4173`.

### Navegação e criação de briefing em perfil isolado — 25/09/2026

Em Chromium, numa origem descartável `127.0.0.1:4207`, percorri as oito páginas da navegação, alternei para Lista e abri filtros, Mais opções e Pendências. As opções de etapa, cliente e prazo apareceram; as pendências exibiram os briefings incompletos e a aprovação à espera do cliente. Abrir um briefing pela lista de pendências fechou o diálogo e abriu o detalhe correto.

Preenchi todos os campos obrigatórios do formulário com conteúdo sintético, salvei a demanda e confirmei sua presença no começo do fluxo. Após recarregar, a demanda e seus dados continuaram visíveis. A execução confirmou gravação local no perfil do navegador. O diálogo de exportação foi aberto, mas não foram iniciados downloads; os filtros foram inspecionados, mas não aplicados. Esta rodada não valida autenticação, integração entre usuários ou operação de produção.
### Filtros, briefing incompleto e demanda minimizada — 25/09/2026

Em Chromium, no perfil sintético descartável `127.0.0.1:4208`, apliquei o filtro de cliente Café Aroeira e conferi as três demandas resultantes. Tentei salvar um briefing vazio: a validação nativa impediu o envio e moveu o foco para o primeiro campo obrigatório. Cancelei e a contagem seguiu em oito. Em seguida, abri uma demanda de briefing incompleto e confirmei que “Confirmar briefing e planejar” está desativado enquanto origem, canal/peça e critérios faltam. Minimizei e reabri a demanda; título, cliente, briefing e histórico foram preservados. A captura inspecionada mostra rolagem dentro do painel e os controles de ação fixos no rodapé. Os dados eram fictícios e a origem não compartilha armazenamento com as abas de uso. Testes de regras: 22/22; sintaxe dos arquivos JavaScript e `git diff --check` passaram. Esta auditoria não altera código nem demonstra autenticação ou operação multiusuário.

### Catálogo dos tipos de usuário citados nos áudios — 2026-09-25

A página Acessos agora lista quatro categorias: responsável pela Mix7, gerente, profissional da equipe e cliente/aprovador. Cada botão seleciona uma ficha com a capacidade explicitamente mencionada e as decisões que ainda faltam. Os trechos e responsabilidades vêm de `docs/TRACEABILIDADE-AUDIOS.md`; os pesos de avaliação foram mantidos como evidência da fala, sem deduzir cargo administrativo, atribuição de tarefas ou autoridade adicional. O catálogo é uma referência de produto: não cria contas, login ou bloqueios de acesso.

Ao verificar essa página, encontrei e corrigi uma regra de CSS: o quadro Kanban permanecia visível abaixo das páginas auxiliares porque a regra de `display:grid` prevalecia sobre o atributo HTML `hidden`. Chromium/Playwright percorreu Acessos e Demandas em 1440×900 e 390×844; o catálogo selecionou cada categoria e mostrou a ficha correspondente, o quadro ficou oculto em Acessos e voltou em Demandas, sem rolagem horizontal nem erros JavaScript. As capturas renderizadas foram inspecionadas: [desktop 1440×900](evidencias/visual/acessos-tipos-de-usuario-1440.png) e [celular 390×844](evidencias/visual/acessos-tipos-de-usuario-390.png). `node --test tests/workflow.test.js` passou 26/26; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Nenhum dado foi persistido na origem de uso `127.0.0.1:4173`.

### Ações de exportação na interface — 25/09/2026

Em Chromium, numa origem separada e descartável `127.0.0.1:4210`, abri “Mais opções” e acionei “Baixar dados JSON” e “Baixar lista CSV”. Após fechar a janela, a interface exibiu a confirmação específica de cada formato. As oito demandas sintéticas permaneceram inalteradas. O navegador bloqueou a abertura da página interna de downloads; por isso, esta rodada confirma o acionamento e a resposta visual, mas não inspeciona o arquivo gravado. A serialização CSV tem cobertura unitária, inclusive neutralização de fórmulas; a abertura em Excel/Calc e a conferência dos bytes exportados continuam pendentes.

### Conteúdo dos arquivos exportados — 25/09/2026

Em outra origem isolada (`127.0.0.1:4212`), acionei os dois botões e validei os arquivos recém-criados na pasta Downloads pelo horário e sufixo, preservando arquivos anteriores com o mesmo nome. O JSON parseou com schema 1, oito demandas, metadados de exportação e histórico. O CSV foi importado por `Import-Csv`: oito registros, as sete colunas esperadas, BOM UTF-8 e finais de linha CRLF. As mensagens da interface corresponderam ao formato escolhido e o quadro manteve as oito demandas fictícias.

`serializeRequestsJson` e `serializeRequestsCsv` agora concentram a geração de conteúdo para testes determinísticos. `node --test tests/workflow.test.js` passou 24/24. Nenhum arquivo anterior foi sobrescrito ou removido. Na data desta etapa, ainda faltava conferir a apresentação visual do CSV; a conferência posterior consta na seção **Verificação de apresentação no LibreOffice Calc**. A estrutura e os bytes UTF-8 foram verificados aqui.

### Menus de etapa do Kanban — 2026-09-25

Uma auditoria isolada mostrou que o cabeçalho de uma coluna invadia o cabeçalho vizinho: a largura mínima do grid era menor que o `min-width` aplicado depois às colunas. Com isso, menus como “Filtrar planejamento” pareciam visíveis, mas outra coluna interceptava o clique. Alinhei a largura das trilhas do grid com a largura mínima real em desktop, tablet e celular, preservando a rolagem horizontal dentro do quadro.

Chromium/Playwright em perfil efêmero confirmou a geometria e acionou os oito menus, conferindo o nome da etapa em cada diálogo, nas larguras 1440, 1399, 1280, 1250, 900, 768, 600 e 390 px. Em todas elas, a página permaneceu sem overflow horizontal e a rolagem ficou dentro do Kanban. Nenhum erro JavaScript ocorreu. Capturas renderizadas e inspecionadas: [desktop 1440×1000](evidencias/visual/kanban-filtros-colunas-desktop-1440.png) e [celular 390×844](evidencias/visual/kanban-filtros-colunas-mobile-390.png). A origem descartável foi `127.0.0.1:4227`; nenhuma demanda foi salva nem a origem de uso alterada.

### Acessibilidade por teclado do painel de demanda — 2026-09-25

Ao percorrer os botões da interface, encontrei dois problemas de foco: fechado e deslocado por CSS, o painel ainda recebia foco em 20 controles; aberto, Tab escapava para a página escurecida atrás dele. O painel agora tem semântica de diálogo modal, fica `inert` enquanto fechado, e a página e a bandeja ficam inertes enquanto ele está aberto. Tab e Shift+Tab circulam dentro do painel. Ao fechar, o foco retorna à demanda que o abriu; ao minimizar, vai para o atalho criado na bandeja.

Em Chromium isolado, 100 avanços de Tab não focaram nenhum controle quando fechado; ao abrir, o foco foi a Fechar; 100 Tabs e 100 Shift+Tabs permaneceram no diálogo; fechar devolveu o foco ao cartão de origem; minimizar focou o atalho e reabrir o reativou. Não ocorreram erros JavaScript nem overflow horizontal. Renderizei e inspecionei a demanda aberta em [desktop 1440×900](evidencias/visual/demanda-drawer-inert-1440.png) e [celular 390×844](evidencias/visual/demanda-drawer-inert-390.png). Os dados eram fictícios; a origem descartável foi `127.0.0.1:4228` e o perfil de uso `4173` não foi alterado.
