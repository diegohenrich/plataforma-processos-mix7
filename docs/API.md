# API Mix7 — versão 1

A API pertence à aplicação Laravel e usa o mesmo banco, políticas e histórico do site. Rotas internas sob `/api/v1` exigem token Sanctum no cabeçalho `Authorization: Bearer …`, conta ativa e acesso autorizado. As duas rotas públicas de aprovação usam o token secreto, temporário e revogável do próprio link; não exigem conta. Respostas usam JSON.

Na aplicação web, comentários, anotações e decisões do link de revisão geram notificações armazenadas no banco para a direção/gerência ativa da organização, a pessoa criadora ativa da demanda e profissionais ativos atribuídos às tarefas daquela demanda. Cada usuário lê e abre somente suas próprias notificações; a abertura confere novamente a permissão da demanda. Esses avisos não enviam e-mail.

Usuários internos ativos podem emitir e revogar seus próprios tokens em `/integracoes/tokens`. A emissão exige a senha atual, nome e validade fixa de 7, 30 ou 90 dias; cada conta mantém no máximo 10 tokens não expirados. O segredo é mostrado uma única vez em resposta privada sem cache e o banco armazena somente seu hash. Tokens têm a permissão `api`, enquanto cada endpoint continua sujeito às regras de papel, organização e recurso. Clientes não podem emitir tokens internos; a revisão externa segue por link.

## Identidade e leitura

| Método e rota | Uso | Acesso |
| --- | --- | --- |
| `GET /me` | Identidade, papel e organização da conta autenticada | Qualquer perfil ativo |
| `GET /demands` | Até 100 demandas recentes visíveis à pessoa, incluindo canal de entrada (`intake_source`), autoria do briefing (`brief_author`), chave e versão do módulo, etapas internas de conferência (`module_steps`), tarefas autorizadas e, se aprovada, a síntese curta interna (`summary`). Cada tarefa identifica pessoa responsável (`assignee`) e autor da atribuição mais recente (`assigned_by`). | Dados reduzidos para clientes e tarefas atribuídas filtradas para profissionais; clientes nunca recebem briefing, canal, autoria, síntese, etapas internas ou tarefas |
| `GET /demands/{demand}` | Detalhe autorizado da demanda, incluindo canal de entrada, autoria do briefing, chave e versão do módulo, etapas internas de conferência (`module_steps`), tarefas autorizadas e a síntese curta aprovada (`summary`). Cada tarefa identifica pessoa responsável (`assignee`) e autor da atribuição mais recente (`assigned_by`). | Cliente vê título, módulo, etapa e atualização; profissional vê suas tarefas; gestão vê o detalhe da organização; campos internos não são enviados a clientes |
| `GET /approval-modules` | Tipos ativos disponíveis, com campos e `workflow_steps` configurados | Direção/gerência da própria organização |
| `PATCH /demands/{demand}/module-steps/{stepKey}` | `{ "completed": true }` marca uma etapa de conferência como concluída; `false` reabre | Direção/gerência da própria organização; a resposta registra autoria/data e não muda a etapa principal |
| `GET /demands/{demand}/attachments` | Lista metadados e URLs autenticadas de prévia/download dos anexos internos | Direção/gerência ou profissional autorizado a ver a demanda; clientes recebem `404` |
| `POST /demands/{demand}/attachments` | `multipart/form-data` com `files[]`; até 10 arquivos por envio e 20 MB por arquivo. PDF, imagens, vídeos, Office, texto, CSV e ZIP | Mesma autorização do detalhe interno; grava autor e evento. Retorna `201` com nome, tipo, tamanho, autor, data, `preview_url` e `download_url`; nunca retorna o caminho privado de armazenamento |
| `GET /demands/{demand}/attachments/{attachment}` | Transmite PDF, imagem ou vídeo inline para prévia; acrescente `?download=1` para forçar download | Exige token ativo e autorização da demanda; clientes, outra organização e anexos associados a outra demanda recebem `404` |
| `GET /team/activity` | Atividade dos últimos 30 dias e série semanal (`weekly_trend`) com tarefas concluídas e tempo registrado por semana | Direção/gerência recebem totais agregados sem títulos de tarefas; profissional recebe somente a própria série, suas próprias tarefas abertas e seu cronômetro; cliente não tem acesso. A série descreve registros, não calcula nota nem ranking |
| `GET /team/service-access` | Catálogo de serviços e solicitações | Direção vê solicitações da organização; profissional/gerência veem o catálogo e somente o próprio estado; cliente não acessa |
| `POST /team/service-access` | `name`, `access_method`, `instructions`, `service_url?`, `review_due_on?` | Somente direção cadastra ferramenta; URL aceita HTTP/HTTPS; não há campos para senha, token ou chave |
| `PUT /team/service-access/{service}` | Campos do catálogo | Somente direção atualiza item da própria organização |
| `DELETE /team/service-access/{service}` / `POST .../{service}/restore` | Vazio | Direção arquiva/restaura; arquivamento é recusado enquanto há solicitação pendente ou acesso concedido |
| `POST /team/service-access/{service}/requests` | Vazio | Usuário interno solicita o próprio acesso; cliente não acessa e pedido duplicado pendente/concedido é recusado |
| `DELETE /team/service-access/requests/{request}` | Vazio | A pessoa retira somente a própria solicitação pendente |
| `POST /team/service-access/requests/{request}/decision` | `status` (`granted` ou `denied`) | Somente direção da organização decide; a ação deve ser executada também no serviço externo |
| `POST /team/service-access/requests/{request}/revoke` | Vazio | Direção registra revogação para acesso concedido; revogação no serviço externo continua manual |
| `GET /team/members` | Lista contas profissionais e clientes da agência, com papel e estado de acesso | Somente direção; no máximo 200 registros, sem contas de direção/gerência ou de outras organizações |
| `GET /team/invitations` | Lista convites pendentes e ainda válidos, sem segredo/token | Somente direção; resposta sem cache e filtrada pela organização |
| `GET /knowledge` | Lista conteúdo ativo da biblioteca; aceita filtros opcionais `type` e `q` | Direção, gerência e profissionais ativos da própria organização; clientes não têm acesso |
| `POST /knowledge` | Cria referência, treinamento, contato ou trilha de onboarding | Direção/gerência; conteúdo pertence à própria organização e a trilha exige ao menos uma etapa |
| `PUT /knowledge/{item}` | Atualiza conteúdo ativo e seus metadados | Direção/gerência da organização; atribuições já iniciadas mantêm o instantâneo original das etapas |
| `DELETE /knowledge/{item}` | Arquiva conteúdo sem apagar histórico | Direção/gerência da organização |
| `POST /knowledge/{item}/restore` | Restaura item arquivado | Direção/gerência da organização |
| `POST /knowledge/{item}/assignments` | `{"user_id":17}` atribui uma trilha a profissional ativo | Direção/gerência; uma atribuição por pessoa/trilha e todas as etapas são copiadas para o progresso individual |
| `GET /onboarding/assignments` | Lista as trilhas atribuídas à própria pessoa, com etapas e progresso | Somente profissionais; não lista trilhas de colegas |
| `PATCH /onboarding/assignments/{assignment}/steps/{step}` | Alterna a conclusão da etapa atribuída | Somente o profissional designado e somente para etapa da mesma trilha/organização |
| `GET /approval-modules` | Lista os tipos padrão e adicionais ativos da organização (`key`, `label`, `version`, `description`, `fields[]`) | Direção/gerência |
| `POST /demands` | `title`, `brief`, `module_key`, `module_fields_data?`, `intake_source?`, `brief_author_id?`, `tasks[]`; cria demanda e de 1 a 20 tarefas iniciais numa transação. O canal de entrada é texto curto; a autoria do briefing deve apontar para conta interna ativa da mesma organização. | Direção/gerência; o tipo deve ser padrão ou estar ativo no catálogo da mesma organização; cliente opcional, autor do briefing e profissionais ativos devem pertencer à mesma organização |
| `POST /demands/{demand}/tasks` | Acrescenta uma tarefa atribuída à demanda | Direção/gerência da organização, exceto durante aprovação do cliente, entrega ou conclusão |
| `PATCH /demands/{demand}/status` | `{"status":"planning"}` | Direção/gerência; transições do fluxo são validadas e revisão interna exige todas as tarefas concluídas |
| `POST /demands/{demand}/delivery-evidences` | `{"outcome":"published","evidence_url":"https://…"}` ou observação `details` | Direção/gerência na etapa Entrega ou depois; registra autoria e histórico sem publicar conteúdo nem mover a etapa |
| `POST /demands/{demand}/review-links` | `material_url` ou `material_file`, mais `expires_at` | Direção/gerência na Aprovação do cliente; cria versão, revoga versões abertas anteriores e retorna a URL secreta somente uma vez |
| `DELETE /demands/{demand}/review-links/{reviewLink}` | Vazio | Direção/gerência da mesma organização; revoga o link imediatamente |
| `POST /team/invitations` | `name`, `email`, `role` (`professional` ou `client`) | Somente direção; cria link de ativação por 72 horas sem enviar e-mail; retorna URL secreta uma vez |
| `DELETE /team/invitations/{invitation}` | Vazio | Somente direção da mesma organização; cancela o link pendente |
| `PATCH /team/members/{member}/access` | Alterna entre desativar e restaurar o acesso | Somente direção da mesma organização para contas profissionais ou clientes; revoga tokens/sessões e encerra timer ao desativar |
| `POST /team/performance-reviews` | `task_id`, `deadline_assessment`, `quality_assessment`, `evidence?`, `external_factors?` | Direção/gerência registra avaliação de tarefa concluída da organização; o sistema aplica peso 2/1 e impede duplicata do mesmo avaliador (HTTP `409`) |
| `POST /team/performance-reviews/{review}/responses` | `response` | Só a pessoa profissional avaliada, enquanto ativa e na mesma organização, pode registrar resposta ligada ao histórico |

Cada `fields[]` de um tipo adicional informa `key`, `label`, `type`, `required` e `options` quando `type` é `select`. Tipos aceitos: `text`, `textarea`, `date`, `url` e `select`; opções são uma lista de valores permitidos. `module_fields_data` usa as chaves definidas pelo tipo. Campos desconhecidos, valores fora da lista e campos obrigatórios ausentes são recusados com `422`. Alterar a definição pela interface web incrementa sua versão sem mudar snapshots existentes. Dados de campos aparecem somente nas respostas internas autorizadas de demandas e nunca nas respostas de cliente ou do link público de aprovação.

## Aprovação externa por link

O link é uma credencial: qualquer pessoa que o possua pode ver somente o material daquela versão e responder em nome informado. A gerência pode criar e revogar links pela API autenticada, como descrito na tabela de demandas; a criação devolve a URL secreta em uma única resposta. O token não deve ser incluído em logs, analytics ou links de terceiros. A página pública e a API de revisão enviam `Cache-Control: private, no-store` e `Referrer-Policy: no-referrer`. Leitura e resposta têm limites de 30 e 10 requisições por minuto por origem.

O link de convite também é uma credencial e deve ser compartilhado em canal seguro. A API informa que não enviou e-mail; a pessoa convidada acessa a página web de ativação e cria a própria senha. O token expira em 72 horas, é armazenado somente como hash e pode ser revogado pela direção. O endpoint de criação tem limite de 10 chamadas por minuto.

| Método e rota | Uso | Comportamento |
| --- | --- | --- |
| `GET /public/reviews/{token}` | Lê título da demanda, versão, validade, material e respostas ligadas à versão | Sem login; token expirado, revogado ou indisponível retorna `410`; resposta é privada e não armazenável em cache |
| `POST /public/reviews/{token}/responses` | Usa os mesmos campos `reviewer_name`, `type`, `comment` e âncoras da revisão web | `comment`, `annotation`, `approved` ou `changes_requested`; decisão final move a etapa e não pode ser repetida |

O envio opcional do link por e-mail está disponível somente na tela web de criação da versão, por ação explícita da direção/gerência e com endereço informado. A mensagem contém o link temporário e informa que o cliente não precisa de conta. A API não envia mensagens; em `log`/`array`, a tela explica que o envio está desabilitado e permite copiar o link. Um transporte real ainda precisa ser configurado e validado no ambiente de operação.

Arquivos privados continuam disponíveis pela URL de material que o `GET` retorna e passam pela mesma checagem do link. Aprovação e pedido de ajustes registram o evento da demanda; comentários e anotações permanecem ligados à versão. Os links públicos não expõem briefing nem tarefas internas.

Anexos de trabalho são separados do material enviado para aprovação. A API autentica cada leitura/transferência e oculta anexos internos dos clientes; um arquivo só aparece para o aprovador externo quando a equipe o seleciona em uma nova versão de revisão. Os limites locais são os descritos acima; Hostinger ainda precisa confirmar limite PHP, espaço e tráfego.

## Tarefas e cronômetro

| Método e rota | Corpo | Comportamento |
| --- | --- | --- |
| `PATCH /tasks/{task}/status` | `{"status":"in_progress"}` | Aplica somente a próxima transição permitida. Concluir fecha o timer aberto e registra autoria e data de conclusão. |
| `POST /tasks/{task}/timer/start` | Vazio | Profissional inicia timer da própria tarefa. Recusa tarefa concluída, predecessoras abertas ou outro timer ativo. |
| `POST /tasks/{task}/timer/pause` | Vazio | Profissional pausa timer da própria tarefa e recebe o intervalo salvo. |
| `POST /tasks/timer/heartbeat` | Vazio | Profissional mantém a própria sessão ativa. A tela web envia sinais a cada 20 segundos; após 180 segundos sem sinal, o servidor encerra o intervalo no último sinal confirmado e pausa a tarefa. |
| `PATCH /tasks/{task}/schedule` | `{"planned_start_on":"2026-10-02","planned_due_on":"2026-10-07"}` | Direção/gerência salva ou limpa datas opcionais da tarefa na própria organização. Prazo anterior ao início é recusado. |
| `PATCH /tasks/{task}/assignee` | `{"assignee_id":17}` | Direção/gerência transfere trabalho aberto para profissional ativo da própria organização. Se houver timer ativo, ele é encerrado e tarefa em execução fica pausada. A resposta identifica o responsável atual e quem fez a atribuição mais recente; o histórico preserva atribuições anteriores. |
| `POST /tasks/timer/recover` | Vazio | Profissional encerra uma sessão ativa antiga sem sinal de navegador (por exemplo, iniciada antes da implantação do heartbeat) e pausa a tarefa. |

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

A escrita cobre administração de conteúdo e onboarding, mudança de acesso de profissionais/clientes, criação de demanda e tarefas, transições de etapa/tarefa, cronograma, transferência de responsável, cronômetro, evidência pós-aprovação, ciclo de links de revisão, convites, avaliação/resposta e progresso pessoal de onboarding. Tipos adicionais de aprovação são configurados pela interface web, incluindo campos internos próprios versionados; a API lista definições ativas e aceita valores de demanda validados. O contrato interno de demandas inclui schema e valores próprios do módulo; clientes não os recebem. As ações permanecem isoladas por organização e papel. Direção pode listar as contas e convites ativos da equipe. Tokens Sanctum podem ser emitidos/revogados pela própria pessoa interna na interface web; não há endpoints de administração dos tokens. A API não calcula pontuação ou ranking. Não existe aplicação Windows nesta entrega; o contrato fica documentado para esse cliente futuro. A matriz de perfis e a operação compartilhada ainda precisam de validação da Mix7 antes do uso com dados reais.
