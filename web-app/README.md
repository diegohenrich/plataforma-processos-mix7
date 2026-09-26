# Plataforma de Processos Mix7

Aplicação Laravel da plataforma integrada de demandas, equipe e aprovações. O projeto está em desenvolvimento local; ainda não está implantado nem pronto para dados reais. Consulte a [auditoria por perfil](../docs/AUDITORIA-FUNCIONAL-POR-PERFIL.md) e o [roteiro completo](../docs/ROADMAP.md) para limites e pendências.

## Preparar demonstração local

Use uma cópia local do repositório, PHP 8.2+, Composer, Node.js 22.13+ (série 22) ou 24+, e SQLite. Não conecte este banco de demonstração à instalação do CRM Mix7 nem ao banco da Hostinger.

No PowerShell:

```powershell
Set-Location "C:\caminho\para\Gestão Mix7\web-app"
Copy-Item .env.example .env # execute somente quando ainda não houver .env
composer install
npm ci
if (-not (Test-Path database\mix7-demo.sqlite)) { New-Item -ItemType File database\mix7-demo.sqlite | Out-Null }
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=4210
```

Se já existir um `.env`, preserve-o e confirme `APP_ENV=local`, `APP_URL=http://127.0.0.1:4210`, `DB_CONNECTION=sqlite` e `DB_DATABASE=database/mix7-demo.sqlite`. O seeder só funciona em SQLite e no arquivo exclusivo `web-app/database/mix7-demo.sqlite`; a rotina padrão não cria usuário de teste. O arquivo do banco é ignorado pelo Git.

`php artisan migrate --seed` cria organização, direção, gerência, profissional, cliente, demandas em etapas distintas, tarefas, link de aprovação, avaliação, prévia de capacidade e onboarding fictícios. O comando imprime os quatro e-mails, uma senha aleatória de uso local e o link público da aprovação. Cada nova execução gera outra senha. Nunca reutilize credenciais de demonstração fora deste ambiente; não são contas da Mix7.

## Validações locais

```powershell
php artisan test --compact
vendor/bin/pint --test
composer validate --no-check-publish
npm run build
```

As decisões de MariaDB/Hostinger, SMTP, IA, política de dados, permissões e operação de produção estão documentadas em [`../docs/ARQUITETURA-HOSTINGER-LARAVEL.md`](../docs/ARQUITETURA-HOSTINGER-LARAVEL.md). Não faça deploy nem migração de banco usando este arquivo de demonstração.
