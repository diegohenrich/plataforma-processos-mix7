# Página da demanda: propriedades e contexto

## Comportamento

Cada demanda tem um responsável principal, definido como profissional ativo da organização. As tarefas continuam podendo ser atribuídas a outros profissionais. Se uma integração antiga não enviar o responsável, o primeiro executor da lista inicial é usado; o formulário da aplicação exige seleção explícita.

Quem cria a demanda também é registrado como quem preparou o briefing. O canal de entrada deixa de ser perguntado e não aparece na página profissional. A migração corrige a autoria das demandas antigas para o criador sem apagar o dado histórico do canal no banco.

A página destaca propriedades compactas (responsável, preparador/solicitante, cliente, etapa e arquivos), depois mostra briefing, materiais e instruções para solicitar acesso. A pessoa que cria é a referência quando algo estiver faltando e deve disponibilizar os materiais. Arquivos podem ser anexados à própria demanda. Não se guardam senhas, tokens ou chaves nesses campos.

A equipe pode pedir uma solução à IA pela demanda. O modelo recebe briefing, responsável, preparador, local dos materiais e orientações de acesso. Quando algo não foi informado, deve apontar a pendência com quem criou a demanda; não pode inventar links, contas ou requisitos. A sugestão é gravada para análise, e nenhuma tarefa é criada nem executada. O cliente não recebe esses campos nem acessa o assistente interno.

## Referência de organização

As capturas fornecidas mostram dois padrões úteis, sem adotar o tema escuro do Notion: (1) quadro com colunas por etapa e cartões enxutos, com responsável claramente visível; (2) ao abrir um item, propriedades de pessoas, status, datas e links agrupadas antes do conteúdo, com comentários e arquivos associados ao próprio item. A plataforma mantém a paleta clara e azul da Mix7.

Esses padrões correspondem aos recursos oficiais descritos pelo Notion: registros de banco abrem como páginas e propriedades podem ser organizadas por visualização; quadros agrupam registros por status e podem filtrar/ordenar. O Trello documenta cartões com membros, campos, anexos e checklists, mantendo execução e contexto dentro do mesmo cartão.

- [Notion: páginas e bancos de dados](https://www.notion.com/help/intro-to-databases)
- [Notion: quadros](https://www.notion.com/help/boards)
- [Notion: propriedades de bancos](https://www.notion.com/help/database-properties)
- [Trello: cartões](https://support.atlassian.com/trello/docs/add-and-customize-cards/)
- [Trello: anexos](https://support.atlassian.com/trello/docs/adding-attachments-to-cards/)

## Validação local — 2026-10-02

- Migration aditiva aplicada ao SQLite local; os três registros existentes ficaram com autor e responsável preenchidos.
- Suíte Laravel: 258 testes e 2.539 verificações passaram; quatro testes de integração externos foram ignorados por padrão. Build Vite e Pint focalizado também passaram.
- Página de detalhe e formulário conferidos no navegador local em desktop e viewport de 390 px. No detalhe #407, o criador aparece como autor do briefing, o profissional aparece como responsável e os campos de canal/preparador não se repetem no briefing. Em 390 px, corpo e documento mantiveram 375 px sem rolagem horizontal global; o menu usa rolagem própria.
- A conexão OpenClaw respondeu a uma solicitação sintética usando o mesmo formato do recurso “Solução sugerida pela IA”: solução, próximos passos, materiais/acessos pendentes e item a confirmar. Resposta em 4,1 segundos; nenhum dado real foi enviado ou salvo. Testes automatizados verificam armazenamento, autorização e proteção do cliente.
- A sessão visual usada foi a de gerência; o perfil profissional ainda não foi aberto separadamente. A autorização por demanda e a ausência dos campos removidos foram cobertas por testes automatizados e pela inspeção da tela comum de detalhe.
- Sugestões ficam para revisão humana; não criam tarefas nem executam ações.
