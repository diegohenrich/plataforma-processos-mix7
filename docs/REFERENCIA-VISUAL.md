# Referência visual da plataforma

## Fonte conferida

O CRM-MIX7-RENEW foi executado em ambiente local. Em 24/09/2026, Chromium renderizou a tela `/login` em 1270 × 713. A rota `/administrador/dashboard` redirecionou para o login, portanto o dashboard autenticado ainda não foi inspecionado renderizado; nenhuma credencial foi tentada. A decisão visual abaixo usa a tela que foi renderizada e os estilos do dashboard existentes em `public/css/dashboard-light.css`.

O CSS define corpo de 16 px, títulos de 32–46 px, navegação de 15 px, lateral de 252 px (230 px até 1399 px), recuos de conteúdo de 38 px (28 px até 1399 px), cartões de raio 24 px e botões com altura mínima de 44 px. Define também a lateral em degradê `#243943` → `#10252f`, item ativo `#e8f3f8` e as cores `#202e35` (texto), `#204b61` (azul-petróleo), `#8ecde2` (azul-claro), `#f5f6f5` (fundo) e branco (superfícies).

Após o pedido recente do usuário para aproximar mais a plataforma do CRM, sua navegação agora usa o degradê escuro, textos claros, item ativo azul-claro e as larguras confirmadas de 252/230 px. O conteúdo segue em superfícies brancas e fundo claro, com os azuis do CRM nas ações e destaques. Os cartões de resumo e de demandas foram ajustados para raios maiores. Nenhum arquivo ou asset do CRM foi copiado.

## Inspeção renderizada

Depois da alteração, a página de demandas foi aberta em Chromium na janela desktop de 1270 × 720. A lateral escura, os rótulos e ícones da navegação, o estado ativo, o fundo claro, cartões de resumo e o quadro de demandas estão visíveis. O contraste e o alinhamento geral da lateral foram conferidos nessa renderização.

A plataforma tem uma sessão local de demonstração aberta em outra aba com um formulário iniciado; ela foi preservada. As regras móveis existentes definem lateral compacta de 72 px até 900 px e 56 px até 600 px. A revisão desta mudança em janela móvel continua pendente; a janela disponível não expôs controle de viewport móvel nesta sessão.

## Pendências

- Abrir o dashboard autenticado do CRM em sessão de demonstração para comparar sua composição, cabeçalho e cartões reais. O usuário precisa entrar manualmente na aba local `Entrar | CRM Mix7`, pois `/administrador/dashboard` redireciona para autenticação.
- Conferir a alteração em viewport móvel e ajustar caso a lateral compacta ou o quadro apresente problema.
- Reavaliar este documento quando houver evidência nova do dashboard renderizado. Nenhum arquivo do CRM foi alterado.
