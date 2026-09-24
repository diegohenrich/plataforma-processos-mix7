# Continuidade do projeto Mix7

Leia `.ai/CONTEXT.md`, `.ai/DECISIONS.md` e `.ai/STATUS.md` antes de trabalhar. O [Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7) acompanha tarefas e andamento; o [GitHub](https://github.com/diegohenrich/plataforma-processos-mix7) guarda arquivos, documentação e histórico.

## Regra obrigatória de sincronização

- Vincule cada alteração ou tarefa a um cartão existente no Trello; crie um cartão quando não houver um correspondente.
- Depois de **cada alteração concluída e de cada tarefa concluída**, crie commit para os arquivos pertinentes, envie a branch ao GitHub e confirme que o commit remoto corresponde ao local. Atualize o Trello com resultado, estado e link para commit ou pull request. Faça as duas atualizações antes de declarar a entrega concluída.
- Use uma branch para mudanças de trabalho e integre em `main` por pull request. Não use `force push`, não reescreva commits publicados e não exclua branches ou tags sem autorização explícita. Marque marcos importantes com tags anotadas enviadas ao GitHub.
- Atualize `.ai/STATUS.md` com validações concretas; revise `.ai/CONTEXT.md` ou `.ai/DECISIONS.md` somente quando seus fatos ou decisões mudarem.
- Preserve mudanças de outras pessoas. Se GitHub ou Trello estiver indisponível, registre a sincronização pendente em `.ai/STATUS.md` e informe o impedimento; não apresente as fontes como sincronizadas.

O pedido atual do usuário e instruções mais específicas têm prioridade sobre este arquivo.
