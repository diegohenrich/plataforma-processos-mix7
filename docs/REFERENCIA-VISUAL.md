# Referência visual da plataforma

## Fonte conferida

O CRM-MIX7-RENEW foi executado em ambiente local. Chromium renderizou a tela `/login`; a rota `/administrador/dashboard` redireciona para o login sem sessão, portanto ainda falta inspecionar o dashboard autenticado renderizado. Nenhuma credencial foi tentada. O layout base abaixo vem de `public/css/dashboard-light.css`, que é carregado pelo template do dashboard. A tela de entrada serve para confirmar apenas a identidade visual do login, não sua composição interna.

O CSS define corpo de 16 px, títulos de 32–46 px, navegação de 15 px, lateral de 252 px (230 px até 1399 px), recuos de conteúdo de 38 px (28 px até 1399 px), cartões de raio 24 px e botões com altura mínima de 44 px. Define também a lateral em degradê `#243943` → `#10252f`, item ativo `#e8f3f8` e as cores `#202e35` (texto), `#204b61` (azul-petróleo), `#8ecde2` (azul-claro), `#f5f6f5` (fundo) e branco (superfícies).

Após o pedido recente do usuário para aproximar mais a plataforma do CRM, sua navegação usa o degradê escuro, textos claros, item ativo azul-claro e as larguras confirmadas de 252/230 px. O conteúdo segue em superfícies brancas e fundo claro, com os azuis do CRM nas ações e destaques. Os cartões de resumo e de demandas foram ajustados para raios maiores. Nenhum arquivo ou asset do CRM foi copiado.

## Inspeção renderizada

Depois da alteração, a página de demandas foi aberta em Chromium na janela desktop de 1270 × 720. A lateral escura, os rótulos e ícones da navegação, o estado ativo, o fundo claro, cartões de resumo e o quadro de demandas estão visíveis. O contraste e o alinhamento geral da lateral foram conferidos nessa renderização.

A plataforma tem uma sessão local de demonstração aberta em outra aba com um formulário iniciado; ela foi preservada. As regras móveis definem lateral compacta de 72 px até 900 px e 56 px até 600 px. A revisão móvel desta primeira alteração foi concluída depois, conforme a seção “Validação móvel da plataforma” abaixo.

## Pendências

- Comparar a composição autenticada do CRM com a plataforma quando o usuário fornecer os prints. O usuário pediu que essa validação não interrompa as demais frentes; continuar requisitos, pesquisa e testes enquanto isso.
- Reavaliar este documento quando os prints chegarem. Nenhum arquivo do CRM foi alterado.

## Reinspeção solicitada — 25/09/2026

- O servidor do CRM-MIX7-RENEW foi iniciado novamente em `http://127.0.0.1:8198`. A tela de login foi renderizada em Chromium. A rota `/administrador/dashboard` voltou a redirecionar para `/login`; o painel autenticado não foi visto e nenhuma credencial foi inserida.
- Foi comparada a tela atual de demandas da plataforma com os estilos fonte do CRM. Há uma diferença de escala ainda visível no CSS da plataforma: em larguras até 1399 px, o menu usa 14 px, os títulos de coluna 13 px, os títulos de cartão 14 px e suas descrições 12 px; o tema do CRM declara corpo 16 px, navegação 15 px e títulos de dashboard entre 32 e 46 px. O CRM também define cartões de 24 px de raio e área útil com recuo de 38 px, reduzido a 28 px nessa faixa.
- Essa comparação confirma uma diferença nos valores tipográficos, mas não determina por si só como reproduzir a composição do dashboard na plataforma. Não houve alteração visual nesta reinspeção; o painel renderizado é necessário para validar cabeçalho, hierarquia, proporções e distribuição dos componentes.
- Próximo passo: o usuário autentica manualmente no CRM local e deixa o dashboard aberto. Depois, comparar a composição renderizada em tamanhos equivalentes, ajustar o protótipo e verificar desktop e celular. Nenhum arquivo do CRM foi modificado.

## Escala de texto do quadro e do briefing — 25/09/2026

- O CRM foi executado novamente e a tela de login, a única rota renderizada sem autenticação, foi inspecionada lado a lado com o quadro e o formulário de demanda. O CSS do CRM confirma corpo de 16 px, títulos de painel de 32–46 px, navegação de 15 px e cartões arredondados. O dashboard autenticado continua inacessível até o usuário entrar manualmente.
- Ajustados textos de navegação contextual, cartões de demanda, painel de detalhes, comentários, formulários, campos de tarefa e áreas de equipe/conhecimento para a escala de 14–16 px em conteúdo e títulos de 28–32 px nos diálogos/painel. Cartões de demanda e formulário receberam raio de 24 px. Regras móveis explícitas mantêm uma escala própria para telas estreitas. Cores e superfícies existentes foram preservadas; nenhum arquivo ou asset do CRM foi copiado.
- Chromium renderizou o quadro e o formulário após a alteração em viewport desktop de 1270 × 720. A captura confirmou títulos, campos e controles sem corte dentro do diálogo rolável. A validação em viewport móvel e a comparação da composição com o dashboard autenticado permanecem pendentes.

## Nova execução do CRM para comparação — 25/09/2026

- O servidor Laravel do CRM foi iniciado novamente em `http://127.0.0.1:8198` com `php artisan serve`; Chromium renderizou a tela de login em 1270 × 713. A tela tem composição dividida: apresentação escura à esquerda e formulário claro à direita. Essa observação descreve somente a entrada do CRM e não deve ser aplicada como composição do painel de processos.
- `php artisan route:list` confirmou que `administrador/dashboard` usa `auth`, `status` e `is.admin`; sem sessão autenticada, a navegação vai para `/login`. O código do dashboard aponta para `resources/views/dashboard/index.blade.php` e `overview.blade.php`, mas essa inspeção de arquivos não substitui a comparação visual renderizada do painel.
- A aba local do CRM foi deixada aberta para autenticação manual pelo usuário. Nenhuma senha foi lida ou inserida; nenhum arquivo do CRM foi alterado. A comparação de cabeçalho, sidebar, hierarquia, cartões e espaçamentos do dashboard segue pendente até a sessão de demonstração ficar aberta.

## Validação móvel da plataforma — 25/09/2026

- Em Chromium/Playwright, origem isolada `127.0.0.1:4200`, viewport de 390 × 844 e dados fictícios: quadro e documento ficaram com 390 px de largura, sem rolagem horizontal da página. Drawer de demanda e rodapé de aprovação ocuparam x=0–390 após a animação de entrada. “Solicitar ajustes” e “Aprovar versão” ficaram habilitados e dentro da tela. O conteúdo do drawer tem rolagem interna; o formulário de briefing mede x=19–371 e rola dentro da janela.
- As capturas de quadro, briefing, demanda aberta e aprovação foram inspecionadas visualmente; nenhum erro JavaScript ocorreu. A primeira captura do drawer foi durante a animação e não foi usada como evidência; a captura posterior, com a transição terminada, confirma a posição correta. Nenhum dado da origem `4173` foi acessado ou alterado.
- Esta verificação cobre apresentação móvel da plataforma, não sua semelhança com a composição autenticada do CRM. O painel CRM ainda precisa ser aberto manualmente para concluir a comparação visual.

## Reexecução visual do CRM — 25/09/2026

- Reaberta a rota `/administrador/dashboard` em Chromium no CRM local. O servidor respondeu e redirecionou para `/login`; a tela visível apresenta painel escuro de apresentação à esquerda e formulário claro à direita. Essa observação é específica do login e não define a composição do dashboard.
- A aba local “Entrar | CRM Mix7” foi deixada aberta para o usuário autenticar. Nenhuma credencial foi inserida nem lida. A comparação renderizada do dashboard segue pendente; enquanto isso, não aplicar à plataforma elementos de composição inferidos da tela de login.
- A inspeção móvel da plataforma permanece concluída em 390 × 844, incluindo quadro, briefing, drawer e ações de aprovação, com capturas inspecionadas e sem overflow horizontal. Esta validação não depende da sessão autenticada do CRM.

## Cabeçalho e resumo comparados com o CRM — 25/09/2026

- O servidor local do CRM respondeu em `127.0.0.1:8198`. Chromium renderizou a tela de entrada em 1440 × 900 e 390 × 844. No desktop, o painel de marca ocupa 778 px dos 1440 px e o formulário tem 470 px; no celular, a marca ocupa 165 px de altura e a página de login rola até 872 px. Essas medidas descrevem o login, não foram aplicadas à plataforma.
- Conferi que `resources/views/dashboard/index.blade.php` escolhe a apresentação redesenhada e `resources/views/layouts/template.blade.php` carrega `dashboard-light.css`. A view inclui breadcrumb e perfil na barra superior; a lateral começa com o perfil e depois o menu. O CSS do próprio dashboard define barra transparente com margens 24 px em cima e 40 px abaixo, área útil com recuos de 38 px, primeiro indicador com degradê escuro `#294b5c` → `#112833`, superfícies brancas e cantos de 24 px. O dashboard autenticado ainda não pôde ser renderizado.
- A plataforma foi ajustada em `prototipo/styles.css`: removi a faixa branca separada do cabeçalho desktop e apliquei a posição/tamanho confirmados da barra do CRM; o primeiro resumo agora usa o tratamento escuro do cartão de destaque do CRM. O quadro e a navegação próprios da plataforma foram preservados; não repliquei o painel dividido do login.
- Capturas renderizadas do protótipo e inspecionadas: [desktop, 1440 × 900](evidencias/visual/plataforma-crm-alinhado-1440.png) e [celular, 390 × 844](evidencias/visual/plataforma-crm-alinhado-390.png). Chromium/Playwright confirmou, no desktop, menu de 252 px, cabeçalho x=290/y=24 com 1112 × 44 px e fundo transparente, primeiro indicador com o degradê e título a y=108. No celular, o documento permaneceu com 390 px e a barra compacta original foi mantida.
- `node --test tests/workflow.test.js` passou 22/22; `node --check prototipo/workflow.js`, `node --check prototipo/app.js` e `git diff --check` passaram. Limite restante: para comparar perfil, cabeçalho e cartões contra o painel renderizado real, a sessão do CRM precisa estar autenticada manualmente.

## Retomada da inspeção pedida pelo usuário — 25/09/2026

- O servidor do projeto CRM-MIX7-RENEW está ativo em `http://127.0.0.1:8198`. Abri a rota `/administrador/dashboard` em Chromium; ela redirecionou para `/login`, pois esta sessão não está autenticada. A aba do CRM foi mantida aberta para o usuário entrar manualmente. Nenhuma credencial foi lida ou preenchida e nenhum arquivo do CRM foi alterado.
- Renderizei o protótipo em Chromium (1270 × 920). A tela de Demandas mostra uma lateral escura de 252 px, o perfil no rodapé, três indicadores e um Kanban de oito etapas com rolagem horizontal. As views locais do CRM mostram perfil no topo da lateral, breadcrumb e perfil na barra superior e um dashboard modular com indicadores, análise e gráficos. O CSS confirma superfícies predominantemente claras, cantos de 24 px e tipografia de títulos entre 32 e 46 px. As views descrevem a composição implementada no código, mas não provam quais módulos aparecem após o carregamento dos dados reais.
- Evidência: há diferenças concretas entre a composição renderizada do protótipo e a estrutura declarada no dashboard do CRM, porém ainda falta enxergar o CRM autenticado. Por isso, nenhum novo ajuste visual foi feito nesta retomada: o próximo ajuste deve ser guiado pela comparação renderizada em sessão autenticada, sem copiar componentes sem relação com os processos da Mix7.

## Inspeção inicial da cópia local autorizada — 26/09/2026

- O usuário confirmou o caminho `C:\Users\anony\ProjetosPessoais\Projetos de Sistemas\CRM-MIX7-RENEW` e limitou esta fase à extração visual, sem migração nem alteração da persistência e sem tocar no serviço publicado. TryCRM permanece fora da referência visual.
- Em navegador local, `/login` carregou na cópia. A 1270 px, a tela tem composição em duas colunas: painel de marca Mix7 escuro em degradê à esquerda, com wordmark, chamada e texto institucional; área de acesso em superfície clara à direita, com título, campos e botão. Em largura estreita, o CSS recolhe o painel de marca para uma faixa superior compacta e empilha o formulário. Essa tela só confirma o desenho de acesso, não a composição do dashboard.
- `dashboard-light.css` confirma paleta e medidas: texto `#202e35`, azul-petróleo `#204b61`, azul-claro `#8ecde2`, fundo `#f5f6f5`, superfície branca, lateral `#243943` → `#10252f`, menu com raio de cartão 24 px e tipografia de 16 px/15 px. A navegação própria do CRM coloca perfil acima do menu e usa breadcrumb/perfil no topo do conteúdo. Esses tokens e estruturas são evidência de código; a tela autenticada ainda precisa de comparação renderizada.
- A cópia FTP contém Laravel e dependências, mas o `.env` configura MySQL local e a porta configurada não está em escuta; nenhum serviço MySQL/MariaDB local foi encontrado ativo. Para renderizar somente a página pública, o processo foi executado com configuração temporária apontando para SQLite em memória e sessão em memória. O banco MySQL da cópia não foi iniciado, acessado, apagado ou alterado; nenhuma senha foi redefinida. O usuário autorizou criar acesso fictício, mas ainda falta o servidor/banco local para autenticar. O login fica pendente até esse banco estar disponível e a entrada temporária ser feita pela pessoa usuária.
- O PHP local mostra aviso depreciação de `PDO::MYSQL_ATTR_SSL_KEY` durante a execução. É aviso do ambiente/código de configuração local, não parte do design da tela.
- Resultado daquela etapa: identidade visual do acesso e tokens do dashboard foram conferidos; a sessão autenticada ainda estava pendente. A renderização autenticada foi concluída depois, conforme a seção seguinte. O serviço publicado não foi acessado.

## Dashboard autenticado renderizado — 26/09/2026

- A sessão foi aberta na cópia local usando o MariaDB isolado e uma conta fictícia criada somente no clone. A tela autenticada foi renderizada no viewport disponível de 664 × 880. O cabeçalho compacto mostra menu à esquerda, título e avatar; o conteúdo usa fundo cinza-claro, cartões brancos arredondados, filtros de empresa e período, escolha Essencial/Avançado e módulos empilhados. A visão Avançado organiza resumo e leitura executiva, canais de contato, tendências, meta, indicadores de anúncios, histórico e tabelas recentes.
- Na captura autenticada, os módulos de métricas e contatos aparecem carregados; os dados permanecem no navegador local e não foram reproduzidos neste documento. A captura não foi guardada no repositório porque contém registros pessoais do banco importado. O tamanho de desktop amplo continua sem comprovação renderizada nesta sessão; os valores do CSS não substituem essa validação.
- Durante uma primeira tentativa, a configuração temporária deixou o leitor local do Google Ads desligado enquanto foi selecionada uma empresa com conta vinculada; o fluxo do dashboard acionou consultas pelo provedor direto. O servidor foi parado assim que isso foi percebido, mas não há evidência suficiente para confirmar se houve tráfego externo. Na inspeção retomada, o leitor de dados armazenados ficou habilitado, sem chamadas diretas ao provedor pelo dashboard. Para novas verificações, manter esse modo local e escolher apenas dados que não acionem integrações desnecessárias.
- O banco publicado e os arquivos do CRM de referência não foram alterados. O clone conserva o usuário fictício e os dados locais do SQL importado. Essa validação conclui a observação autenticada do painel e deixa pendente comparar desktop amplo, registrar a evidência sem dados pessoais e aplicar somente os ajustes visuais comprovados à plataforma.
