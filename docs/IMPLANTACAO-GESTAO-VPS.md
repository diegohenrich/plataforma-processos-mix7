# Gestão Mix7 na VPS

A aplicação Laravel e o PostgreSQL 17 rodam no projeto Docker `gestao-mix7` da VPS Hostinger. O banco fica no volume `gestao_database`, sem porta publicada; os anexos ficam em `gestao_storage`. O Traefik existente deve encaminhar `gestao.mix7.org` ao serviço `app` na porta interna 8080. Não use `docker compose down -v` nem `docker system prune`: o primeiro apaga volumes, e o segundo pode afetar outros projetos.

## Estado anterior e mudança de banco

Em 02/10/2026, a VPS foi atualizada até a correção `13eecd0`, o PostgreSQL privado foi iniciado e todas as migrations terminaram com `DONE`. O usuário informou que criou a conta de direção, renovou a `APP_KEY` e iniciou `app`/`scheduler`. A rota pública HTTPS apresentou a tela `Entrar · Plataforma Mix7` com HTTP 200; login e fluxos internos ainda não foram confirmados. A conexão direta anterior ao Supabase falhou por falta de rota IPv6 no contêiner; o Session pooler retornou `ENOIDENTIFIER`. O projeto Supabase permanece independente e não recebeu migração de dados. Não remova projetos Supabase nem volumes como parte da instalação.

A pasta `/opt/gestao-mix7/web-app` já existe na VPS. O `git status --short` mostrou arquivos não rastreados chamados `=`, `CACHED`, `[app]`, `[scheduler]`, `exporting`, `naming` e `unpacking` nessa pasta. Não os remova automaticamente; preserve-os durante a atualização da branch.

## Preparar configuração privada

O arquivo `web-app/.env` já existe na VPS e deve continuar com permissão `600`. Mantenha `APP_KEY` e `DB_PASSWORD` privados. A senha com `$` deve continuar entre aspas simples no `.env`, pois o Compose também a lê. O Compose agora define para os contêineres `DB_HOST=database`, `DB_PORT=5432`, `DB_DATABASE=gestao_mix7`, `DB_USERNAME=mix7_app` e `DB_SSLMODE=disable`; a comunicação ocorre somente na rede Docker privada. O valor antigo de `DB_HOST` no `.env` não será usado pelo app, mas atualize-o para `database` quando editar o arquivo para não confundir operações futuras. A senha atual do `.env` inicia o banco local; se desejar outra, altere-a **antes da primeira inicialização**. Depois disso, trocar somente o `.env` não altera a senha da conta PostgreSQL já criada.

O contêiner existente `traefik-traefik-1` está em modo de rede `host`, conforme inspeção na VPS em 02/10/2026. Ele alcança o IP do contêiner `app` na bridge Docker pela porta 8080 usando o provedor Docker e as labels da aplicação. Não configure `TRAEFIK_NETWORK`, não publique a porta do banco e não altere o Traefik existente nesta etapa.

Uma `APP_KEY` anterior apareceu em texto enviado ao chat. O usuário a renovou no `.env` antes de iniciar o serviço público, sem exibir o novo valor. Guarde essa chave separadamente para recuperação; não a envie ao GitHub, Trello ou chat.

## Atualizar e iniciar

O usuário pediu que esta etapa avance sem testes diagnósticos. Estes comandos são as operações necessárias para instalar o banco novo, aplicar o esquema e iniciar a aplicação; não executam a suíte de testes nem o antigo `migrate:status`.

```bash
cd /opt/gestao-mix7
git pull --ff-only origin codex/fundacao-compartilhada
cd web-app
docker compose -f docker-compose.vps.yml pull database
docker compose -f docker-compose.vps.yml build app scheduler
docker compose -f docker-compose.vps.yml up -d database
docker compose -f docker-compose.vps.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.vps.yml run --rm app php artisan mix7:owner:create
docker compose -f docker-compose.vps.yml up -d app scheduler
```

O primeiro `git pull` só deve prosseguir se não houver conflito com os arquivos existentes; não force nem limpe a árvore. `mix7:owner:create` pede nome, e-mail e senha interativamente. Não execute `db:seed` nem `migrate:fresh` na VPS. Se uma operação falhar, interrompa a sequência naquele ponto e preserve o volume `gestao_database` para diagnóstico posterior.

O `app` não publica porta no host. Para o HTTPS funcionar, o Traefik em modo `host` precisa descobrir o serviço via provedor Docker e alcançar o IP da bridge do `app` na porta 8080. DNS e certificado também precisam estar corretos. Não declare a publicação concluída até abrir a página real no domínio.

Em 02/10/2026, a página pública de entrada foi aberta por HTTPS e retornou HTTP 200. Isso não confirma login, funcionalidades internas, e-mail nem IA. A IA Gemma 3:4b roda apenas no computador local via Ollama; a VPS não tem esse serviço configurado.

## Atualizações posteriores

Antes de atualizar, salve fora da VPS uma cópia criptografada do banco e dos anexos e guarde a `APP_KEY` separadamente. Uma migration aplicada não é revertida ao voltar para uma imagem antiga. Atualize somente a branch do projeto, reconstrua `app` e `scheduler`, aplique migrations versionadas e recrie apenas esses serviços. Nunca use `down -v`.

## Limites antes de dados reais

- Configurar e verificar SMTP; enquanto `MAIL_MAILER=log`, convites e recuperação de senha não chegam por e-mail.
- Definir como a IA Gemma/Ollama funcionará na VPS; o Ollama instalado no Windows não fica acessível pelo contêiner remoto.
- Preparar backup externo recorrente do volume PostgreSQL e dos anexos e uma restauração isolada. O volume Docker sozinho não é backup.
- Revisar permissões da aplicação e os privilégios da conta do banco antes de uso com clientes.
