# Operação e cópias de segurança

## O que precisa ser recuperado

O código-fonte e seu histórico ficam no GitHub. Isso não inclui o banco de produção, anexos privados em `storage/app/private`, o arquivo `.env`, a chave `APP_KEY` nem arquivos enviados pelos usuários. Uma recuperação completa precisa reunir versões compatíveis desses itens. Segredos e cópias com dados de clientes não devem entrar no GitHub, Trello ou em capturas.

## Proteção do banco na VPS

O PostgreSQL usa o volume Docker `gestao_database`; os anexos usam `gestao_storage` na mesma VPS. Volumes locais não substituem backup. Mantenha cópia lógica criptografada **fora da VPS**, além de cópia independente dos anexos e do `.env`/`APP_KEY`.

Gere exportação lógica antes de migrações de risco. Guarde-a criptografada em local privado separado e registre data e commit. Arquivos privados Laravel precisam de cópia separada; o banco sozinho não recupera anexos. Preserve `.env` e `APP_KEY` em cofre de segredos separado. Nunca compartilhe esses valores no cartão de trabalho.

## Antes de publicar uma mudança

1. Registre o SHA do commit que será publicado e confirme que ele está no GitHub.
2. Quando a verificação operacional for retomada, rode `php artisan mix7:deploy:check` no contêiner. Ele confere ambiente, debug, chave, HTTPS, banco selecionado e acessível, conexão privada ou TLS, anexos, cache e cookie seguro; não imprime segredos. O usuário dispensou testes nesta etapa de preparação.
3. Crie uma exportação lógica pré-migração e uma cópia dos anexos da VPS; confirme que chegaram ao destino externo criptografado.
4. Armazene cópias criptografadas fora da VPS, junto da data e SHA da aplicação.
5. Se a mudança envolve anexos ou armazenamento, obtenha também uma cópia dos arquivos privados Laravel. Não os mova para `public_html`.
6. Execute migrations versionadas e registre o resultado junto do SHA. Não importe um dump em banco compartilhado sem confirmar o banco selecionado.

O comando é uma pré-verificação, não um deploy, não verifica o agendador da VPS nem prova a entrega de e-mail. Execute migrations, agende o Cron e valide essas integrações separadamente. Execute os comandos pelo terminal SSH da VPS e valide as integrações separadamente.

## Cópia local criptografada

A aplicação oferece `php artisan mix7:backup:create` para criar um ZIP criptografado com snapshot SQLite ou exportação lógica MySQL/MariaDB/PostgreSQL e arquivos do disco privado Laravel. PostgreSQL exige `pg_dump` no contêiner/host. O arquivo fica em `storage/app/backups`, fora da pasta pública, **e precisa ser copiado para fora da VPS**. O `.env` e a `APP_KEY` não entram no arquivo; preserve a chave separadamente. O exportador PostgreSQL usa a rede privada Docker sem TLS quando `DB_HOST=database`, e usa TLS para bancos remotos; a senha passa pelo ambiente do processo, sem entrar nos argumentos nem no log.

```powershell
php artisan mix7:backup:create
php artisan mix7:backup:restore "storage/app/backups/mix7-backup-AAAAmmdd-HHmmss-id.zip" --destination="storage/app/restore-local-2026-09-27"
```

A restauração recusa uma pasta já existente ou dentro de `public/` e valida manifesto/checksums sem substituir a conexão ativa. Dumps SQL devem ser importados manualmente em um banco de teste vazio, nunca diretamente em produção sem procedimento aprovado. A restauração de arquivos também permanece fora da pasta pública. O ciclo SQLite foi coberto localmente; exportação e recuperação PostgreSQL reais ainda não foram exercitadas.

## Exercício de restauração

Até existir ambiente de teste, use somente dados sintéticos. Para o exercício futuro:

1. Crie um segundo contêiner/volume PostgreSQL isolado da produção.
2. Importe a cópia SQL nele usando `psql`/`pg_restore` conforme o formato, sem conectar a app de produção.
3. Restaure os anexos em uma pasta privada separada e configure ambiente de teste próprio.
4. Confira migrations, login sintético, dados esperados e abertura de um anexo sintético.
5. Registre data, origem, commit, duração, verificações e falhas; elimine a cópia conforme retenção definida.

Um teste local com SQLite não comprova compatibilidade nem recuperação PostgreSQL. A etapa só será considerada validada quando banco, anexos e configuração forem recuperados em ambiente isolado, sem escrita na produção.

## Recuperação de incidente

Antes de restaurar produção, interrompa temporariamente novas gravações, preserve uma cópia do estado atual e identifique o instante a recuperar. A restauração pode substituir dados posteriores ao ponto escolhido. Confirme com a pessoa responsável pela operação qual backup e qual escopo devem voltar; depois valide login, demandas, arquivos, filas e logs antes de liberar o acesso. Registre impacto e diferenças entre o último backup e o incidente.


## Pendências para aceitar a operação

- Definir cópia recorrente para armazenamento fora da VPS e política de retenção.
- Definir frequência própria, cópia fora da hospedagem, retenção, responsáveis e RPO/RTO aceitáveis.
- Planejar armazenamento protegido para SQL, anexos e `APP_KEY`.
- Executar e documentar uma restauração de teste PostgreSQL + anexos em ambiente separado.
- Validar o procedimento real de backup antes de cada release que altere esquema ou dados.
