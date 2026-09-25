# Requisitos em levantamento

Fonte: transcrição dos três áudios fornecidos em 24/09/2026 e decisões explícitas da conversa. “Confirmado” indica intenção expressa, não especificação pronta para desenvolvimento. Questões abertas precisam ser resolvidas com exemplos reais da operação.

## Confirmado pelo pedido e pelos áudios

| Área | Necessidade expressa |
| --- | --- |
| Plataforma | Integrar gestão de equipe com módulos de aprovação expansíveis para áreas da agência. |
| Gestão | Kanban e lista, visão das demandas por profissional e interação simples com arquivos/imagens. |
| Tempo e planejamento | Cronômetro por tarefa, horas previstas, disponibilidade da equipe e gráfico de Gantt. |
| Aprovação | Cliente aprova, desaprova e comenta materiais de imagem e vídeo. |
| Vídeo | Comentário vinculado ao instante em que o vídeo foi pausado. |
| IA | Sugerir decomposição de briefing em tarefas, responsáveis e estimativas; organizar feedbacks cronologicamente. Sugestões passam por revisão humana antes da aplicação. |
| Avaliação | Medir prazo, produção e qualidade; notas da direção com peso 2 e do gestor com peso 1, conforme fala do áudio. |
| Conhecimento | Referências, treinamentos, contatos e trilha de onboarding em passos. |
| Acessos | Matriz por função e possibilidade de usar serviços sem revelar senhas, quando tecnicamente viável. |
| Experiência | Foco em menos opções que o Notion, inspiração na experiência do Trello e referência visual da Mix7. Preferência da interface: branco e azul claros. |
| Arquivos e ambiente de trabalho | Soltar imagens/arquivos em uma demanda e mantê-los associados a ela; integração com Windows foi pedida, mas ainda sem comportamento definido. |
| Construção | Comparar soluções existentes e opções open source antes de decidir o que desenvolver. Avaliar licenças, manutenção e segurança. |

## Decisões confirmadas para o primeiro recorte

- Público: equipe e clientes da Mix7.
- Fluxo-alvo aprovado: demanda/briefing → planejamento revisado → execução → revisão interna → aprovação do cliente → ajustes e nova versão quando pedidos → entrega/agendamento/publicação com evidência → conclusão conferida. Validar no caso real da Mix7 quem exerce cada papel, as exceções e as evidências exigidas por serviço; não reabrir o fluxo-alvo sem nova evidência ou decisão explícita.
- IA: sugere; uma pessoa revisa antes de criar ou distribuir tarefas.

## Tipos de usuário e papéis de trabalho

Os áudios dão evidência de quatro categorias de participante: a pessoa que fala como responsável pela Mix7, o gerente citado, o profissional da equipe e o cliente que aprova. As falas não definem os cargos formais de quem fala ou do gerente. São categorias respaldadas pelo áudio, não contas nem permissões configuradas.

O fluxo aprovado exige identificar, em cada demanda, quem responde pelo briefing, executa tarefas, faz a revisão interna e confere a entrega ou publicação. A pessoa que valida o planejamento também precisa ser identificada. Essas funções descrevem o que alguém faz naquele processo; uma pessoa pode acumular funções. O aprovador é designado pelo cliente para a versão compartilhada. A fala “cada profissional vê o que é para fazer” (áudio 3, 00:42–00:54) confirma uma visão individual do trabalho, mas ainda não define se os demais dados ficam ocultos por segurança.

### O que os áudios permitem afirmar sobre cada categoria

| Categoria mencionada | Capacidade explicitamente citada | Ainda não definido |
| --- | --- | --- |
| Responsável que fala como dono/representante da Mix7 | Avaliar prazo e qualidade; a avaliação pessoal tem peso 2 (áudio 3, 01:58–02:53). | Cargo formal, acesso administrativo, ações no fluxo e critérios de avaliação. “Direção” é um rótulo provisório, não um cargo confirmado. |
| Gerente | Avaliar a qualidade com peso 1 (áudio 3, 02:18–02:53). | Autoridade para distribuir tarefas, acesso administrativo, ações no fluxo e critérios de avaliação. Atribuição de tarefas pelo gerente não foi estabelecida nessa fala. |
| Profissional da equipe | Ver o trabalho destinado à própria pessoa; são dados como exemplo um programador chamado Diego (áudio 3, 00:42–00:54). | Se essa visão é filtro pessoal ou limite de segurança e quais outros dados ou ações ficam disponíveis. O exemplo não autoriza criar uma conta real para Diego. |
| Cliente/aprovador | Acessar materiais enviados à aprovação, aprovar ou reprovar e comentar imagem ou vídeo (áudio 2, 00:00–00:41). | Como autenticar e vincular o aprovador, isolamento entre clientes, notas internas, rodadas, aprovações parciais e acesso após a decisão. |

Administrador técnico, convites, recuperação e revogação de contas são necessidades do produto a especificar; não aparecem como capacidades atribuídas a uma dessas categorias nos áudios. Também não há nomes, e-mails ou autorização para provisionar usuários reais.

Antes de criar contas operacionais, ainda é necessário decidir autenticação, administração da plataforma, associação de usuários internos à agência e aprovadores às contas de cliente, convites/recuperação, remoção e a matriz de leitura/escrita/aprovação/reabertura. O áudio menciona pontos por prazo e avaliações de qualidade, com pesos 2 e 1; não especifica fórmula, contestação ou governança. Não habilitar notas, remuneração ou medida disciplinar sem critérios e política de uso. Até as decisões e os dados de provisionamento existirem, os nomes livres do protótipo continuam demonstrativos e não são contas.

## Questões abertas que impedem especificação final

1. Em um caso real da Mix7, como se aplicam as etapas do fluxo-alvo já aprovado? Quem executa cada papel, quais exceções existem e o que comprova a conclusão para esse serviço (entrega, agendamento, publicação ou outro registro)?
2. Que autenticação e administração serão usadas? Quem pode ver, editar, aprovar, publicar e reabrir cada item? A visão individual é um filtro ou uma restrição de acesso? Como associar clientes e aprovadores, convidar usuários e revogar acesso?
3. Quem aprova em nome do cliente? Há aprovação parcial, limite de rodadas, prazo de resposta ou alteração após aprovação?
4. Como manter comentários ligados à versão correta? Comentários em imagem exigem marcação espacial? Como exibir feedback temporal em novas versões do vídeo?
5. Como medir disponibilidade, pausas, tarefas simultâneas, atrasos externos e alterações de estimativa?
6. Qual escala e fórmula de avaliação serão usadas? Como tratar tarefas bloqueadas ou alteradas por terceiros? Qual política de uso dos resultados?
7. Quais serviços exigem acesso, e quais permitem autenticação sem compartilhar senha? Como conceder e revogar acessos?
8. Quais integrações com ChatGPT, Codex, Claude e Windows são realmente necessárias na primeira versão? Que dados podem ser enviados a cada serviço?
9. “Tudo que o Trello tem” é uma expectativa ampla, não uma lista pronta. Quais funções do Trello são indispensáveis além de quadro/lista, atribuição, busca, etiquetas, checklists, comentários, prazos, anexos e arrastar arquivos? Quais formatos, limites de arquivo e tamanhos de tela precisam ser atendidos?
10. Quais são as regras transparentes e a finalidade da avaliação de prazo, produção e qualidade? Como registrar bloqueios ou mudanças de escopo sem atribuir automaticamente o resultado à pessoa?

As respostas serão registradas nos cartões da lista **Requisitos a validar** do [Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7). A [ficha de validação de caso real](VALIDACAO-CASO-REAL.md) separa fatos, fontes, hipóteses e decisões propostas. Não inferir regras finais a partir do resumo da transcrição quando a fala não as estabelece. A [rastreabilidade dos três áudios](TRACEABILIDADE-AUDIOS.md) liga cada pedido explícito à cobertura atual e às lacunas verificadas.

Uma [proposta de fluxo](FLUXO-PROPOSTO.md) responde como o processo **deveria funcionar** para criativos de redes sociais. Ela não substitui a validação de um caso real da Mix7.
