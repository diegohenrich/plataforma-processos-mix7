# Glossário e critério de conclusão

Este glossário descreve o vocabulário e o comportamento-alvo da primeira entrega. Ele não afirma como a Mix7 opera hoje; o [caso real anonimizado](VALIDACAO-CASO-REAL.md) confirma papéis, variações por serviço e evidências concretas.

| Termo | Significado no produto |
| --- | --- |
| Demanda | Unidade de trabalho que reúne briefing, plano, tarefas, materiais, decisões e resultado até a conclusão. |
| Briefing | Contexto e critérios para executar a demanda. Origem, canal/peça e critérios de aceite são mínimos provisórios do protótipo; campos obrigatórios devem variar conforme o serviço quando validados. |
| Planejamento | Plano revisado que organiza tarefas e suas relações antes de iniciar ou retomar a execução. Sugestões de IA precisam de confirmação humana. |
| Tarefa | Parte atribuída do plano, com estado próprio e pertencente a uma rodada de trabalho. Responsáveis no protótipo são nomes livres, não identidades ou permissões. |
| Rodada | Ciclo de execução associado ao material em preparação. Uma solicitação de ajuste inicia outra rodada antes de criar a versão seguinte. |
| Rascunho | Arquivo em produção ainda não compartilhado. Pode ser substituído antes do envio; o histórico local permite baixar o arquivo anterior enquanto ele existir no IndexedDB. |
| Versão | Material identificado que passou pela revisão interna e pode ser compartilhado para decisão. Aprovação, pedido de ajuste e comentários permanecem associados à versão correspondente. |
| Feedback | Comentário ligado à versão; pode apontar para um ponto da imagem ou instante do vídeo quando houver âncora. Uma tarefa criada a partir dele mantém a referência à origem. |
| Revisão interna | Etapa-alvo que verifica o material antes de compartilhá-lo com o cliente. A função e as exceções por serviço dependem da validação operacional. |
| Decisão do cliente | Aprovação ou pedido de ajustes relativo a uma versão compartilhada. Aprovar uma versão, por si só, não conclui a demanda. |
| Resultado pós-aprovação | Registro de material entregue, publicação agendada ou publicação realizada. O protótipo pede evidência, mas não executa ações em canais externos. |
| Evidência | Registro verificável do resultado, como comprovante de envio ou endereço de publicação. O formato e a função que o conferem devem ser validados por serviço. |
| Demanda concluída | Estado final após registrar o resultado pós-aprovação e uma pessoa designada conferir a evidência. A função concreta responsável pela conferência ainda precisa ser confirmada com um caso real da Mix7. |

## Critério de conclusão

No comportamento-alvo aprovado, aprovação do cliente não encerra automaticamente a demanda. Registre o resultado pertinente — entrega, agendamento, publicação ou outro destino próprio de um módulo futuro — e sua evidência. Uma pessoa designada confere o registro e então conclui a demanda. Se a evidência estiver ausente ou não corresponder ao resultado, a demanda permanece aberta para correção.

O produto já define a sequência acima. A aplicação por tipo de serviço, quem confere, quais comprovantes são válidos e eventuais exceções continuam em aberto até a validação de um caso real anonimizado.
