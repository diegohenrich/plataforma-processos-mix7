# API Mix7 — versão 1

A API pertence à aplicação Laravel e usa o mesmo banco, políticas e histórico do site. Todas as rotas ficam sob `/api/v1` e exigem um token Sanctum no cabeçalho `Authorization: Bearer …`, conta ativa e acesso autorizado. Respostas usam JSON. A API ainda não provisiona nem revoga tokens; não distribua tokens de teste para usuários reais.

## Identidade e leitura

| Método e rota | Uso | Acesso |
| --- | --- | --- |
| `GET /me` | Identidade, papel e organização da conta autenticada | Qualquer perfil ativo |
| `GET /demands` | Até 100 demandas recentes visíveis à pessoa | Dados reduzidos para clientes e tarefas atribuídas filtradas para profissionais |
| `GET /demands/{demand}` | Detalhe autorizado da demanda | Cliente vê título, etapa e atualização; profissional vê suas tarefas; gestão vê o detalhe da organização |

## Tarefas e cronômetro

| Método e rota | Corpo | Comportamento |
| --- | --- | --- |
| `PATCH /tasks/{task}/status` | `{"status":"in_progress"}` | Aplica somente a próxima transição permitida. Concluir fecha o timer aberto e registra autoria e data de conclusão. |
| `POST /tasks/{task}/timer/start` | Vazio | Profissional inicia timer da própria tarefa. Recusa tarefa concluída, predecessoras abertas ou outro timer ativo. |
| `POST /tasks/{task}/timer/pause` | Vazio | Profissional pausa timer da própria tarefa e recebe o intervalo salvo. |
| `POST /tasks/timer/recover` | Vazio | Profissional encerra sua sessão ativa e pausa a tarefa. Se o processo ficou fechado, o período desde o início pode incluir tempo offline e deve ser revisado. |

Profissionais só alteram tarefa atribuída a si; direção/gerência podem mudar o estado de tarefas da própria organização; clientes não operam tarefas. Iniciar/pausar/reconhecer timers é exclusivo do profissional responsável. Os mesmos eventos de autoria usados pelo site são gravados pela API.

Exemplo de sucesso:

```json
{
  "message": "Cronômetro iniciado. O tempo será salvo nesta tarefa.",
  "data": {
    "task_id": 42,
    "status": "in_progress",
    "status_label": "Em andamento",
    "completed_at": null,
    "timer": {
      "id": 81,
      "started_at": "2026-09-26T12:00:00.000000Z",
      "ended_at": null,
      "duration_seconds": null
    }
  }
}
```

Erros de validação usam HTTP `422`; conflito de timer ou regra de fluxo usa `409`; conta sem autorização usa `403`; token ausente/inválido usa `401`. Erros de regra incluem `message` e `errors`, indexados pelo campo. O cliente deve tratar o estado do servidor como fonte de verdade e não presumir que seu relógio local registra tempo.

## Limites atuais

A escrita existente cobre estado de tarefa e cronômetro, reutilizando ações já disponíveis no site. Criar demanda/tarefa, transferir responsável, editar cronograma, gerir conhecimento/equipe, aprovação, anexos e configuração de tokens ainda não têm endpoints de escrita nesta versão. Não existe aplicação Windows nesta entrega; o contrato fica documentado para esse cliente futuro. A matriz de perfis e a operação compartilhada ainda precisam de validação da Mix7 antes do uso com dados reais.
