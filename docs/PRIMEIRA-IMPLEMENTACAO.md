# Primeira implementação: fluxo local de demandas

**Estado:** fatia funcional para demonstração e validação; não é ambiente de produção nem decisão de stack.

## O que já executa

A tela `prototipo/` mantém os cartões no quadro e na lista, registra briefing, busca e detalhes, permite planejar tarefas com responsável, estimativa e prazo, acompanhar sua conclusão, aceita arquivo local de imagem, vídeo ou PDF de até 15 MB, cria versões, grava comentários e eventos, aplica decisões e avança a demanda pelas regras do fluxo.

O caminho implementado é:

`Briefing → Planejamento → Produção → Revisão interna → Aprovação do cliente → (Ajustes → nova versão → revisão) → Entrega/publicação → Concluída`

O plano exige ao menos uma tarefa atribuída; revisão interna exige que as tarefas da rodada estejam concluídas e que haja um arquivo. O cliente só decide uma versão compartilhada após a revisão interna. Pedido de ajustes requer justificativa e fica preso à versão; a nova versão exige tarefas da rodada de ajuste concluídas e preserva a decisão anterior. A aprovação conduz a entrega, mas só há conclusão após registrar evidência.

## Implementação atual

- Interface: HTML, CSS e JavaScript nativos, reutilizando o protótipo visual.
- Regras de transição puras e testadas em `prototipo/workflow.js`.
- Demandas, versões, comentários e trilha local em `localStorage` (`mix7.workflow.v1`).
- Arquivos binários em `IndexedDB` (`mix7.workflow.assets`).
- Sem dependências de build; roda servindo a pasta `prototipo/` por HTTP local.
- Itens iniciais são ficcionais; comentários e material ilustrativo também são fictícios.

## Limites e tratamento de dados

O navegador serve apenas para validar comportamento. Cada perfil de navegador tem dados próprios, sem sincronização ou backup gerenciado. Limpar os dados do site pode removê-los. Não use conteúdo de clientes, credenciais ou informação pessoal. A tela não tem login, isolamento por cliente, autorização de servidor, portal do cliente, trilha de auditoria à prova de adulteração, controle de retenção ou recuperação de backup. Os controles locais não constituem aprovação enviada ao cliente.

Comentários em vídeo ainda não têm timecode e comentários em imagem ainda não têm coordenadas. As tarefas usam nomes livres e não são contas de membros: não há gestão de equipe/autorização, cronômetro ou capacidade. Também não há notificações, IA, integrações, nem registro separado de agendamento vs. publicação. Esses requisitos seguem na especificação priorizada.

## Verificação

Executar da raiz:

```powershell
node --test tests/workflow.test.js
node --check prototipo/workflow.js
node --check prototipo/app.js
```

Os testes cobrem plano com tarefa atribuída, bloqueio de revisão com tarefas incompletas, fluxo feliz, aprovação de versão não compartilhada, motivo obrigatório na revisão interna, pedido de alteração ligado à versão antiga, preservação do histórico na versão seguinte e evidência obrigatória para concluir. A inspeção em navegador também deve conferir persistência após recarga, seleção e abertura de arquivo e layout em desktop/celular antes de tratar esta fatia como revisada.

## Próximas decisões

Antes de usar dados reais, fechar o caso real da Mix7 e sua matriz de papéis; escolher persistência central, autenticação, isolamento, hospedagem, backup, retenção e integração de arquivos; completar comentários ancorados em mídia. A escolha final de tecnologias deve seguir a pesquisa e as restrições operacionais.
