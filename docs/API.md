# API Mix7 — versão 1

A API pertence à aplicação Laravel e usa o mesmo banco, políticas e histórico do site. Todas as rotas ficam sob `/api/v1` e exigem um token Sanctum no cabeçalho `Authorization: Bearer …`, conta ativa e acesso autorizado. Respostas usam JSON. A API ainda não provisiona nem revoga tokens; não distribua tokens de teste para usuários reais.

## Identidade e leitura

| Método e rota | Uso | Acesso |
| --- | --- | --- |
| `GET /me` | Identidade, papel e organização da conta autenticada | Qualquer perfil ativo |
| `GET /demands` | Até 100 demandas recentes visíveis à pessoa | Dados reduzidos para clientes e tarefas atribuídas filtradas para profissionais |
| `GET /demands/{demand}` | Detalhe autorizado da demanda | Cliente vê título, etapa e atualização; profissional vê suas tarefas; gestão vê o detalhe da organização |
| `POST /demands` | Cria demanda e de 1 a 20 tarefas iniciais em uma transação | Direção/gerência; cliente opcional e profissionais ativos devem pertencer à mesma organização |
| `POST /demands/{demand}/tasks` | Acrescenta uma tarefa atribuída à demanda | Direção/gerência da organização, exceto durante aprovação do cliente, entrega ou conclusão |
| `PATCH /demands/{demand}/status` | `{"status":"planning"}` | Direção/gerência; transições do fluxo são validadas e revisão interna exige todas as tarefas concluídas |
| `POST /demands/{demand}/delivery-evidences` | `{"outcome":"published","evidence_url":"https://…"}` ou observação `details` | Direção/gerência na etapa Entrega ou depois; registra autoria e histórico sem publicar conteúdo nem mover a etapa |

## Tarefas e cronômetro

| Método e rota | Corpo | Comportamento |
| --- | --- | --- |
| `PATCH /tasks/{task}/status` | `{"status":"in_progress"}` | Aplica somente a próxima transição permitida. Concluir fecha o timer aberto e registra autoria e data de conclusão. |
| `POST /tasks/{task}/timer/start` | Vazio | Profissional inicia timer da própria tarefa. Recusa tarefa concluída, predecessoras abertas ou outro timer ativo. |
| `POST /tasks/{task}/timer/pause` | Vazio | Profissional pausa timer da própria tarefa e recebe o intervalo salvo. |
| `PATCH /tasks/{task}/schedule` | `{"planned_start_on":"2026-10-02","planned_due_on":"2026-10-07"}` | Direção/gerência salva ou limpa datas opcionais da tarefa na própria organização. Prazo anterior ao início é recusado. |
| `PATCH /tasks/{task}/assignee` | `{"assignee_id":17}` | Direção/gerência transfere trabalho aberto para profissional ativo da própria organização. Se houver timer ativo, ele é encerrado e tarefa em execução fica pausada, com autoria no histórico. |
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

Erros de validação e transição de tarefa não permitida usam HTTP `422`; conflito de timer ou bloqueio de fluxo da demanda usa `409`; conta sem autorização usa `403`; token ausente/inválido usa `401`. Criações bem-sucedidas retornam HTTP `201`. Erros de regra incluem `message` e `errors`, indexados pelo campo. O cliente deve tratar o estado do servidor como fonte de verdade e não presumir que seu relógio local registra tempo.

## Limites atuais

A escrita cobre criação de demanda e tarefas, transições de etapa/tarefa, cronograma, transferência de responsável, cronômetro e registro de evidência pós-aprovação, reutilizando regras já aplicadas pelo site. Gerir conhecimento/equipe, aprovação, anexos e configuração de tokens ainda não têm endpoints de escrita nesta versão. Não existe aplicação Windows nesta entrega; o contrato fica documentado para esse cliente futuro. A matriz de perfis e a operação compartilhada ainda precisam de validação da Mix7 antes do uso com dados reais.
