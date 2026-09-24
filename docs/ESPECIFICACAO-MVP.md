# Especificação funcional inicial — primeira jornada integrada

**Estado:** versão para validação e protótipo. Requisitos priorizados em [REQUIREMENTS.md](REQUIREMENTS.md). Descreve comportamento-alvo aprovado como diretriz; papéis concretos, exceções e rotina atual ainda precisam de um caso real Mix7.

## Objetivo e limite

Permitir que equipe e cliente acompanhem uma demanda de criativo desde o briefing até a publicação/entrega registrada, mantendo tarefa, arquivo, versão, comentário e decisão ligados ao mesmo histórico. A fundação deve permitir novos módulos de aprovação sem separar os cadastros ou duplicar a auditoria.

Inclui entrada e briefing, plano revisável, tarefa atribuída, execução, revisão interna-alvo, aprovação de cliente por versão, rodada de ajustes, registro de publicação/entrega e conclusão. Não inclui nesta jornada pontuação de desempenho, onboarding, cofre de senhas nem automação de publicação. A IA fica atrás de uma interface de sugestão com confirmação humana; selecionar provedor e enviar dados reais exige decisão separada sobre custos e tratamento dos dados. Conectores de execução com ChatGPT, Codex e Claude Code não fazem parte desta primeira jornada.

## Atores de produto (nomes provisórios)

| Ator | Ações previstas |
| --- | --- |
| Responsável pela demanda / conta | Registrar e completar o briefing; compartilhar versão; acompanhar retorno; conferir evidência final e pedir conclusão. |
| Gestor de operação | Revisar plano sugerido, distribuir tarefas, ajustar capacidade e decidir encaminhamento de impedimentos. |
| Profissional executor | Consultar sua carga, executar tarefas, registrar avanço/arquivo e sinalizar bloqueio. |
| Revisor interno | Avaliar peça e registrar aprovar ou devolver para ajuste antes do envio ao cliente. Pode coincidir com gestor conforme exceção do fluxo-alvo. |
| Aprovador do cliente | Abrir apenas o material compartilhado para sua revisão, comentar e registrar aprovar ou pedir alterações. |
| Responsável pela publicação/entrega | Registrar agendamento/publicação ou entrega, data e evidência. |

Uma pessoa pode acumular papéis; cada ação relevante deve manter identidade individual. Membros, substituições e permissões finais permanecem pendentes de validação operacional.

## Estados e transições

| Estado proposto | Entrada | Saída e regra |
| --- | --- | --- |
| Briefing incompleto | Demanda criada com informação essencial ausente. | Completar campos configurados por tipo de entrega. Não liberar execução enquanto faltar item obrigatório. |
| Planejamento | Briefing está pronto para divisão do trabalho. | IA pode sugerir plano; gestor confirma, edita ou rejeita. Plano confirmado cria tarefas e prazos. |
| Em execução | Há tarefas atribuídas e plano confirmado. | Executor atualiza andamento e envia arquivo. Bloqueios ficam explícitos e retornam ao gestor. |
| Revisão interna | Uma versão está pronta para a checagem interna obrigatória do fluxo-alvo aceito. | Aprovar libera envio; pedir ajuste devolve à execução com motivo. Gravar revisor e versão. Qualquer exceção exige regra explícita e registro; confirmar quais exceções existem no caso real. |
| Aguardando cliente | Responsável compartilhou versão e aprovador designado. | Decisão explícita e comentários associados àquela versão. Pendente até a decisão; não inferir aceite por silêncio. |
| Ajustes | Cliente pediu alterações. | Consolidar comentários, criar tarefas/versão nova e repetir revisão interna e envio. Manter histórico antigo imutável. |
| Entrega/publicação | Versão aprovada pelo cliente. | Registrar agendamento, publicação ou entrega e evidência por tipo de serviço. Falha mantém demanda aberta. |
| Concluída | Evidência final conferida pelo papel autorizado. | Preservar histórico; reabertura requer ator, motivo e evento auditável. Permissão para reabrir ainda pendente. |

## Regras funcionais

1. Uma decisão sempre referencia demanda, artefato, versão, autor, papel e instante do registro.
2. Comentário em vídeo referencia timecode e versão. Comentário em imagem referencia pelo menos a versão; se ancoragem em coordenada for suportada, registrar também posição.
3. Alterar arquivo depois do envio cria nova versão; não substitui o conteúdo já analisado pelo cliente.
4. Atualizar briefing após o plano confirmado sinaliza impacto e exige revisão humana do plano antes de manter a execução.
5. IA gera somente sugestão editável. Se o modelo não responder, o fluxo manual continua.
6. Aprovar o criativo não publica automaticamente. Publicar é ação explícita autorizada; registrar evidência e só então concluir.
7. Cliente não recebe notas internas, rascunhos nem conteúdo de outra demanda por link ou filtro. A matriz detalhada será validada com papéis reais.
8. Novos módulos definem campos, estados e evidências específicos, reutilizando identidade, demanda, permissões e eventos de auditoria.

## Critérios de aceite ponta a ponta

- **Fluxo feliz:** registrar briefing completo → revisar proposta/plano → confirmar responsável e prazo → enviar arquivo da versão 1 → concluir revisão interna obrigatória do fluxo-alvo → compartilhar com aprovador correto → aprovar a versão exata → registrar publicação/entrega e evidência → responsável autorizado conclui; todo histórico é recuperável.
- **Briefing incompleto:** tarefa de execução não pode começar; o sistema identifica campos pendentes sem descartar o pedido.
- **Pedido de ajuste:** decisão e comentários ficam na versão enviada; equipe gera versão 2; a versão anterior e seu aceite/comentários continuam consultáveis; novo envio inicia decisão independente.
- **Feedback audiovisual:** comentário de vídeo reabre no timecode gravado; comentário de imagem retorna ao arquivo/versão a que foi feito.
- **Falha de revisão interna:** não compartilhar a versão com o cliente e devolver para execução com motivo e revisor registrados. Exceções à revisão precisam ser autorizadas e auditáveis.
- **Mudança de briefing/prazo:** mostrar impacto no plano confirmado e registrar quem autorizou a alteração.
- **Falha de publicação:** a demanda não passa a concluída; falta ou falha de evidência fica visível como pendência.
- **Acesso de cliente:** conta de teste de cliente não consegue abrir briefing interno, tarefa de outro cliente ou comentário interno.
- **IA indisponível:** gestor cria plano manualmente e mantém o fluxo sem bloqueio.

Os critérios acima são propostas para orientar protótipo e especificação. Teste de permissões exige matriz real de papéis; aceite final depende do caso operacional da Mix7.

## Registro pendente de caso real

Não há ainda uma narrativa verificável da rotina Mix7 que confirme entrada, briefing, revisão, quem envia, aprovação parcial/rodadas, publicação e conclusão. Até essa validação, os nomes de atores e estados acima são atores/estados de produto provisórios, e este documento não deve ser usado como afirmação de processo vigente.
