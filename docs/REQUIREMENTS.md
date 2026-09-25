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

Os áudios identificam quatro tipos de participante: direção (a pessoa que avalia com peso 2; cargo formal a confirmar), gestor/gerente, profissional da equipe e aprovador do cliente. São categorias de usuário respaldadas pelas falas, não contas nem permissões configuradas.

O fluxo aprovado também exige atribuir funções por demanda: responsável pela conta/briefing, gestor da operação, executor, revisor interno e responsável por entrega/publicação. Essas funções descrevem o que alguém faz naquele processo; uma pessoa pode acumular funções. O aprovador é designado pelo cliente para a versão compartilhada. A fala “cada profissional vê o que é para fazer” confirma uma visão individual do trabalho, mas ainda não define se os demais dados ficam ocultos por segurança.

Antes de criar contas operacionais, ainda é necessário decidir autenticação, administração da plataforma, associação de usuários internos à agência e aprovadores às contas de cliente, convites/recuperação, remoção e a matriz de leitura/escrita/aprovação/reabertura. Os áudios citam avaliação pela direção (peso 2) e pelo gestor (peso 1), mas não se deve habilitar nota, remuneração ou medida disciplinar sem critérios e política de uso. Não há nomes e e-mails de usuários para provisionamento. Até isso ser decidido, os nomes livres do protótipo continuam demonstrativos e não são contas.

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
