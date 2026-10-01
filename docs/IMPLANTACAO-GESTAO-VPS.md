# Implantação inicial de Gestão Mix7 na VPS

Este procedimento cria somente o projeto Docker `gestao-mix7`, usa o banco novo do Supabase e adiciona a rota `gestao.mix7.org` ao Traefik existente. Não use `docker compose down -v`, `docker system prune`, nem comandos sem os arquivos Compose deste projeto. Eles podem atingir dados ou serviços da VPS que não pertencem à Gestão Mix7.

## Antes de começar

- O DNS A de `gestao.mix7.org` foi confirmado em resolvedores públicos (1.1.1.1 e 8.8.8.8) como `72.61.51.41`, igual ao IP da captura do hPanel. Confirme que o IP ainda é da VPS antes de publicar. Não há registro `AAAA`; não crie um sem IPv6 configurado na VPS.
- O projeto Supabase deve ser exclusivo da Gestão Mix7 e ainda não conter dados. A VPS já confirmou conexão TLS ao usuário `postgres` via Session pooler/porta 5432; isso confirma rede e credenciais naquele teste, mas não confirma as migrations Laravel.
- Não publique dados reais ainda. A aplicação não implementa RLS do Supabase e as regras completas de permissões, o envio SMTP e a restauração PostgreSQL + anexos ainda não foram aceitos em teste.
- Confirme em checagens somente leitura que `/opt/gestao-mix7` está livre, o Traefik `websecure`/`letsencrypt` segue ativo e há espaço para construir uma imagem. Preserve todos os serviços fora deste projeto.

Na SSH da VPS, este bloco só consulta o estado. Continue apenas se a primeira linha disser `LIVRE`:

```bash
if [ -e /opt/gestao-mix7 ]; then echo 'EXISTE: pare e confira a pasta'; else echo 'LIVRE: /opt/gestao-mix7'; fi
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
docker network ls
df -h /
```

## Criar configuração privada

Na sessão SSH da VPS, use o repositório e a branch/commit publicados no GitHub. Não copie o `.env` local de demonstração.

```bash
cd /opt
git clone --branch codex/fundacao-compartilhada --single-branch https://github.com/diegohenrich/plataforma-processos-mix7.git gestao-mix7
cd /opt/gestao-mix7/web-app
cp docker/env.vps.example .env
chmod 600 .env
nano .env
```

Preencha no editor:

- `APP_KEY`: deixe vazio por enquanto; gere-a depois do build e cole o resultado no arquivo.
- `DB_HOST`: host do Session pooler mostrado pelo Supabase.
- `DB_USERNAME`: `postgres.` seguido do identificador do projeto Supabase.
- `DB_PASSWORD`: senha do banco, sem os colchetes do placeholder. Não a cole no chat. Se contiver caracteres especiais, mantenha o valor entre aspas simples no `.env`.
- `MAIL_*`: ficam em transporte `log` inicialmente, então recuperação de senha/convites não serão entregues por e-mail. Configure SMTP antes de convidar pessoas.

Salve o arquivo no `nano` (`Ctrl+O`, Enter, `Ctrl+X`) e confira somente permissões e nomes, sem mostrar valores:

```bash
stat -c '%a %n' .env
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_SSLMODE|MAIL_MAILER)=' .env | cut -d= -f1
```

A permissão esperada é `600`. Não use `cat .env`, não cole valores secretos em comandos e não envie capturas com segredos visíveis.

## Construir, migrar e iniciar

O build acontece apenas neste projeto e pode levar alguns minutos. Não inicia nem recria os contêineres Mix7/Traefik já existentes.

```bash
docker compose -f docker-compose.vps.yml build --pull app scheduler
```

Depois que o build terminar, gere uma chave de aplicação e cole a linha impressa em `APP_KEY` no `.env`. Essa chave é secreta; não a compartilhe. Salve o arquivo e confirme novamente a permissão `600`.

```bash
docker compose -f docker-compose.vps.yml run --rm app php artisan key:generate --show
```

Consulte o estado das migrations. Se falhar com `ENOIDENTIFIER`, **não rode migrate**: no Supabase, abra Connect → Session pooler e confira o host exato e o usuário `postgres.<PROJECT_REF>` da URI. O Project ID no sufixo deve ser do novo projeto Gestão Mix7. Esse erro indica tenant/host/usuário do pooler não identificado; não redefina a senha sem antes conferir esses dados.

```bash
docker compose -f docker-compose.vps.yml run --rm --no-deps app php artisan migrate:status
```

Só se o status mostrar o banco novo e as migrations como pendentes, aplique e continue:

```bash
docker compose -f docker-compose.vps.yml run --rm --no-deps app php artisan migrate --force
docker compose -f docker-compose.vps.yml run --rm --no-deps app php artisan mix7:owner:create
docker compose -f docker-compose.vps.yml up -d app scheduler
```

`mix7:owner:create` pede nome, e-mail e senha interativamente; crie uma senha nova e exclusiva. Não rode `db:seed` nem `migrate:fresh`.

Confira somente este projeto:

```bash
docker compose -f docker-compose.vps.yml ps
docker compose -f docker-compose.vps.yml logs --tail=80 app scheduler
```

Inspecione a saída de logs antes de compartilhá-la; não publique endereços de e-mail nem dados de pessoas. Abra `https://gestao.mix7.org`. O certificado será solicitado pelo Traefik quando o DNS estiver propagado e a rota estiver acessível. O app não publica portas diretamente: o acesso público depende do Traefik. Se aparecer `404`, confira DNS e labels do app; se aparecer `502`, confira o healthcheck e os logs do app. Não reinicie o Traefik nem outros projetos para resolver falhas desta instalação.

## Atualizar com segurança

Antes de cada atualização, tenha cópia do banco e dos arquivos privados fora da VPS e guarde a `APP_KEY` em cofre. Aplique somente uma branch revisada; inspecione o SHA antes de migrar:

```bash
cd /opt/gestao-mix7
git status --short --branch
git pull --ff-only
cd web-app
docker compose -f docker-compose.vps.yml build app scheduler
docker compose -f docker-compose.vps.yml run --rm --no-deps app php artisan migrate --force
docker compose -f docker-compose.vps.yml up -d app scheduler
```

Uma migration de banco não é desfeita ao voltar à imagem anterior. Confirme os backups e leia o diff/release antes de aplicá-la.

## Limites que continuam pendentes

- Revisar grants mínimos e implementar/testar isolamento RLS antes de dados reais; a Data API do Supabase não é necessária para esta arquitetura.
- Configurar e validar SMTP antes de recuperação de senha ou convites.
- Definir a política de dados, o provedor e o worker de IA. O Ollama do Windows em `127.0.0.1` não fica acessível de dentro do container da VPS; a produção fica sem IA até uma topologia segura ser aprovada.
- Fazer backup criptografado externo e um exercício de restauração isolado de PostgreSQL e anexos; preservar a `APP_KEY` separadamente.
- Validar a matriz de permissões e as jornadas com cada perfil antes do uso com clientes.
