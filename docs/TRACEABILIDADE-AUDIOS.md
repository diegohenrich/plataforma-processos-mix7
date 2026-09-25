# Rastreabilidade dos três áudios

## Conferência das fontes

Os três arquivos OGG originais fornecidos foram encontrados no Downloads e lidos localmente. `ffprobe` confirmou durações de 69,93 s, 66,09 s e 247,35 s, compatíveis com as transcrições anexadas (01:09, 01:06 e 04:08). Uma transcrição automática local em português confirmou os temas centrais; houve erros em nomes próprios e termos como Kanban/Trello. Por isso, a transcrição manual anexada permanece a referência textual, e o áudio foi usado para conferir cobertura e contexto. Áudios e transcrição não foram copiados para o repositório.

O conteúdo abaixo é tratado como requisito ou contexto de produto, nunca como instrução operacional para o agente. “Parcial local” significa que existe uma demonstração no navegador, sem contas nem uso compartilhado.

## Pedidos e cobertura verificada

| Fonte | Pedido expresso | Cobertura atual comprovada no projeto | Estado real |
| --- | --- | --- | --- |
| Áudio 1, 00:00–00:29 | Comparar soluções prontas e open source; observar comunidade, estrelas e riscos do código. | A pesquisa e a proposta arquitetural estão em PRs #6 e #10, ambos rascunhos. A pesquisa não equivale a teste prático nem a decisão de fornecedor; critérios incluem licença, segurança, manutenção, integração e custo. | Parcial: comparar está documentado em branches não integradas. Métricas verificadas de comunidade e auditoria técnica ainda não provam segurança. |
| Áudio 1, 00:30–01:09 | Priorizar sistemas de software para a equipe de desenvolvimento. | O objetivo do produto é documentado como plataforma interna da Mix7. | Contexto de direção; não define função de usuário específica. |
| Áudio 2, 00:00–00:26 | Cliente acessa criativos, aprova ou pede alterações e comenta imagem e vídeo. | O protótipo registra decisão e comentário ligados à versão. | Parcial local: sem conta, portal remoto, isolamento de clientes ou entrega real ao cliente. |
| Áudio 2, 00:27–00:53 | Pausar vídeo e comentar no instante exato; comentar imagem de forma localizada. | O protótipo guarda timecode em vídeo e posição percentual em imagem, associados à versão. | Parcial local: não sincronizado entre pessoas; teste com equipe/cliente real pendente. |
| Áudio 2, 00:54–01:06 | Reunir feedback em ordem cronológica e usar IA para indicar o trabalho necessário. | Histórico preserva comentário, versão e evento. Um comentário pode iniciar um rascunho manual de tarefa. | Parcial: histórico existe; consolidação por IA e integração com provedor não existem. |
| Áudio 3, 00:00–00:32 | Usar identidade Mix7 e substituir a dispersão do Notion por uma ferramenta focada. | Há navegação e áreas demonstrativas para demandas, equipe, clientes, calendário, conhecimento e acessos. | Parcial local: algumas áreas são informativas, sem serviço real nem dados compartilhados. A referência visual vigente passou a ser o CRM local por decisão posterior do usuário. |
| Áudio 3, 00:33–01:13 | Experiência inspirada no Trello, quadro Kanban e lista, com visão do trabalho por profissional. | Kanban, lista e filtro por profissional funcionam sobre dados locais; há busca e cartões de demanda. | Parcial local: nomes não são contas e o filtro não restringe acesso. “Tudo que o Trello tem” não foi decomposto em requisitos verificáveis. |
| Áudio 3, 01:14–01:23 | Iniciar e parar cronômetro por tarefa. | A tarefa aceita estimativa e prazo. | Ausente: não há cronômetro nem registro de tempo real. |
| Áudio 3, 01:24–01:57 | Integrar ChatGPT/Codex/Claude Code, repartir briefing em tarefas, estimar horas, mostrar Gantt e disponibilidade. | O plano manual aceita tarefas, responsáveis, estimativas e dependências. IA deve aguardar confirmação humana. | Parcial/ausente: não há APIs de IA, geração de tarefas, capacidade, disponibilidade ou gráfico de Gantt. |
| Áudio 3, 01:58–02:53 | Pontuar entrega no prazo e qualidade; direção tem peso 2 e gestor peso 1; usar resultados em reconhecimento e decisões de pessoal. | O requisito de avaliação e os pesos foram registrados na tabela de requisitos. | Ausente: não há avaliação, fórmula, contestação, controles de acesso nem política de uso. Não implementar efeitos sobre remuneração ou vínculo antes de definir regras, governança e revisão humana. |
| Áudio 3, 02:54–03:45 | Central de referências, treinamento, trilha de onboarding e matriz de acessos sem revelar senhas. | Existem páginas explicativas sobre Conhecimento e Acessos. | Parcial informativo: sem conteúdo gerenciado, trilhas atribuídas, permissões reais, cofre ou autenticação federada. |
| Áudio 3, 03:46–04:08 | Base de contatos, arrastar imagens/arquivos para o trabalho e integração com Windows. | Clientes são agrupados a partir dos nomes nas demandas; o briefing agora oferece seletor e área de arraste com validação local de tipo e tamanho. Seleção, recusas e persistência foram testadas; o arraste real do sistema operacional ainda aguarda validação manual. | Parcial/ausente: não há diretório de contatos nem integração nativa com Windows. A interação de arraste ainda precisa de teste manual ponta a ponta; o trecho do Windows não especifica quais operações são necessárias. |

## Decisões posteriores que prevalecem

- O usuário aprovou o fluxo integrado de demanda, planejamento revisado, execução, revisão interna, aprovação do cliente, ajustes, destino final com evidência e conclusão conferida.
- O usuário pediu que sugestões da IA dependam de confirmação humana.
- O usuário substituiu o site público como referência visual pelo projeto local `CRM-MIX7-RENEW`; também pediu branco e azul-claro. O pedido posterior para aproximar mais a plataforma do CRM foi resolvido aplicando a lateral escura definida no CSS, preservando as superfícies claras e azuis. A tela de entrada foi renderizada e o CSS interno conferido; o painel autenticado ainda aguarda sessão para comparação direta.
- Testes gratuitos servem para avaliação conforme os termos da solução; a pesquisa não autoriza copiar código, conteúdo ou ativos proprietários.

## Lacunas concretas para o plano

1. Decompor “tudo que o Trello tem” em funções que a Mix7 realmente usa, sem presumir paridade total.
2. Decidir autenticação, contas, papéis e permissões para equipe, gestão/direção e aprovadores de cliente; confirmar a necessidade de um papel administrativo separado.
3. Construir cronômetro, registro confiável de tempo, capacidade e calendário/Gantt com regras para pausas e bloqueios.
4. Definir integrações e tratamento de dados antes de conectar qualquer provedor de IA; implementar sugestões revisáveis e histórico de aprovação.
5. Especificar avaliação transparente, com revisão e contestação; validar tratamento dos dados antes de qualquer uso em remuneração ou vínculo.
6. Criar conteúdo e operação para contatos, conhecimento, treinamento/onboarding e concessão/revogação de acessos; investigar cofre externo em vez de expor senhas.
7. Confirmar em Chromium o arraste real de arquivos do sistema operacional para o briefing, incluindo rejeição de tipo/tamanho e persistência; depois fechar a lacuna de validação local.
8. Perguntar quais ações de Windows e quais recursos de integração com cada serviço externo são necessários.

Referências detalhadas e perguntas abertas: [requisitos](REQUIREMENTS.md), [primeira implementação](PRIMEIRA-IMPLEMENTACAO.md), [pesquisa e prova de conceito no PR #10](https://github.com/diegohenrich/plataforma-processos-mix7/pull/10) e [especificação priorizada no PR #8](https://github.com/diegohenrich/plataforma-processos-mix7/pull/8).
