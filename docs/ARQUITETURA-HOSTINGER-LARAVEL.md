# Aplicação web Mix7 em Laravel

## Decisão

A plataforma será uma aplicação web em Laravel 12, PHP 8.2 como versão mínima compatível e MariaDB da Hostinger como banco, acessível e administrável no phpMyAdmin. A interface web e a API versionada pertencem à mesma aplicação. Um futuro aplicativo Windows poderá usar HTTPS e a API, sem precisar manter um segundo banco ou duplicar regras.

Laravel concentra autenticação, sessão, proteção CSRF, validação, limitação de tentativas, migrações, autorização e acesso ao banco em componentes mantidos. Isso diminui código sensível próprio e facilita documentar as regras. O framework é software livre MIT. PHP e Laravel não têm licença por usuário; o custo de hospedagem existente depende do plano já contratado.

## Compatibilidade e custo

- A aplicação usa PHP 8.2 ou superior e a extensão PDO MySQL. A configuração de Composer fixa a plataforma em PHP 8.2 para não escolher bibliotecas que funcionem só no PHP 8.5 local.
- A Hostinger documenta MariaDB e phpMyAdmin para hospedagem web, com conexão PHP/PDO. Limites de banco, SSH e Composer dependem do plano. Consultar os detalhes do hPanel antes de instalar.
- SSH não está incluído no plano Single Web; Composer no servidor consta nos planos Premium Web e superiores. Se o plano atual não incluir isso, dependências podem ser construídas localmente e enviadas pelo gerenciador de arquivos, mas o método de publicação precisa ser validado no plano antes de publicar.
- Não contratar, renovar ou habilitar serviço adicional como parte desta decisão. Ainda não conhecemos o plano ativo nem o domínio/subdomínio escolhido.

Referências oficiais consultadas em 25/09/2026: [banco de dados na Hostinger](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/), [PHP e ferramentas de banco suportados](https://www.hostinger.com/support/which-databases-and-data-tools-are-supported-at-hostinger/), [limites por plano](https://support.hostinger.com/en/articles/6976044-parameters-and-limits-of-hosting-plans), [SSH](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/) e [Composer](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/).

## Estado implementado

- Aplicação Laravel em `web-app/`, separada do protótipo browser-only em `prototipo/`.
- Tela de entrada sem cadastro público, sessão regenerada no login, saída protegida por CSRF e limite de tentativas.
- Contas possuem organização, estado ativo e um dos quatro papéis citados: direção da agência, gerência de marketing, profissional ou cliente. Isso registra a identidade; a matriz detalhada de acesso a cada recurso ainda precisa ser construída antes de dados reais.
- Comando inicial `php artisan mix7:owner:create` solicita nome, e-mail e senha sem gravar credenciais no repositório. A senha requer pelo menos 12 caracteres. O comando cria a organização Mix7 se ainda não existir e recusa uma segunda conta de direção.
- API `GET /api/v1/me` e leitura `GET /api/v1/demands`, `GET /api/v1/demands/{demand}` protegidas por Sanctum. A API filtra demandas e tarefas por organização e restringe profissionais aos próprios trabalhos.
- Migrações Laravel registram organizações, papéis, tokens, demandas, tarefas e eventos de histórico. MariaDB é o destino; desenvolvimento local e testes usam SQLite.
- Demandas/tarefas têm telas, transições de estado com validação, autoria de ações e atribuição. A direção pode cadastrar contas profissionais vinculadas à organização, com senha armazenada por hash e sem cadastro público. Essa autorização é provisória até a matriz dos quatro papéis ser confirmada.
- O profissional vê tarefas atribuídas a ele; o histórico também esconde eventos de tarefas de colegas. A revisão da demanda e briefing continuam visíveis para quem trabalha nela, necessários ao contexto da execução.
- O cronômetro web registra intervalos na tabela `task_time_entries`, ligados a tarefa, profissional e organização. Um bloqueio transacional na linha do usuário serializa o início e impede mais de uma sessão ativa. Pausar salva o intervalo; retomar cria outro; concluir/impedir/pausar a tarefa fecha a sessão ativa. O contador do cabeçalho é calculado pelos intervalos salvos e continua visível ao navegar.
- A revisão do cliente por link fica em `demand_review_links` e `demand_review_responses`. O token tem 64 caracteres aleatórios e somente seu SHA-256 é persistido; o material é um URL HTTP/HTTPS informado pela gerência, a validade é escolhida no envio no fuso local do navegador e a gerência pode revogar. Criar nova versão revoga links não revogados anteriores. Rotas públicas têm limite de requisições; uma decisão final impede novas respostas e altera a etapa da demanda. Nome é autodeclarado. Cada resposta pode guardar âncora JSON com trecho, coordenadas percentuais, instante de vídeo ou página e URL da versão. A tela pública mostra somente o material escolhido, título e versão; não mostra briefing nem tarefas. Esta é uma política técnica inicial, ainda pendente de validação da Mix7 antes de material real; arquivo privado e captura visual automática ainda não existem.

## Desenvolvimento local

Na raiz do repositório:

```powershell
cd web-app
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan mix7:owner:create
php artisan serve
```

Abra `http://127.0.0.1:8000`. Em `.env`, configurar `DB_CONNECTION=mysql`, host, nome, usuário e senha do banco local ou Hostinger. Nunca copiar credenciais reais para `.env.example`, GitHub, Trello ou capturas.

## Preparação da hospedagem

No hPanel, confirmar plano vigente, versão PHP 8.2+, PDO MySQL, espaço disponível, limites MariaDB, SSH/Composer, domínio e certificado HTTPS. Criar banco e usuário de banco separados no hPanel; phpMyAdmin administra o banco, mas não deve ser publicado aberto no site da aplicação. Guardar valores secretos somente no `.env` do servidor.

O diretório público do domínio deve apontar para `web-app/public/`; código, `.env`, `vendor/`, logs e arquivos privados ficam fora do diretório público. Definir `APP_ENV=production`, `APP_DEBUG=false`, chave própria de produção, HTTPS e permissões de escrita restritas em `storage/` e `bootstrap/cache/`. Executar migrações versionadas após cópia de segurança. Se o plano não permitir SSH, validar upload completo de dependências e importação de esquema pelo phpMyAdmin antes de escolher o procedimento alternativo.

Nenhum deploy foi feito e não foram acessados painel, domínio, banco ou credenciais da Hostinger. A revisão externa foi exercitada somente com banco de teste/local e dados sintéticos.

## Contrato futuro do executável Windows

O desktop será um cliente da API HTTPS do mesmo sistema. Endpoints serão versionados (`/api/v1`); autenticação de cliente desktop usará tokens pessoais com escopo mínimo, expiração e revogação. O desktop não terá acesso direto ao MariaDB, não guardará senha de banco e não duplicará regras de negócio. Hoje há leitura autenticada de identidade e demandas; escrita de demandas/tarefas e comandos de cronômetro pela API, aprovações, anexos e outros módulos ainda são trabalho futuro.

## Não pronto para uso com dados reais

As permissões por papel e isolamento por cliente ainda não estão completos; o MVP não deve receber material real ou links de cliente. O cronômetro não reconcilia encerramento abrupto nem registra tempo offline. Também faltam fluxo seguro de provisionamento e recuperação de contas, trilha de auditoria completa, aprovação das regras do link, marcação visual em sites/imagens/vídeos, armazenamento privado de anexos, cópias de segurança e restauração exercitada, gestão operacional de tokens e confirmação do plano Hostinger. A fundação e as primeiras telas de trabalho habilitam desenvolvimento; não representam a plataforma completa.
