# Demonstração local

A demonstração permite explorar os papéis e áreas principais usando somente conteúdo sintético no SQLite isolado `web-app/database/mix7-demo.sqlite`. Ela não usa o CRM Mix7 publicado, seus arquivos de banco ou a hospedagem.

## Carga disponível

- 34 contas: 1 direção, 6 gerências, 14 profissionais ativos, 1 profissional inativo e 12 clientes.
- 52 demandas distribuídas entre as oito etapas, com 148 tarefas, responsáveis, estimativas, prazos, dependências, histórico e registros de tempo. Uma tarefa da conta profissional principal começa com o cronômetro ativo para mostrar a bandeja de trabalho.
- 3 tipos adicionais de aprovação, além dos tipos padrão, com links públicos em demandas de exemplo para testar versões, comentários e decisões.
- Anexo PDF interno e materiais públicos de exemplo, evidências de entrega, avaliações e respostas, capacidade, biblioteca, trilhas de onboarding, catálogo de acessos e convite pendente.

Demandas, tarefas e itens da biblioteca começam com `[DEMO]`. Briefings, comentários, nomes e materiais também são inventados e podem ser recriados.

## Entrar

O e-mail principal da direção é `direcao@mix7-demo.test`. Também existem `gerencia@mix7-demo.test`, `profissional@mix7-demo.test` e `cliente@mix7-demo.test`. As contas adicionais seguem os padrões `gerencia02` a `gerencia06`, `profissional02` a `profissional14` e `cliente02` a `cliente12`, todas no domínio `@mix7-demo.test`.

O seeder cria uma senha temporária aleatória e a imprime no terminal. Ela é igual para as contas criadas naquela execução e muda sempre que a carga é executada; não deve ser registrada no repositório ou usada fora desta instalação local. A conta `profissional-inativo@mix7-demo.test` serve para conferir bloqueio de acesso e não pode entrar.

## Recriar os exemplos

Na pasta `web-app`, com `APP_ENV=local`, use a conexão SQLite e configure exatamente o arquivo dedicado no `.env`:

```powershell
$env:DB_DATABASE = (Join-Path (Get-Location) 'database\mix7-demo.sqlite')
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\DemoWorkspaceSeeder' --force
```

O seeder atualiza registros de demonstração existentes e também renova a senha temporária e os tokens dos links de aprovação. Guarde somente na sessão local a senha e os links impressos; links anteriores deixam de ser válidos após nova execução.

O servidor local usado nesta sessão está em `http://127.0.0.1:4292`. Para iniciar outro, primeiro confira se a porta já está ocupada e mantenha o servidor restrito a `127.0.0.1`.
