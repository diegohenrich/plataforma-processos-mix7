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
| Construção | Comparar soluções existentes e opções open source antes de decidir o que desenvolver. Avaliar licenças, manutenção e segurança. |

## Decisões confirmadas para o primeiro recorte

- Público: equipe e clientes da Mix7.
- Fluxo pretendido: demanda → planejamento revisado → execução → aprovação do cliente → ajustes → conclusão.
- IA: sugere; uma pessoa revisa antes de criar ou distribuir tarefas.

## Questões abertas que impedem especificação final

1. Qual é o percurso real de uma demanda, da entrada ao encerramento? Existe revisão interna obrigatória? “Concluído” significa aprovado, entregue, agendado ou publicado?
2. Quem pode ver, editar, aprovar e reabrir cada item? A visão individual é um filtro ou uma restrição de acesso?
3. Quem aprova em nome do cliente? Há aprovação parcial, limite de rodadas, prazo de resposta ou alteração após aprovação?
4. Como manter comentários ligados à versão correta? Comentários em imagem exigem marcação espacial? Como exibir feedback temporal em novas versões do vídeo?
5. Como medir disponibilidade, pausas, tarefas simultâneas, atrasos externos e alterações de estimativa?
6. Qual escala e fórmula de avaliação serão usadas? Como tratar tarefas bloqueadas ou alteradas por terceiros? Qual política de uso dos resultados?
7. Quais serviços exigem acesso, e quais permitem autenticação sem compartilhar senha? Como conceder e revogar acessos?
8. Quais integrações com ChatGPT, Codex, Claude e Windows são realmente necessárias na primeira versão? Que dados podem ser enviados a cada serviço?
9. Quais funções do Trello são indispensáveis? Quais formatos, limites de arquivo e tamanhos de tela precisam ser atendidos?

As respostas serão registradas nos cartões da lista **Requisitos a validar** do [Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7). Não inferir regras finais a partir do resumo da transcrição quando a fala não as estabelece.

Uma [proposta de fluxo](FLUXO-PROPOSTO.md) responde como o processo **deveria funcionar** para criativos de redes sociais. Ela não substitui a validação de um caso real da Mix7.
