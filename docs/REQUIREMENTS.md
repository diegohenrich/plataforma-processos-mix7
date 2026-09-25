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

## Requisitos adicionais confirmados pelo usuário — 25/09/2026

Estes pontos complementam os três áudios e decisões anteriores, com base na inspeção do protótipo feita pelo usuário. Não devem ser apresentados como falas dos áudios:

- **Quadro legível:** cartões mostram um resumo curto do briefing; o contexto completo continua no detalhe. O histórico permanece disponível, mas recolhido por padrão para não alongar a tela.
- **Documentos:** materiais anexados precisam ter uma ação de visualização clara. PDFs devem abrir no próprio detalhe e oferecer alternativa de nova guia ou download caso o navegador não consiga mostrá-los.
- **Autoria e atribuição:** registrar quem criou a demanda, revisou o planejamento, atribuiu e executou cada tarefa, aprovou ou pediu alterações e confirmou a entrega. Os nomes precisam corresponder a usuários identificados, não texto livre apresentado como identidade comprovada.
- **Visões por função:** separar informações e ações para dono da agência, gerente de marketing, profissional executor e cliente, conforme permissões explícitas. A visualização individual do protótipo não conta como controle de acesso.
- **Tempo e trabalho individual:** manter o cronômetro e uma lista de tarefas da equipe facilmente visíveis fora do detalhe minimizado; concluir uma tarefa encerra e registra automaticamente seu cronômetro.
- **Aprovação de cliente por link:** o cliente deve abrir a entrega por link sem criar conta, entender o que está revisando, aprovar ou sugerir alterações e escrever comentários. O escopo de cada link e as medidas contra encaminhamento, acesso indevido e exposição de outros clientes ainda precisam de decisão arquitetural.
- **Revisão de sites:** permitir selecionar trechos de texto e marcar visualmente uma área da página para contextualizar comentários. Essa capacidade exige testar sites com iframe, restrições de origem, páginas móveis e versões publicadas; o mecanismo técnico ainda está em aberto.
- **Gestão da equipe:** gerência precisa acompanhar produção e evolução por profissional; direção e gestão podem pontuar conforme a necessidade citada nos áudios. Os pesos 2 e 1 aparecem nos áudios, mas métricas, escala, período, justificativa, contestação e finalidade continuam sem fórmula aprovada.

### Critérios de aceite adicionados

1. Um cartão longo não expande a coluna do Kanban; o resumo é curto e o briefing completo pode ser consultado no detalhe.
2. O histórico começa recolhido e abre quando a pessoa aciona “Ver histórico”.
3. Um PDF local válido pode ser lido no detalhe em desktop e celular, com ação alternativa clara caso o visualizador embutido não funcione.
4. Um cronômetro continua visível ao navegar/minimizar, fica associado à tarefa escolhida e termina com registro quando a tarefa é concluída.
5. Uma revisão compartilhada por link mostra somente a demanda, versão e materiais autorizados; comentários e decisão ficam ligados ao link, ao material e à versão. O cliente não precisa criar conta. A política técnica inicial usa nome autodeclarado, validade definida por quem envia, revogação e token limitado à versão; a Mix7 precisa validar a política antes de material real. O detalhe está em [Aprovações por módulos](MODULOS-DE-APROVACAO.md).
6. Comentários de site conservam versão e URL, além de trecho, posição X/Y, instante de vídeo ou página. Em prévias incorporáveis, o cliente pode clicar para registrar X/Y; páginas que bloqueiam iframe permitem coordenadas manuais. O Laravel registra as âncoras e as mostra no histórico. Seleção automática de texto, desenho livre e sincronização com o player continuam pendentes de implementação/validação visual.
7. Cada função tem uma matriz verificável de leitura e ação. A tela de cada função é testada com sessões separadas antes do piloto.
8. Indicadores de produção e evolução são rastreáveis às tarefas e avaliações, distinguem bloqueios e mudanças de escopo e não aplicam consequência automática de pessoal.

O sistema de aprovações deve ser reutilizável em áreas diferentes da agência. Criativos de redes sociais são o primeiro módulo conhecido; a Mix7 ainda precisa indicar os demais tipos concretos. Requisitos e critérios estão em [Aprovações por módulos](MODULOS-DE-APROVACAO.md).

O comportamento de tempo confirmado, a proposta de capacidade e os critérios de aceite estão em [Tempo, disponibilidade e capacidade](TEMPO-E-CAPACIDADE.md). A proposta não fecha as regras de jornada que a Mix7 ainda precisa definir.

## Decisões confirmadas e escopo da primeira entrega operacional

A primeira versão em uso cobre todas as áreas listadas acima: gestão, aprovações expansíveis, tempo e capacidade, IA revisada por pessoa, avaliação, conhecimento, onboarding, acessos e integração necessária com o ambiente de trabalho. As regras ainda sem resposta continuam como requisitos a validar; não são motivo para retirar essas áreas do escopo. A ordem de construção e as evidências de conclusão estão em [ROADMAP.md](ROADMAP.md).

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
5. Como medir disponibilidade, pausas, tarefas simultâneas, atrasos externos e alterações de estimativa? Consulte a proposta e os critérios em [Tempo, disponibilidade e capacidade](TEMPO-E-CAPACIDADE.md).
6. Qual escala e fórmula de avaliação serão usadas? Como tratar tarefas bloqueadas ou alteradas por terceiros? Qual política de uso dos resultados?
7. Quais serviços exigem acesso, e quais permitem autenticação sem compartilhar senha? Como conceder e revogar acessos?
8. Quais integrações com ChatGPT, Codex, Claude e Windows são realmente necessárias na primeira versão? Que dados podem ser enviados a cada serviço?
9. “Tudo que o Trello tem” é uma expectativa ampla, não uma lista pronta. Quais funções do Trello são indispensáveis além de quadro/lista, atribuição, busca, etiquetas, checklists, comentários, prazos, anexos e arrastar arquivos? Quais formatos, limites de arquivo e tamanhos de tela precisam ser atendidos?
10. Quais são as regras transparentes e a finalidade da avaliação de prazo, produção e qualidade? Como registrar bloqueios ou mudanças de escopo sem atribuir automaticamente o resultado à pessoa?
11. Que informação e ação são próprias do dono da agência, gerente de marketing, profissional e cliente? Quem pode ver dados de cada cliente, criar e atribuir tarefas, alterar briefing, revisar, compartilhar link, reabrir e consultar indicadores?
12. Como se identifica o cliente ao responder por link sem conta? O link expira, pode ser revogado ou restringido a uma única demanda/versão? O que fazer se for encaminhado?
13. Para revisar site, a Mix7 quer comentar sobre página publicada via script, imagem capturada, extensão ou outro mecanismo? Que interações e dispositivos precisam funcionar?
14. Quais valores de produção a gerência precisa acompanhar (tarefas, horas, entregas, qualidade ou outros), e com que período e referência de evolução?

As respostas serão registradas nos cartões da lista **Requisitos a validar** do [Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7). A [ficha de validação de caso real](VALIDACAO-CASO-REAL.md) separa fatos, fontes, hipóteses e decisões propostas. Não inferir regras finais a partir do resumo da transcrição quando a fala não as estabelece. A [rastreabilidade dos três áudios](TRACEABILIDADE-AUDIOS.md) liga cada pedido explícito à cobertura atual e às lacunas verificadas.

Uma [proposta de fluxo](FLUXO-PROPOSTO.md) responde como o processo **deveria funcionar** para criativos de redes sociais. Ela não substitui a validação de um caso real da Mix7.
