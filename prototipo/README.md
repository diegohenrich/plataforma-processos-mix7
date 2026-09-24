# Primeira fatia local do fluxo integrado

Aplicação experimental de navegador, construída sobre o protótipo visual para testar uma jornada funcional sem escolher a stack final. Para servir a pasta, execute `py -m http.server 4173 --bind 127.0.0.1` a partir de `prototipo/` e abra `http://127.0.0.1:4173`.

Demandas e eventos são gravados no `localStorage`; arquivos de até 15 MB (imagem, vídeo ou PDF) ficam no `IndexedDB` do navegador. Os dados não são sincronizados entre computadores ou pessoas. Os cartões que aparecem inicialmente são fictícios; itens novos ficam salvos neste perfil do navegador. Não inserir dados de clientes ou materiais reais.

Não há autenticação, separação de clientes, portal externo, API, servidor, backup ou publicação social. Aprovação e comentários são registros locais demonstrativos, não comunicações enviadas ao cliente. Esta implementação não define a arquitetura nem a stack de produção.

## Percursos para revisar

1. Criar demanda e conferir que ela começa como briefing com origem, canal/peça e critérios de aceite registrados; prazo e referências são opcionais neste protótipo.
2. Tentar avançar um briefing antigo sem esses dados: o sistema deve listar o que falta e bloquear planejamento. Preencher pelo formulário do briefing e conferir o histórico da alteração.
3. Atribuir ao menos uma tarefa com responsável e estimativa ou prazo opcional e confirmar planejamento.
4. Durante a execução, alterar o briefing com motivo; confirmar que a demanda volta ao planejamento, preserva a versão anterior e não retoma até alguém revisar e confirmar o plano.
5. Concluir todas as tarefas da rodada; anexar uma imagem/vídeo/PDF fictício. Sem tarefa concluída ou arquivo, o envio à revisão fica bloqueado.
6. Percorrer revisão interna e compartilhamento da versão; devolver à produção e exigir justificativa.
7. Simular pedido de alterações do cliente, atribuir e concluir tarefa(s) para o ajuste, criar versão 2 e conferir que decisão/comentário da versão 1 permanecem.
8. Aprovar a versão 2 e conferir que a demanda continua aberta até registrar evidência de entrega/publicação.
9. Conferir quadro/lista, busca, recarga da página e persistência local; testar em desktop e celular.

Os testes de regra de negócio usam `node --test tests/workflow.test.js` na raiz do repositório.

## Estado do fluxo

Esta fatia materializa regras do fluxo-alvo aceito: revisão interna, decisão do cliente vinculada à versão, nova rodada de ajustes e evidência antes de concluir. Os papéis concretos, exceções e evidência por tipo de serviço seguem sujeitos à validação com um caso real da Mix7. É uma avaliação local sem controles de produção.

O briefing inicial captura origem, canal/peça e critérios de aceite; sem eles, a demanda não avança ao planejamento. Referências e prazo aparecem no histórico, mas são opcionais nesta demonstração. Essa regra mínima é uma hipótese de protótipo para testar completude; campos obrigatórios específicos por tipo de serviço devem ser confirmados com um caso real.
