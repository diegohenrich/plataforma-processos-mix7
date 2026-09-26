# API Mix7 — versão 1

A API pertence à aplicação Laravel e usa o mesmo banco, políticas e histórico do site. Rotas internas sob `/api/v1` exigem token Sanctum no cabeçalho `Authorization: Bearer …`, conta ativa e acesso autorizado. As duas rotas públicas de aprovação usam o token secreto, temporário e revogável do próprio link; não exigem conta. Respostas usam JSON. A API ainda não provisiona nem revoga tokens Sanctum; não distribua tokens de teste para usuários reais.

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
| `POST /demands/{demand}/review-links` | `material_url` ou `material_file`, mais `expires_at` | Direção/gerência na Aprovação do cliente; cria versão, revoga versões abertas anteriores e retorna a URL secreta somente uma vez |
| `DELETE /demands/{demand}/review-links/{reviewLink}` | Vazio | Direção/gerência da mesma organização; revoga o link imediatamente |
| `POST /team/invitations` | `name`, `email`, `role` (`professional` ou `client`) | Somente direção; cria link de ativação por 72 horas sem enviar e-mail; retorna URL secreta uma vez |
| `DELETE /team/invitations/{invitation}` | Vazio | Somente direção da mesma organização; cancela o link pendente |
| `POST /team/performance-reviews` | `task_id`, `deadline_assessment`, `quality_assessment`, `evidence?`, `external_factors?` | Direção/gerência registra avaliação de tarefa concluída da organização; o sistema aplica peso 2/1 e impede duplicata do mesmo avaliador (HTTP `409`) |
| `POST /team/performance-reviews/{review}/responses` | `response` | Só a pessoa profissional avaliada, enquanto ativa e na mesma organização, pode registrar resposta ligada ao histórico |

## Aprovação externa por link

O link é uma credencial: qualquer pessoa que o possua pode ver somente o material daquela versão e responder em nome informado. A gerência pode criar e revogar links pela API autenticada, como descrito na tabela de demandas; a criação devolve a URL secreta em uma única resposta. O token não deve ser incluído em logs, analytics ou links de terceiros. Leitura e resposta têm limites de 30 e 10 requisições por minuto por origem.

O link de convite também é uma credencial e deve ser compartilhado em canal seguro. A API informa que não enviou e-mail; a pessoa convidada acessa a página web de ativação e cria a própria senha. O token expira em 72 horas, é armazenado somente como hash e pode ser revogado pela direção. O endpoint de criação tem limite de 10 chamadas por minuto.

| Método e rota | Uso | Comportamento |
| --- | --- | --- |
| `GET /public/reviews/{token}` | Lê título da demanda, versão, validade, material e respostas ligadas à versão | Sem login; token expirado, revogado ou indisponível retorna `410`; resposta é privada e não armazenável em cache |
| `POST /public/reviews/{token}/responses` | Usa os mesmos campos `reviewer_name`, `type`, `comment` e âncoras da revisão web | `comment`, `annotation`, `approved` ou `changes_requested`; decisão final move a etapa e não pode ser repetida |

Arquivos privados continuam disponíveis pela URL de material que o `GET` retorna e passam pela mesma checagem do link. Aprovação e pedido de ajustes registram o evento da demanda; comentários e anotações permanecem ligados à versão. Os links públicos não expõem briefing nem tarefas internas.

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

A escrita cobre criação de demanda e tarefas, transições de etapa/tarefa, cronograma, transferência de responsável, cronômetro, evidência pós-aprovação, ciclo de links de revisão, convite/cancelamento de profissionais e clientes, e avaliação/resposta. A API não calcula pontuação ou ranking. Gestão de acesso de contas, conhecimento e provisionamento de tokens Sanctum ainda não têm endpoints nesta versão. Não existe aplicação Windows nesta entrega; o contrato fica documentado para esse cliente futuro. A matriz de perfis e a operação compartilhada ainda precisam de validação da Mix7 antes do uso com dados reais.
