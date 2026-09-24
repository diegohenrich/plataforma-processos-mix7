# Requisitos priorizados

**Estado:** baseline para especificar e validar a primeira entrega. Prioridades são propostas de produto baseadas nos três áudios e no fluxo-alvo aceito; não comprovam a operação atual da Mix7 nem autorizam decidir política de equipe sem validação.

## Evidências e níveis de certeza

- **Áudio:** necessidade dita diretamente pelo usuário nos três áudios de 24/09/2026.
- **Fluxo-alvo aceito:** proposta de funcionamento aceita pelo usuário; define para onde o produto deve apontar, não como a Mix7 trabalha hoje.
- **Pendente:** precisa de exemplo real, regra de negócio ou decisão operacional da Mix7.
- **Prioridade:** P0 = necessário para demonstrar a primeira jornada integrada; P1 = importante, fase seguinte ou depende de validar capacidade/integração; P2 = fase posterior, decisão sensível ou necessidade ainda ampla. P0/P1/P2 ordenam o trabalho, não substituem a aprovação dos requisitos.

## Primeira jornada integrada

Registrar uma demanda com briefing → revisar e confirmar plano e atribuições → executar tarefas → revisar internamente conforme o fluxo-alvo → enviar uma versão específica ao aprovador do cliente → aprovar ou pedir ajustes com comentários ligados à versão → se houver ajustes, criar nova versão e repetir revisão/aprovação → após aceite, registrar publicação/agendamento ou entrega e evidência → responsável pela conta confirma conclusão.

A IA pode preparar rascunhos de plano e de lista de ajustes, mas não muda o fluxo sem confirmação humana. O desenho de revisão interna obrigatória e do critério de conclusão é parte do fluxo-alvo aceito; o caso real ainda precisa confirmar papéis, exceções e quais evidências encerram cada tipo de serviço.

## Requisitos P0 — primeira jornada

| ID | Requisito e aceite verificável | Fonte / estado |
| --- | --- | --- |
| MVP-01 | Uma demanda fica vinculada a cliente/projeto, origem do pedido, briefing, tipo de entrega, canal/peça, prazo pretendido, arquivos/referências e critérios de aceite. Os campos obrigatórios podem variar por tipo; a tela mostra o que falta antes do planejamento, e a equipe pode registrar a origem mesmo quando o pedido veio de fora do sistema. | Fluxo-alvo; campos obrigatórios pendentes por tipo. |
| MVP-02 | A equipe acompanha demandas em Kanban e lista, e pode filtrar o trabalho por profissional. O mesmo item, estado e atualização aparecem nas duas visões. A decisão entre filtro pessoal e restrição de acesso é pendente; uma visão filtrada nunca concede ao cliente acesso interno. | Áudio 3; escopo de acesso pendente. |
| MVP-03 | Uma demanda pode conter tarefas com responsável, estado, dependência, estimativa, prazo e arquivos; a execução mantém vínculo com a demanda e registra impedimentos. É possível atribuir e editar o plano manualmente mesmo quando IA ou integração estiver indisponível. | Áudio 3 e fluxo-alvo; estados e exceções pendentes. |
| MVP-04 | IA pode transformar briefing em proposta de tarefas, sequência, responsáveis e estimativas; proposta é rascunho, pode ser editada/rejeitada e só vira plano após confirmação de uma pessoa autorizada. Sem confirmação, nenhuma tarefa é atribuída automaticamente. | Áudio 3 e decisão humana explícita. |
| MVP-05 | Cada arquivo enviado para revisão tem versão identificável e imutável para decisões. A tela permite consultar versões anteriores e saber qual foi enviada, por quem e quando. | Áudio 2; formatos, tamanho, armazenamento e retenção pendentes. |
| MVP-06 | A revisão do cliente permite decisão explícita **Aprovar** ou **Solicitar alterações**, comentário e registro de autor, hora e versão. Comentário sem decisão deixa a revisão pendente; silêncio não aprova. O cliente acessa somente materiais destinados à sua revisão, sujeito à confirmação da matriz detalhada de permissões. | Áudio 2 e fluxo-alvo; papéis concretos pendentes. |
| MVP-07 | Comentários continuam associados à versão exata. Vídeo permite pausar e registrar timecode; imagem permite comentário vinculado ao arquivo e à versão. Se nova versão mudar o contexto, comentários e decisões antigas permanecem legíveis e não são transferidos silenciosamente para outro conteúdo. | Áudio 2; ancoragem espacial em imagem pendente. |
| MVP-08 | Ao solicitar alteração, o sistema preserva a decisão e os comentários, devolve a demanda à equipe, permite consolidar feedback cronologicamente e inicia nova rodada com nova versão. A lista de ajustes gerada por IA é revisável antes de virar tarefas. | Áudio 2 e fluxo-alvo aceito. |
| MVP-09 | O sistema registra etapa interna de revisão antes do envio ao cliente, com resultado e responsável; falha retorna a peça à execução. O fluxo-alvo prevê revisor diferente do produtor quando possível e gestor como substituto. | Fluxo-alvo aceito; confirmar exceções no caso real. |
| MVP-10 | Aprovação do cliente não encerra automaticamente a demanda. O responsável registra se houve agendamento, publicação ou entrega, com data/canal e evidência aplicável; só então o responsável indicado confirma conclusão. Se publicação falhar, demanda continua aberta. | Fluxo-alvo aceito; definição por tipo de serviço pendente. |
| MVP-11 | Mudanças de briefing, tarefas, responsável, prazos, arquivos/versões, comentários, decisão, reenvio e encerramento preservam autoria, horário e histórico recuperável. | Necessário para a rastreabilidade descrita nos áudios e no fluxo-alvo. |
| MVP-12 | A estrutura de domínio permite adicionar outros processos de aprovação sem duplicar cliente, demanda, usuários, permissões, arquivos e histórico. Um módulo pode adicionar formulário, estados e evidências específicos; aprovação de criativos é o primeiro. | Pedido explícito de expansão lateral; categorias futuras pendentes. |

## Requisitos P1 — gestão e integrações seguintes

| ID | Requisito | Dependência antes de implementação |
| --- | --- | --- |
| SIG-01 | Cronômetro iniciar/parar por tarefa e registrar horas previstas e realizadas. | Regras para pausa, lançamento manual, simultaneidade e correção de horas. |
| SIG-02 | Mostrar disponibilidade da equipe e planejar capacidade/dependências em Gantt. | Calendário, ausências, estimativas e prazo externo acordados. |
| IA-01 | Conectar a provedores escolhidos pelo usuário (ChatGPT/OpenAI, Codex, Claude/Claude Code), com uso assistivo e confirmação humana. | Definir casos de uso, credenciais, custos e quais dados podem sair do ambiente Mix7. |
| PUB-01 | Registrar/automatizar agendamento e publicação social depois da aprovação. | Redes/canais, permissões OAuth, publicação manual vs. automática e prova de publicação. |
| OPS-01 | Notificações e prazos de revisão para equipe e aprovadores. | Canal, preferências, lembretes, escalonamento e fuso horário. |
| DESK-01 | Arrastar arquivos/imagens e interoperar com ambiente Windows. | Significado de “interligação com Windows”, navegadores, seleção de arquivo, limites e permissões do desktop. |

## Requisitos P2 — políticas e módulos posteriores

| ID | Requisito | Por que fica depois |
| --- | --- | --- |
| TEAM-01 | Pontuação por prazo, volume e qualidade, com avaliações de direção peso 2 e gestor peso 1. | A escala, fórmula, contexto de bloqueios, direito de resposta, visibilidade, retenção e uso em recompensa/advertência não estão definidos. Nenhuma nota deve produzir decisão de remuneração ou desligamento automaticamente. |
| KNOW-01 | Central de referências, contatos e treinamentos, com trilhas de onboarding em passos. | Confirmar responsáveis por conteúdo, versão, público, conclusão e atualização. |
| SEC-01 | Matriz de acesso a ferramentas e fluxo para autenticação sem revelar senha real. | Inventariar serviços; avaliar SSO/OAuth ou cofre de segredos existente, rotação, auditoria, recuperação e revogação antes de projetar armazenamento de credenciais. |
| APP-01 | Aprovação por campanha/lote, aprovação parcial, múltiplos níveis, limites de rodada, reabertura, alteração após aceite e se “reprovar” é decisão diferente de “solicitar alterações”. | Validar se a operação da Mix7 usa esses cenários antes de fixar estados e regras. |

## Requisitos de qualidade antes de produção

- **Segurança e privacidade:** isolamento por cliente e papel; anexos sem link público permanente; transporte e armazenamento protegidos; trilha de auditoria; política de retenção e exclusão definida antes de carregar dados reais.
- **Confiabilidade:** backup e restauração demonstrados; falha de IA não bloqueia o trabalho; uma submissão não pode aprovar versão diferente da que o cliente viu.
- **Usabilidade:** interface focada em branco e azul claros, referência visual Mix7; Kanban/lista coerentes; experiência conferida em desktop e largura móvel definida pela equipe.
- **Acessibilidade e compatibilidade:** critérios, navegadores suportados, formatos e limites de arquivo ainda devem ser definidos antes do piloto.
- **Portabilidade e operação:** exportação de demandas, arquivos, comentários e decisões; proprietário de armazenamento, região e custo definidos antes de escolher fornecedor.

## Decisões operacionais ainda necessárias

1. Um caso real da Mix7: como entrou, quem consolidou briefing e plano, quem executou/revisou, qual foi a resposta do cliente e que evidência marcou o final.
2. Papéis concretos, substituições, quem vê/edita/aprova/reabre e limite do portal de cliente.
3. Campos obrigatórios por tipo de entrega, prazos de resposta e como tratar urgência, mudança de escopo e atraso causado por cliente/terceiro.
4. Aprovação por peça ou campanha, aprovação parcial, rodadas incluídas e alteração posterior ao aceite.
5. Critério de conclusão por serviço: arquivo entregue, post agendado, publicado ou outro resultado.
6. Horas e disponibilidade: pausas, concorrência de tarefas, ausência, prazo externo e revisão de estimativa.
7. Política de avaliação de desempenho, contestação e uso das notas; integrações/credenciais e dados que podem ser enviados a IA.
8. Destino de arquivos e requisitos de Windows, compatibilidade, tamanhos de tela, formatos e limites.

## Processo de pesquisa e propriedade intelectual

O áudio 1 pede evitar começar do zero e avaliar soluções existentes/open source, inclusive períodos grátis. Testes gratuitos servem para entender fluxos e validar adequação segundo os termos do fornecedor; não autorizam copiar código, marca, telas ou ativos proprietários. Para open source, comparar licença, atividade de manutenção, comunidade, dependências, avisos de segurança, processo de release, hospedagem, backup e custo total antes de selecionar.

## Rastreabilidade com os cartões do Trello

| Requisitos | Cartão canônico |
| --- | --- |
| MVP-01, MVP-09, MVP-10 | [Percurso real da demanda](https://trello.com/c/7ZHbl0kH/4-mapear-o-percurso-real-de-uma-demanda-na-mix7) |
| MVP-02, MVP-03, SIG-01, SIG-02 | [Tarefas, Kanban, lista, tempo e capacidade](https://trello.com/c/C6J7q7bP/8-definir-tarefas-kanban-lista-tempo-e-capacidade) |
| MVP-03, MVP-06, MVP-09, MVP-10 | [Papéis, visibilidade e permissões](https://trello.com/c/8XDNkPFF/5-definir-pap%C3%A9is-visibilidade-e-permiss%C3%B5es) |
| MVP-04, MVP-08, IA-01 | [Entrada de demandas, IA e integrações](https://trello.com/c/mYwcNprU/12-definir-entrada-de-demandas-ia-e-integra%C3%A7%C3%B5es) |
| MVP-06, MVP-08, MVP-09, MVP-10, APP-01 | [Etapas e exceções de aprovação](https://trello.com/c/RrTq5VH6/6-definir-etapas-e-exce%C3%A7%C3%B5es-da-aprova%C3%A7%C3%A3o-do-cliente) |
| MVP-05, MVP-06, MVP-07, MVP-08 | [Versões e comentários em imagem/vídeo](https://trello.com/c/CugVr9g4/7-definir-vers%C3%B5es-de-arquivos-e-coment%C3%A1rios-em-imagem-e-v%C3%ADdeo) |
| TEAM-01 | [Avaliação de prazo, produção e qualidade](https://trello.com/c/vBPwTyk3/9-definir-avalia%C3%A7%C3%A3o-de-prazo-produ%C3%A7%C3%A3o-e-qualidade) |
| KNOW-01 | [Central de conhecimento e onboarding](https://trello.com/c/4V8fFh5l/10-definir-central-de-conhecimento-e-onboarding) |
| SEC-01 | [Gestão de acessos sem exposição de senhas](https://trello.com/c/QQ09Z3Xk/11-definir-gest%C3%A3o-de-acessos-sem-exposi%C3%A7%C3%A3o-de-senhas) |
| Interface, arquivos e DESK-01 | [Experiência visual e requisitos do desktop](https://trello.com/c/zlSQhiwa/13-definir-experi%C3%AAncia-visual-e-requisitos-do-desktop) |

O detalhamento e os critérios de aceite da jornada estão em [ESPECIFICACAO-MVP.md](ESPECIFICACAO-MVP.md); o cartão de entrega é [Especificar o fluxo integrado da primeira versão](https://trello.com/c/g4qmC1HI/15-especificar-o-fluxo-integrado-da-primeira-vers%C3%A3o).

Referências de gestão no [Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7). O [fluxo proposto](FLUXO-PROPOSTO.md) define comportamento-alvo; casos reais definem variações operacionais ainda desconhecidas.
