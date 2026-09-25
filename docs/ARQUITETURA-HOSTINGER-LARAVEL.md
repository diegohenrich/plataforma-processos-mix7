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
- API `GET /api/v1/me` protegida por token Laravel Sanctum, como início do contrato que o cliente Windows poderá usar. Não há ainda tela ou procedimento operacional de emissão/revogação de tokens para equipe.
- Migrações Laravel registram organizações, papéis e tokens pessoais. MariaDB é o destino; desenvolvimento local pode usar SQLite.

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

Nenhum deploy foi feito e não foram acessados painel, domínio, banco ou credenciais da Hostinger.

## Contrato futuro do executável Windows

O desktop será um cliente da API HTTPS do mesmo sistema. Endpoints serão versionados (`/api/v1`); autenticação de cliente desktop usará tokens pessoais com escopo mínimo, expiração e revogação. O desktop não terá acesso direto ao MariaDB, não guardará senha de banco e não duplicará regras de negócio. O contrato atual expõe apenas a identidade autenticada; criar demandas, tarefas, cronômetros, aprovações, anexos e demais módulos ainda é trabalho futuro.

## Não pronto para uso com dados reais

As permissões por papel e isolamento por cliente ainda não estão completas; o MVP não deve receber material real ou links de cliente. Também faltam provisionamento e recuperação de contas, trilha de auditoria, política segura para links externos, armazenamento privado de anexos, cópias de segurança e restauração exercitada, emissão/revogação de tokens e operação da Hostinger confirmada. A fundação habilita o trabalho; não representa a plataforma completa.
