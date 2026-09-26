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
- Contas possuem organização, estado ativo e um dos quatro papéis citados: direção da agência, gerência de marketing, profissional ou cliente. A matriz completa ainda está em construção; não receber dados reais antes de validar as regras e o fluxo de recuperação de contas.
- Comando inicial `php artisan mix7:owner:create` solicita nome, e-mail e senha sem gravar credenciais no repositório. A senha requer pelo menos 12 caracteres. O comando cria a organização Mix7 se ainda não existir e recusa uma segunda conta de direção.
- API `GET /api/v1/me` e leitura `GET /api/v1/demands`, `GET /api/v1/demands/{demand}` protegidas por Sanctum. A API filtra demandas e tarefas por organização e restringe profissionais aos próprios trabalhos.
- Migrações Laravel registram organizações, papéis, tokens, demandas, tarefas e eventos de histórico. MariaDB é o destino; desenvolvimento local e testes usam SQLite.
- Demandas/tarefas têm telas, transições de estado com validação, autoria de ações e atribuição. A direção pode cadastrar contas profissionais vinculadas à organização, com senha armazenada por hash e sem cadastro público. Essa autorização é provisória até a matriz dos quatro papéis ser confirmada.
- A direção da agência pode criar e listar contas de cliente sem cadastro público; direção e gerência podem associar uma conta existente a demandas da mesma organização. Um cliente autenticado só lista e abre demandas vinculadas à própria conta e vê título, etapa e data de atualização; briefing, tarefas, nomes da equipe, histórico interno, conhecimento e ferramentas de IA ficam fora das telas e respostas de API do cliente. Criar/remover o vínculo gera evento com autoria. A seleção de cliente na criação e edição de demanda aceita somente conta Cliente ativa da mesma organização. Essa é uma fronteira técnica inicial, não substitui a matriz final de permissões. O cliente usa a conta para acompanhar a etapa; comentários e decisão de aprovação continuam no link externo sem conta.
- Direção pode suspender e restaurar o acesso de contas de profissional ou cliente da mesma organização, sem exclusão física. `is_active` bloqueia login e cada rota autenticada; na suspensão, tokens pessoais e sessões do driver de banco são revogados, sessões de outros drivers são recusadas na próxima requisição pelo middleware. Timer aberto é finalizado e a tarefa volta para Pausada. A tabela `team_member_events` conserva autor, conta e instante; a tela Equipe mostra as 50 mudanças mais recentes. Tarefas existentes continuam atribuídas e aparecem no painel de produção com indicador de conta inativa; não há reatribuição nesta entrega. Direção/gerência precisam validar o procedimento de transferência antes de adotar desligamento real.
- O profissional vê tarefas atribuídas a ele; o histórico também esconde eventos de tarefas de colegas. A revisão da demanda e briefing continuam visíveis para quem trabalha nela, necessários ao contexto da execução.
- O cronômetro web registra intervalos na tabela `task_time_entries`, ligados a tarefa, profissional e organização. Um bloqueio transacional na linha do usuário serializa o início e impede mais de uma sessão ativa. Pausar salva o intervalo; retomar cria outro; concluir/impedir/pausar a tarefa fecha a sessão ativa. O contador do cabeçalho é calculado pelos intervalos salvos e continua visível ao navegar.
- A rota autenticada `/equipe/producao` exibe fatos do trabalho sem pontuação: direção e gerência consultam agregados dos profissionais da própria organização (incluindo inativos para não ocultar tarefas preservadas); o profissional ativo consulta somente suas tarefas abertas e controla o próprio cronômetro. O resumo mostra estados atuais, estimativas abertas, conclusões nos últimos 30 dias e sessões de tempo iniciadas nesse intervalo. `demand_tasks.completed_at` registra a conclusão; a migração preenche tarefas já concluídas com seu `updated_at` disponível. Nenhuma informação de colegas aparece na visão individual; cliente é proibido. A fórmula de avaliação segue não definida.
- A revisão externa aceita rabiscos livres em coordenadas percentuais junto a anotações de área. O servidor aceita até 256 pontos validados no intervalo 0–100 e os armazena no JSON existente da resposta, sem nova tabela. Cliente e equipe veem a prévia SVG vinculada ao comentário e à versão; o traçado não tenta identificar elementos da página nem persiste através de rolagem.
- PDFs anexados são desenhados por PDF.js incluído no build Vite local. A tela de revisão e a equipe podem avançar/voltar páginas e alterar zoom sem depender de um leitor instalado no navegador ou de uma CDN. As ações de abrir em outra guia e baixar permanecem visíveis como alternativas. `package-lock.json` fixa dependências para builds reproduzíveis; compilar exige Node 22.13+ da série 22 ou 24+.
- A revisão do cliente por link fica em `demand_review_links` e `demand_review_responses`. O token tem 64 caracteres aleatórios e somente seu SHA-256 é persistido; cada versão recebe uma URL HTTP/HTTPS ou um arquivo PDF, imagem ou vídeo de até 20 MB. Arquivos são guardados no disco privado, fora da raiz pública; a rota de leitura verifica o token e seu escopo, validade, revogação e estado da revisão antes de transmitir um MIME permitido com `nosniff`, enquadramento limitado à mesma origem e cache privado. A equipe abre o arquivo autenticada e autorizada na mesma organização. A validade é escolhida no envio no fuso local do navegador e a gerência pode revogar. Criar nova versão revoga links abertos anteriores. Rotas públicas têm limite de requisições; uma decisão final impede novas respostas e altera a etapa da demanda. Nome é autodeclarado. Cada resposta pode guardar âncora JSON com trecho, coordenadas percentuais, traçado livre de até 256 pontos, instante de vídeo ou página e URL/nome da versão. Retângulo e rabisco podem ser feitos na prévia iframe isolada; se o site impedir incorporação, a área pode ser descrita manualmente. Coordenadas representam o quadro visível. Seleção automática de texto e player integrado ainda não existem. A tela pública mostra somente o material escolhido, título e versão; não mostra briefing nem tarefas. Esta é uma política técnica inicial, ainda pendente de validação da Mix7 antes de material real.
- Planejamento assistido e assistente interno usam a API de chat OpenAI-compatible por serviços Laravel. Vercel AI Gateway é o padrão compatível; `AI_PROVIDER`, `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` permitem usar outro servidor compatível. Um servidor local como Ollama pode evitar tarifa por token se estiver acessível ao processo Laravel. A opção sem autenticação é bloqueada por padrão e só funciona quando o provedor está explicitamente definido como `openai-compatible` e `AI_ALLOW_UNAUTHENTICATED=true`; deve ficar restrita a rede local protegida. Essa compatibilidade foi testada com respostas simuladas, não com modelo instalado. Um modelo rodando em outro computador não fica acessível automaticamente à Hostinger: requer integração de rede/worker dedicada, ainda não construída. O modelo local usa energia, armazenamento e capacidade do equipamento.
- Na Vercel, configurar `VERCEL_OIDC_TOKEN` efêmero elimina a chave manual, não o custo de inferência. A documentação oficial consultada em 26/09/2026 anuncia US$ 5/mês no tier gratuito do Gateway, tarifado por modelo/tokens, sem garantir uso contínuo gratuito. [BYOK](https://vercel.com/docs/ai-gateway/authentication-and-byok/byok) requer crédito no Gateway e pode recorrer ao saldo se a chave externa falhar. Os termos do [plano Hobby](https://vercel.com/legal/terms) restringem seu uso a fins pessoais/não comerciais, portanto não é hospedagem aprovada para a agência.
- Somente direção/gerência podem enviar título, briefing, tarefas ou perguntas/contexto ao provedor configurado. O agente de planejamento não recebe ferramentas nem permissão de escrita; a saída JSON Schema é validada no Laravel e exige edição, revisão e aplicação humana. Há limite de 3 pedidos por minuto e 45 segundos por chamada; resposta até 3.500 tokens. O assistente por demanda é job Laravel com ferramentas somente de leitura para consultar demanda, tarefas visíveis, referências ativas e, para gerência, feedback do cliente; máximo de 2 rodadas, 4 consultas por rodada e 1.800 tokens por chamada. A pergunta é criptografada no payload; a auditoria guarda hash/tamanho, resposta, tokens, custo se informado e recibos das ferramentas, sem copiar o texto bruto da pergunta.
- Provedor/modelo, política de envio e retenção ainda precisam de aprovação da Mix7 antes de conteúdo real. O comando `ollama` não foi encontrado neste ambiente. Para o assistente enfileirado, é necessário worker persistente; o suporte do plano Hostinger não foi verificado e, sem worker, os jobs permanecem pendentes.

## Desenvolvimento local

Na raiz do repositório:

```powershell
cd web-app
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan mix7:owner:create
npm ci
npm run build
php artisan serve
```

O build gera os ativos em `web-app/public/build/`; envie essa pasta junto com a aplicação se o plano não oferecer Node.js para compilar no servidor.

Abra `http://127.0.0.1:8000`. Em `.env`, configurar `DB_CONNECTION=mysql`, host, nome, usuário e senha do banco local ou Hostinger. Nunca copiar credenciais reais para `.env.example`, GitHub, Trello ou capturas.

## Preparação da hospedagem

No hPanel, confirmar plano vigente, versão PHP 8.2+, PDO MySQL, espaço disponível, limites MariaDB, SSH/Composer, domínio e certificado HTTPS. Criar banco e usuário de banco separados no hPanel; phpMyAdmin administra o banco, mas não deve ser publicado aberto no site da aplicação. Guardar valores secretos somente no `.env` do servidor.

O diretório público do domínio deve apontar para `web-app/public/`; código, `.env`, `vendor/`, logs e arquivos privados ficam fora do diretório público. Definir `APP_ENV=production`, `APP_DEBUG=false`, chave própria de produção, HTTPS e permissões de escrita restritas em `storage/` e `bootstrap/cache/`. Executar migrações versionadas após cópia de segurança. Se o plano não permitir SSH, validar upload completo de dependências e importação de esquema pelo phpMyAdmin antes de escolher o procedimento alternativo.

Nenhum deploy foi feito e não foram acessados painel, domínio, banco ou credenciais da Hostinger. A revisão externa foi exercitada somente com banco de teste/local e dados sintéticos. O limite de 20 MB foi validado na aplicação; limites PHP `upload_max_filesize`/`post_max_size`, espaço do plano, backup/restauração e comportamento MariaDB na Hostinger ainda precisam ser conferidos antes de definir anexos reais.

## Contrato futuro do executável Windows

O desktop será um cliente da API HTTPS do mesmo sistema. Endpoints serão versionados (`/api/v1`); autenticação de cliente desktop usará tokens pessoais com escopo mínimo, expiração e revogação. O desktop não terá acesso direto ao MariaDB, não guardará senha de banco e não duplicará regras de negócio. Hoje há leitura autenticada de identidade e demandas; escrita de demandas/tarefas e comandos de cronômetro pela API, aprovações, anexos e outros módulos ainda são trabalho futuro.

## Não pronto para uso com dados reais

Existe isolamento inicial e testado para contas de cliente: cada pessoa lista e abre somente demandas vinculadas à sua conta, com campos reduzidos também na API. Ainda faltam a matriz completa dos quatro papéis, convites e recuperação de contas, validação das regras pela Mix7, backup/restauração, trilha de auditoria completa e validação do plano de hospedagem; portanto, o MVP ainda não deve receber material real ou links de cliente. O cronômetro não reconcilia encerramento abrupto nem registra tempo offline. Também faltam aprovação das regras do link, seleção automática de texto, player integrado, gestão operacional de tokens e confirmação do plano Hostinger. O rabisco livre foi implementado localmente, mas ainda requer validação visual em navegadores móveis. Anexos privados estão implementados localmente, mas a configuração de limites e armazenamento do plano de hospedagem ainda não foi verificada. A fundação e as primeiras telas de trabalho habilitam desenvolvimento; não representam a plataforma completa.
