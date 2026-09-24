# Primeira fatia local do fluxo integrado

Aplicação experimental de navegador, construída sobre o protótipo visual para testar uma jornada funcional sem escolher a stack final. Para servir a pasta, execute `py -m http.server 4173 --bind 127.0.0.1` a partir de `prototipo/` e abra `http://127.0.0.1:4173`.

Demandas e eventos são gravados no `localStorage`; arquivos de até 15 MB (imagem, vídeo ou PDF) ficam no `IndexedDB` do navegador. Os dados não são sincronizados entre computadores ou pessoas. Os cartões que aparecem inicialmente são fictícios; itens novos ficam salvos neste perfil do navegador. Não inserir dados de clientes ou materiais reais.

Não há autenticação, separação de clientes, portal externo, API, servidor, backup ou publicação social. Aprovação e comentários são registros locais demonstrativos, não comunicações enviadas ao cliente. Esta implementação não define a arquitetura nem a stack de produção.

## Percursos para revisar

1. Criar demanda e conferir que ela começa como briefing com origem, canal/peça e critérios de aceite registrados; prazo e referências são opcionais neste protótipo.
2. Tentar avançar um briefing antigo sem esses dados: o sistema deve listar o que falta e bloquear planejamento. Preencher pelo formulário do briefing e conferir o histórico da alteração.
3. Atribuir tarefas com responsável; opcionalmente ligar tarefas prévias da mesma rodada. A tarefa dependente fica bloqueada até que as anteriores sejam concluídas; o histórico registra a relação.
4. Filtrar por esse responsável e conferir que quadro e lista mostram demandas com tarefas pendentes da rodada atual; a busca deve continuar funcionando junto do filtro. Ao concluir a tarefa, a demanda sai da visão dessa pessoa. O filtro é somente visual, não uma permissão de acesso.
5. Durante a execução, alterar o briefing com motivo; confirmar que a demanda volta ao planejamento, preserva a versão anterior e não retoma até alguém revisar e confirmar o plano.
6. Sinalizar um impedimento com motivo em uma tarefa em execução; a conclusão fica bloqueada até remover o impedimento. As mudanças aparecem no histórico. Concluir todas as tarefas da rodada e anexar uma imagem/vídeo/PDF fictício; sem tarefas concluídas ou arquivo, o envio à revisão fica bloqueado.
7. Percorrer revisão interna e compartilhamento da versão; devolver à produção e exigir justificativa.
8. Simular pedido de alterações do cliente, atribuir e concluir tarefa(s) para o ajuste, criar versão 2 e conferir que decisão/comentário da versão 1 permanecem.
9. Aprovar a versão 2, selecionar se o resultado foi entrega, agendamento ou publicação e conferir que a demanda continua aberta até registrar evidência.
10. Conferir histórico com versão, arquivo anterior/atualização, motivo do pedido e resultado pós-aprovação; recarregar para confirmar persistência local. Testar quadro/lista, busca e filtro em desktop e celular.

Os testes de regra de negócio usam `node --test tests/workflow.test.js` na raiz do repositório.

## Estado do fluxo

Esta fatia materializa regras do fluxo-alvo aceito: revisão interna, decisão do cliente vinculada à versão, nova rodada de ajustes e evidência antes de concluir. Os papéis concretos, exceções e evidência por tipo de serviço seguem sujeitos à validação com um caso real da Mix7. É uma avaliação local sem controles de produção.

O briefing inicial captura origem, canal/peça e critérios de aceite; sem eles, a demanda não avança ao planejamento. Referências e prazo aparecem no histórico, mas são opcionais nesta demonstração. Essa regra mínima é uma hipótese de protótipo para testar completude; campos obrigatórios específicos por tipo de serviço devem ser confirmados com um caso real.
