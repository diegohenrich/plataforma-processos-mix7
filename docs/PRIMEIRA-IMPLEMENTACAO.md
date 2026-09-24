# Primeira implementação: fluxo local de demandas

**Estado:** fatia funcional para demonstração e validação; não é ambiente de produção nem decisão de stack.

## O que já executa

A tela `prototipo/` mantém os cartões no quadro e na lista, registra briefing, busca e detalhes, permite filtrar demandas com tarefas pendentes da rodada atual atribuídas a um profissional, planejar tarefas com responsável, estimativa e prazo, acompanhar sua conclusão, aceita arquivo local de imagem, vídeo ou PDF de até 15 MB, cria versões, grava comentários e eventos, aplica decisões e avança a demanda pelas regras do fluxo. Comentários podem ser ligados a um ponto percentual da imagem ou ao instante pausado do vídeo; atalhos no histórico abrem a versão correspondente.

## Cobertura dos requisitos P0

O baseline P0 está na [especificação priorizada em revisão no PR #8](https://github.com/diegohenrich/plataforma-processos-mix7/blob/spec/requisitos-priorizados/docs/REQUIREMENTS.md). “Parcial local” indica comportamento demonstrável neste navegador; não significa requisito pronto para operação.

| Requisito | Cobertura atual no protótipo | Limite ainda aberto |
| --- | --- | --- |
| MVP-01 Briefing | Parcial local: cliente, tipo, origem, canal/peça e critérios; bloqueia planejamento se campos mínimos faltarem. | Obrigatoriedade por serviço não é configurável; arquivos não entram no formulário inicial; prazo e referências não são validados. |
| MVP-02 Visões e trabalho por pessoa | Parcial local: Kanban, lista, busca e filtro das demandas com tarefas pendentes da rodada atual por profissional. | O filtro usa nomes de texto nas tarefas, não mostra uma fila própria de tarefas e não impõe permissão de acesso. |
| MVP-03 Tarefas | Parcial local: responsável, status, estimativa, prazo e rodada. | Dependências e sinalização de impedimento ainda não estão implementadas. |
| MVP-04 IA no planejamento | Não implementado; o plano continua manualmente editável. | Provedor, dados enviados e experiência de confirmação humana ainda precisam de definição. |
| MVP-05 Versões | Parcial local: versões e arquivos no IndexedDB do navegador. | Sem armazenamento central e auditoria imutável; um arquivo ainda pode ser substituído na mesma versão antes do envio. |
| MVP-06 Decisão do cliente | Parcial local: aprovar ou solicitar ajustes em versão compartilhada. | Sem identidade autenticada, isolamento por cliente ou portal externo. |
| MVP-07 Comentários de mídia | Parcial local: comentário por versão, ponto percentual de imagem e timecode de vídeo. | A referência depende do mesmo perfil local; acesso remoto e armazenamento seguro de mídia faltam. |
| MVP-08 Ajustes e nova versão | Parcial local: comentário e decisão permanecem na versão; tarefas manuais alimentam a nova rodada. | Consolidação cronológica assistida por IA não existe; tarefas não são criadas automaticamente do feedback. |
| MVP-09 Revisão interna | Parcial local: a etapa bloqueia compartilhamento até aprovar; devolução pede motivo. | Papel e identidade do revisor não são verificados; exceções e substituições não são configuráveis. |
| MVP-10 Entrega e conclusão | Parcial local: aprovação conduz à entrega, e conclusão exige evidência textual. | Não diferencia entrega, agendamento e publicação nem exige metadados próprios por destino. |
| MVP-11 Histórico | Parcial local: transições e várias mudanças são registradas no navegador. | Sem identidade confiável, histórico central ou proteção contra alteração local. |
| MVP-12 Módulos futuros | Não implementado: estados do fluxo estão definidos no código. | Aprovações configuráveis e cadastros compartilhados ainda são arquitetura conceitual, não comportamento executável. |

O filtro de MVP-02 é uma demonstração da visão individual solicitada; ele combina com quadro/lista e busca e esconde demandas sem tarefa pendente atribuída à pessoa selecionada, mas não é segurança. A matriz de permissões e as funções reais dependem da definição de papéis e da arquitetura de produção.

No briefing inicial, a demonstração coleta origem do pedido, canal/peça e critérios de aceite. A demanda não segue para planejamento enquanto faltar um desses dados; referências e prazo podem ficar vazios. Alterações nos campos iniciais são preservadas no histórico. Esta regra é uma hipótese mínima da demonstração, ainda sujeita a validação por tipo de serviço com um caso real da Mix7.

O caminho implementado é:

`Briefing → Planejamento → Produção → Revisão interna → Aprovação do cliente → (Ajustes → nova versão → revisão) → Entrega/publicação → Concluída`

Durante a execução, a equipe pode registrar uma alteração do briefing e seu motivo. A demanda retorna a planejamento, preserva o texto anterior e pausa a execução. Uma pessoa precisa revisar o briefing e as tarefas e confirmar o plano para retomar. Esta regra é exercitada com dados fictícios; ainda não há permissões reais que identifiquem a pessoa revisora.

O plano exige ao menos uma tarefa atribuída; revisão interna exige que as tarefas da rodada estejam concluídas e que haja um arquivo. O cliente só decide uma versão compartilhada após a revisão interna. Pedido de ajustes requer justificativa e fica preso à versão; a nova versão exige tarefas da rodada de ajuste concluídas e preserva a decisão anterior. A aprovação conduz a entrega, mas só há conclusão após registrar evidência.

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

Os testes cobrem briefing incompleto e registro histórico de correções, plano com tarefa atribuída, bloqueio de revisão com tarefas incompletas, fluxo feliz, aprovação de versão não compartilhada, motivo obrigatório na revisão interna, pedido de alteração ligado à versão antiga, preservação do histórico na versão seguinte, âncoras de imagem/vídeo, alteração de briefing com retomada bloqueada até revisão humana e evidência obrigatória para concluir. Na inspeção em Chromium com dados fictícios, a alteração de briefing voltou a demanda ao planejamento, mostrou texto anterior e motivo, e só retomou após confirmar o plano; esses dados persistiram após recarregar. Viewport de 390×844 teve largura de documento de 390 px, sem overflow horizontal, e console sem avisos/erros. A seleção de arquivo foi exercitada no fluxo normal do navegador em validação anterior. A integração ainda requer validação da equipe Mix7.

## Próximas decisões

Antes de usar dados reais, fechar o caso real da Mix7 e sua matriz de papéis; escolher persistência central, autenticação, isolamento, hospedagem, backup, retenção e integração de arquivos; validar o comportamento das âncoras com equipe e cliente. A escolha final de tecnologias deve seguir a pesquisa e as restrições operacionais.
