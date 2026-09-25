# Referência visual da plataforma

## Fonte conferida

O CRM-MIX7-RENEW foi executado em ambiente local. Em 24/09/2026, Chromium renderizou a tela `/login` em 1270 × 713. A rota `/administrador/dashboard` redirecionou para o login, portanto o dashboard autenticado ainda não foi inspecionado renderizado; nenhuma credencial foi tentada. A decisão visual abaixo usa a tela que foi renderizada e os estilos do dashboard existentes em `public/css/dashboard-light.css`.

O CSS define corpo de 16 px, títulos de 32–46 px, navegação de 15 px, lateral de 252 px (230 px até 1399 px), recuos de conteúdo de 38 px (28 px até 1399 px), cartões de raio 24 px e botões com altura mínima de 44 px. Define também a lateral em degradê `#243943` → `#10252f`, item ativo `#e8f3f8` e as cores `#202e35` (texto), `#204b61` (azul-petróleo), `#8ecde2` (azul-claro), `#f5f6f5` (fundo) e branco (superfícies).

Após o pedido recente do usuário para aproximar mais a plataforma do CRM, sua navegação agora usa o degradê escuro, textos claros, item ativo azul-claro e as larguras confirmadas de 252/230 px. O conteúdo segue em superfícies brancas e fundo claro, com os azuis do CRM nas ações e destaques. Os cartões de resumo e de demandas foram ajustados para raios maiores. Nenhum arquivo ou asset do CRM foi copiado.

## Inspeção renderizada

Depois da alteração, a página de demandas foi aberta em Chromium na janela desktop de 1270 × 720. A lateral escura, os rótulos e ícones da navegação, o estado ativo, o fundo claro, cartões de resumo e o quadro de demandas estão visíveis. O contraste e o alinhamento geral da lateral foram conferidos nessa renderização.

A plataforma tem uma sessão local de demonstração aberta em outra aba com um formulário iniciado; ela foi preservada. As regras móveis definem lateral compacta de 72 px até 900 px e 56 px até 600 px. A revisão móvel desta primeira alteração foi concluída depois, conforme a seção “Validação móvel da plataforma” abaixo.

## Pendências

- Inspecionar o dashboard autenticado do CRM renderizado e comparar a composição, o cabeçalho, o menu e os cartões com a plataforma. A validação móvel da plataforma foi concluída em Chromium; o teste não substitui a referência autenticada.
- Reavaliar este documento quando houver evidência nova do dashboard renderizado. Nenhum arquivo do CRM foi alterado.

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
