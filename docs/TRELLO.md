# Uso do quadro Trello

Quadro: [Desenvolvimento de Projetos - Mix7](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7). As listas mostram o **andamento**; as etiquetas mostram a **área**. Um cartão pode ter mais de uma etiqueta quando integra áreas.

| Cor | Nome | Use em cartões sobre |
| --- | --- | --- |
| Azul | Gestão de equipe | Demandas, tarefas, Kanban, lista, tempo, capacidade e avaliação. |
| Verde | Aprovações | Envio ao cliente, comentários, versões, aprovação e ajustes. |
| Roxo | IA e automações | Sugestões de tarefas, estimativas, distribuição revisada e organização de feedbacks. |
| Amarelo | Conhecimento e acessos | Referências, treinamento, onboarding, permissões e concessão de acessos. |
| Laranja | Pesquisa de soluções | Comparação de ferramentas existentes, bases open source, licenças e custos. |
| Vermelho | Bloqueio | Impedimento real que exige ação ou decisão antes de continuar. Use junto da etiqueta da área afetada. |

**Exemplo:** o cartão do fluxo integrado recebe azul e verde porque envolve execução da equipe e aprovação do cliente. O cartão de entrada de demandas e IA recebe azul e roxo.

Os nomes foram configurados nas etiquetas do quadro. Se um usuário enxergar somente barras coloridas nos cartões, pode clicar em uma etiqueta para alternar a exibição dos nomes.

## Listas

1. **Contexto e decisões:** visão e escolhas vigentes, com motivo de revisão quando mudarem.
2. **Requisitos a validar:** perguntas cuja resposta depende de um caso real ou decisão da Mix7.
3. **Próximas entregas:** trabalho definido, ainda não iniciado.
4. **Em andamento:** entrega em execução.
5. **Em revisão:** resultado pronto para avaliação.
6. **Concluído:** critérios de aceite atendidos e registro sincronizado com o GitHub.

Ao concluir uma alteração ou tarefa, faça commit e push, confira o commit remoto e atualize o cartão com o resultado, estado e link para commit ou pull request, conforme [AGENTS.md](../AGENTS.md).

## Como deixar os cartões fáceis de entender

Cada cartão deve explicar, em poucas linhas:

1. **Para quê serve?** Diga o objetivo com palavras simples.
2. **O que falta fazer?** Use uma lista de passos curtos, começando com verbos.
3. **Como saber que terminou?** Escreva um resultado que outra pessoa consiga conferir.
4. **Onde estão os detalhes?** Ligue ao documento do GitHub, sem copiar a especificação inteira para o Trello.

Divida entregas diferentes em cartões diferentes. Um cartão maior pode servir de índice e apontar para os cartões menores; não deve esconder várias entregas num único texto. Use checklist para os passos de uma mesma entrega e marque um passo só depois de conferir o resultado. As etiquetas mostram a área relacionada: aplique as que servem ao cartão, sem usar todas em tudo. Use “Concluído” somente depois do aceite e da sincronização GitHub/Trello.

Exemplo atual: o cartão [primeira entrega integrada](https://trello.com/c/Bsb4M4Mm/18-implementar-e-validar-a-primeira-entrega-integrada) aponta para as três simulações concluídas e para o trabalho em andamento dos arquivos de referência no briefing. Cada cartão explica seu próprio resultado e tem passos separados.
