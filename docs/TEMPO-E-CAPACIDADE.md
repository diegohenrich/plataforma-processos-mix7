# Tempo, disponibilidade e capacidade

Este documento separa três informações que atendem a perguntas diferentes. O cronômetro responde **quanto tempo foi trabalhado**; a estimativa responde **quanto trabalho se prevê para uma tarefa**; a disponibilidade responde **quanto tempo a pessoa pode dedicar no período**. A capacidade ajuda a perceber se as estimativas cabem no tempo disponível.

## Comportamento confirmado para o profissional

- A pessoa escolhe uma tarefa liberada e inicia ou pausa o cronômetro pela demanda minimizada.
- A atribuição inicia a contagem de espera. O primeiro clique em “Iniciar tempo” registra o aceite e abre a primeira sessão; alterar o status para “Em andamento” sem iniciar o cronômetro é recusado. Pausar e retomar não reinicia o tempo de aceite.
- Ao transferir uma tarefa, a atribuição anterior é encerrada e uma nova começa. O histórico mantém responsável, quem atribuiu, momento da atribuição, primeiro início e conclusão, para que os tempos de cada pessoa não se misturem.
- Na fila pessoal e no quadro, a tarefa ainda não iniciada mostra continuamente há quanto tempo está aguardando. A idade é um dado factual, sem prazo automático, punição ou pontuação.
- O tempo é registrado na tarefa selecionada. Uma tarefa em pausa pode ser retomada; não se inicia outra sessão simultânea para a mesma pessoa.
- Enquanto o sistema está aberto, só o intervalo iniciado pelo cronômetro conta como tempo realizado. Uma pausa encerra esse intervalo.
- A tela envia um sinal ao servidor a cada 20 segundos enquanto há cronômetro ativo. Se os sinais pararem por mais de 180 segundos, o sistema encerra o intervalo no último sinal confirmado, pausa a tarefa e registra a pausa automática sem atribuí-la a uma ação humana. Ao retornar, o profissional vê o cronômetro encerrado e pode iniciar outro intervalo; os dados anteriores à implantação desta regra continuam recuperáveis manualmente. A detecção pode levar até três minutos, mas esse período não é incluído no tempo registrado. A suspensão prolongada da aba pode encerrar a sessão mesmo que o aplicativo continue aberto, portanto a pessoa deve conferir o timer ao voltar.
- A estimativa é um dado de planejamento separado do tempo realizado. O sistema não deve substituir a estimativa pelo cronômetro nem alterar estimativas automaticamente.

## Capacidade como proposta para validação

Para planejamento, a proposta é calcular a disponibilidade no período a partir da jornada configurada da pessoa, descontando ausências e períodos não trabalhados que a Mix7 confirmar. A carga planejada agrega o esforço restante estimado das tarefas que ocupam o mesmo período. O painel pode comparar carga e disponibilidade, destacar excesso ou falta de estimativa e mostrar as tarefas que compõem o total.

Essa regra é uma proposta, não uma política aprovada. Horários, pausas, feriados, ausências, reuniões, tarefas sem estimativa, distribuição de tarefas longas entre dias, bloqueios externos e tratamento de tarefas paralelas precisam de exemplos reais. O sistema não deve escolher ou redistribuir responsáveis por conta própria; qualquer sugestão da IA exige revisão humana.

### Prévia no protótipo local

O protótipo deixa escolher um profissional com tarefas abertas, um período de semana ISO (segunda a domingo), horas previstas de trabalho e ausências em horas por dia. Não há valores iniciais de jornada. Para comparação, soma a estimativa integral de cada tarefa aberta atribuída à pessoa cujo prazo cai na semana; tarefas sem estimativa ou sem prazo aparecem como lacunas. O cronômetro não altera esses totais. Os dados são demonstrativos e ficam apenas neste navegador. A janela semanal e a regra baseada no prazo são escolhas de ensaio, não regras aprovadas pela Mix7.

### Prévia compartilhada no Laravel

A página Disponibilidade grava previsões semanais manuais em banco: horas totais informadas pela direção/gerência, ausências por data e tarefas abertas da pessoa com prazo final na semana. Ausências são descontadas; tarefas sem estimativa e sem prazo aparecem separadas, fora da soma. Alterações criam snapshots com autoria, preservando histórico. Profissionais consultam somente a própria semana; direção/gerência selecionam pessoas ativas da própria organização e registram alterações. Não há jornada padrão, motivo de ausência, distribuição diária, mudança automática de tarefa ou uso do cronômetro no cálculo.

Esta tela reproduz a regra do protótipo como **previsão de planejamento**, não como medição oficial, política de jornada ou decisão de desempenho. A escolha de somar a estimativa inteira pelo prazo da semana permanece sujeita a validação da Mix7. A inspeção visual renderizada desta nova tela ainda está pendente.

## Relação com calendário e Gantt

O Gantt deve mostrar prazos, duração planejada e dependências registradas. O tempo medido pode apoiar análise posterior, mas não muda datas, capacidade, estimativas ou responsáveis sem uma ação revisada e registrada. Alterar uma estimativa ou período deve preservar o valor anterior, autor e motivo quando a regra for definida.

## Critérios de aceite

1. Sem iniciar o cronômetro, o profissional não consegue marcar uma tarefa “Em andamento” nem registra tempo de execução. Iniciar e pausar cria intervalos associados à tarefa e à pessoa autenticada; a soma corresponde somente aos intervalos ativos.
2. Reabrir a demanda minimizada mantém o mesmo cronômetro e o histórico; iniciar outra tarefa enquanto houver sessão ativa é bloqueado ou exige a ação de pausa definida pela Mix7.
3. Navegar entre telas mantém o cronômetro. Se o navegador parar de enviar sinais por mais de 180 segundos, o sistema encerra o intervalo no último sinal confirmado, pausa a tarefa e registra evento automático. A tolerância atrasa a detecção, mas não é contabilizada; uma aba suspensa por longo período pode exigir que a pessoa inicie o timer novamente.
4. Estimativa, tempo realizado e disponibilidade permanecem campos/medidas distintos; corrigir um não reescreve os outros.
5. A prévia local compara horas semanais inseridas manualmente, ausências registradas e estimativas das tarefas com prazo no período; sobrecarga e tarefas sem estimativa ficam visíveis. A regra operacional só se conclui após a Mix7 aprovar jornada, ausências e alocação.

O especialista de operação/produção da IA pode consultar esses mesmos fatos da semana atual para responder perguntas da direção/gerência. A ferramenta não retorna nomes de tarefas, não cria estimativas nem recomenda redistribuição; qualquer conteúdo enviado a um provedor continua sujeito à política de dados da Mix7, que ainda precisa ser aprovada.

No planejamento estruturado, direção/gerência também pode marcar uma opção para enviar ao agente somente os totais agregados da semana escolhida: profissionais ativos, quantos têm capacidade registrada, minutos disponíveis após ausências, estimativas em tarefas com prazo e lacunas. A entrada não inclui nomes, títulos de tarefas nem identificadores individuais. A revisão da proposta mostra os mesmos totais enviados e uma observação preliminar do agente, que a gestão confere com os valores antes de decidir. Sem contexto de capacidade, o servidor rejeita uma observação que afirme carga ou disponibilidade. O agente pode usar os dados como alerta preliminar de esforço, sem selecionar, comparar ou avaliar pessoas. A gestão ainda escolhe cada responsável e aprova as tarefas. A opção desmarcada não envia dados de capacidade; marcá-la não substitui a aprovação da política de dados da Mix7.
6. O Gantt respeita prazos e dependências salvos; dados do cronômetro não deslocam o cronograma automaticamente.
7. Direção e gerência da organização veem, por profissional, tarefas aguardando início, o maior tempo de espera atual e médias móveis de 30 dias de (a) atribuição até o primeiro início e (b) primeiro início até conclusão. O tempo efetivamente cronometrado continua separado do prazo de execução em calendário. Profissionais veem apenas os próprios indicadores; cliente não tem acesso. Transferências preservam as medidas de cada atribuição anterior.

## Perguntas que ainda precisam de resposta

- Qual jornada semanal vale para cada pessoa e como são tratadas pausas, feriados e dias parciais?
- Quem informa e aprova férias, ausências, reuniões e bloqueios de disponibilidade?
- Como distribuir uma estimativa que atravessa vários dias? Como representar tarefas sem estimativa ou com duração incerta?
- Uma pessoa pode trabalhar em mais de uma demanda ao mesmo tempo? O cronômetro continua exclusivo ou haverá apontamento dividido?
- O que acontece quando uma tarefa fica bloqueada ou muda de escopo? Quem altera estimativa, prazo e capacidade?
- Quem pode ver tempos individuais e relatórios, por quanto tempo, e qual uso é permitido para avaliação?

Até essas respostas serem validadas, a prévia semanal é um cálculo manual preliminar, não política oficial de jornada, medição de produtividade ou base automática para avaliação.
