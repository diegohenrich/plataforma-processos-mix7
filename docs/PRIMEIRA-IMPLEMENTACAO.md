# Primeira implementação: fluxo local de demandas

**Estado:** fatia funcional para demonstração e validação; não é ambiente de produção nem decisão de stack.

## O que já executa

A tela `prototipo/` mantém os cartões no quadro e na lista, registra briefing, busca e detalhes, permite filtrar demandas com tarefas pendentes da rodada atual atribuídas a um profissional, planejar tarefas com responsável, estimativa e prazo, registrar dependências entre tarefas da mesma rodada e sinalizar impedimentos com motivo durante a execução. Dependências e impedimentos bloqueiam a conclusão até serem resolvidos; alterações entram no histórico. Aceita arquivo local de imagem, vídeo ou PDF de até 15 MB, cria versões, grava comentários e eventos, aplica decisões e avança a demanda pelas regras do fluxo. Comentários podem ser ligados a um ponto percentual da imagem ou ao instante pausado do vídeo; atalhos no histórico abrem a versão correspondente.

## Cobertura dos requisitos P0

O baseline P0 está na [especificação priorizada em revisão no PR #8](https://github.com/diegohenrich/plataforma-processos-mix7/blob/spec/requisitos-priorizados/docs/REQUIREMENTS.md). “Parcial local” indica comportamento demonstrável neste navegador; não significa requisito pronto para operação.

| Requisito | Cobertura atual no protótipo | Limite ainda aberto |
| --- | --- | --- |
| MVP-01 Briefing | Parcial local: cliente, tipo, origem, canal/peça, critérios e opcionais de prazo, links e arquivos de referência; bloqueia planejamento se os campos mínimos faltarem. Arquivos de referência ficam separados da versão de entrega e podem ser baixados no painel. | Campos obrigatórios por serviço não são configuráveis; prazo e links não são validados; arquivo, nome e metadados dependem do mesmo perfil local do navegador. |
| MVP-02 Visões e trabalho por pessoa | Parcial local: Kanban, lista, busca e filtro das demandas com tarefas pendentes da rodada atual por profissional. | O filtro usa nomes de texto nas tarefas, não mostra uma fila própria de tarefas e não impõe permissão de acesso. |
| MVP-03 Tarefas | Parcial local: responsável, status, estimativa, prazo e rodada; dependências múltiplas dentro da rodada; impedimento com motivo e histórico. | Uma tarefa concluída só pode ser reaberta após reabrir tarefas que dependem dela. Sem notificações, cronômetro, capacidade, identidade ou coordenação entre usuários. |
| MVP-04 IA no planejamento | Não implementado; o plano continua manualmente editável. | Provedor, dados enviados e experiência de confirmação humana ainda precisam de definição. |
| MVP-05 Versões | Parcial local: versões e arquivos no IndexedDB do navegador. Se um rascunho for substituído antes do envio, o histórico mantém o arquivo anterior disponível para baixar. | Sem armazenamento central ou auditoria imutável; rascunhos anteriores dependem do mesmo perfil do navegador. |
| MVP-06 Decisão do cliente | Parcial local: aprovar ou solicitar ajustes em versão compartilhada. | Sem identidade autenticada, isolamento por cliente ou portal externo. |
| MVP-07 Comentários de mídia | Parcial local: comentário por versão, ponto percentual de imagem e timecode de vídeo. | A referência depende do mesmo perfil local; acesso remoto e armazenamento seguro de mídia faltam. |
| MVP-08 Ajustes e nova versão | Parcial local: comentário e decisão permanecem na versão; em ajustes, um comentário do cliente pode iniciar um rascunho editável de tarefa, mantendo vínculo ao feedback e à versão. A equipe confirma a descrição e atribui responsável antes de criar. | Consolidação cronológica assistida por IA não existe; não há geração nem atribuição automática de tarefas. |
| MVP-09 Revisão interna | Parcial local: a etapa bloqueia compartilhamento até aprovar; devolução pede motivo. | Papel e identidade do revisor não são verificados; exceções e substituições não são configuráveis. |
| MVP-10 Entrega e conclusão | Parcial local: resultado pós-aprovação registrado como material entregue, publicação agendada ou material publicado; exige evidência e mostra o tipo no histórico/quadro. | Sem integração externa, data/destino ou metadados próprios do serviço; a evidência é texto livre local. |
| MVP-11 Histórico | Parcial local: transições ligam versão, arquivo, comentário/motivo, tarefa e resultado com horário, identificadores e resumo legível. Substituição mostra o rascunho anterior e permite baixá-lo quando disponível. | Dados e autoria ficam no navegador; sem identidade confiável, histórico central, retenção ou proteção contra alteração local. |
| MVP-12 Módulos futuros | Não implementado: estados do fluxo estão definidos no código. | Aprovações configuráveis e cadastros compartilhados ainda são arquitetura conceitual, não comportamento executável. |

O filtro de MVP-02 é uma demonstração da visão individual solicitada; ele combina com quadro/lista e busca e esconde demandas sem tarefa pendente atribuída à pessoa selecionada, mas não é segurança. A matriz de permissões e as funções reais dependem da definição de papéis e da arquitetura de produção.

No briefing inicial, a demonstração coleta origem do pedido, canal/peça e critérios de aceite. A demanda não segue para planejamento enquanto faltar um desses dados; referências, prazo e arquivos de referência podem ficar vazios. Arquivos opcionais de imagem, vídeo ou PDF (até 15 MB cada nesta demonstração) ficam separados do criativo final e são recuperáveis pelo painel enquanto permanecerem no IndexedDB deste navegador. A criação registra nome, tipo e tamanho no histórico. Alterações nos campos iniciais são preservadas no histórico. Esta regra mínima e os limites de arquivo ainda precisam de validação por tipo de serviço com um caso real da Mix7.

O caminho implementado é:

`Briefing → Planejamento → Produção → Revisão interna → Aprovação do cliente → (Ajustes → nova versão → revisão) → Entrega/publicação → Concluída`

Durante a execução, a equipe pode registrar uma alteração do briefing e seu motivo. A demanda retorna a planejamento, preserva o texto anterior e pausa a execução. Uma pessoa precisa revisar o briefing e as tarefas e confirmar o plano para retomar. Esta regra é exercitada com dados fictícios; ainda não há permissões reais que identifiquem a pessoa revisora.

O plano exige ao menos uma tarefa atribuída; revisão interna exige que as tarefas da rodada estejam concluídas e que haja um arquivo. O cliente só decide uma versão compartilhada após a revisão interna. Pedido de ajustes requer justificativa e fica preso à versão; a nova versão exige tarefas da rodada de ajuste concluídas e preserva a decisão anterior. Após a aprovação, a demonstração registra se houve entrega, agendamento ou publicação e exige evidência antes de concluir; não executa essas ações em canais externos.

## Implementação atual

- Interface: HTML, CSS e JavaScript nativos, reutilizando o protótipo visual.
- Regras de transição puras e testadas em `prototipo/workflow.js`.
- Demandas, versões, comentários e trilha local em `localStorage` (`mix7.workflow.v1`).
- Arquivos binários em `IndexedDB` (`mix7.workflow.assets`).
- Sem dependências de build; roda servindo a pasta `prototipo/` por HTTP local.
- Itens iniciais são ficcionais; comentários e material ilustrativo também são fictícios.

## Limites e tratamento de dados

O navegador serve apenas para validar comportamento. Cada perfil de navegador tem dados próprios, sem sincronização ou backup gerenciado. Limpar os dados do site pode removê-los. Não use conteúdo de clientes, credenciais ou informação pessoal. O filtro por profissional é uma conveniência visual local; não limita o acesso a dados e não substitui login, isolamento por cliente ou autorização no servidor. Também não há portal do cliente, trilha de auditoria à prova de adulteração, controle de retenção ou recuperação de backup. Os controles locais não constituem aprovação enviada ao cliente.

O ponto em imagem usa coordenadas percentuais relativas à mídia e o vídeo registra o instante em segundos; são comportamentos locais de demonstração a validar com a equipe. Os arquivos precisam estar presentes neste mesmo perfil do navegador para abrir a referência. As tarefas usam nomes livres e não são contas de membros: não há gestão de equipe/autorização, cronômetro ou capacidade. Também não há notificações, IA, integrações, nem registro separado de agendamento vs. publicação. Esses requisitos seguem na especificação priorizada.

## Verificação

Executar da raiz:

```powershell
node --test tests/workflow.test.js
node --check prototipo/workflow.js
node --check prototipo/app.js
```

Os testes cobrem briefing incompleto e registro histórico de correções, plano com tarefa atribuída, dependências entre tarefas, reabertura ordenada, impedimento com motivo/histórico, bloqueio de revisão com tarefas incompletas, fluxo feliz, aprovação de versão não compartilhada, motivo obrigatório na revisão interna, pedido de alteração ligado à versão antiga, preservação do histórico na versão seguinte, âncoras de imagem/vídeo, alteração de briefing com retomada bloqueada até revisão humana, evidência obrigatória e metadados de auditoria por arquivo, decisão, comentário e versão. Em Chromium, dependência, impedimento, pedido de ajuste, geração da próxima versão e aprovação interna foram percorridos; histórico mostra versão, nome de arquivo e comentário/motivo. Viewport de 390×844: painel e documento sem overflow horizontal; resumo de comentário longo limitado visualmente e screenshot móvel inspecionado. Console sem avisos/erros. A integração ainda requer validação da equipe Mix7.

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
