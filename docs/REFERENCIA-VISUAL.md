# Referência visual da plataforma

## Fonte conferida

Em 24/09/2026, o projeto `CRM-MIX7-RENEW` foi executado em servidor local e sua tela `/login` foi renderizada em navegador Chromium a 1270 × 713. A tela mostra a identidade Mix7 com azul-petróleo escuro, realces azul-claro e uma superfície clara; a hierarquia usa título grande, texto de apoio legível e campos/botão de bom tamanho.

O login exige autenticação. Não havia sessão de demonstração documentada; não foram tentadas credenciais. Por isso, o dashboard não foi apresentado como inspecionado ao vivo. Para entender seu tratamento interno, foram conferidos os estilos existentes em `public/css/dashboard-light.css`: corpo de 16 px, navegação de 15 px, títulos de 32–46 px, cartões com raio de 24 px, botões com pelo menos 44 px e primário azul-petróleo. O tema do dashboard usa navegação escura; esse contraste não foi transferido para a plataforma, pois o usuário pediu predominância clara em branco e azul-claro.

As cores confirmadas no CRM continuam sendo `#202e35` (texto), `#204b61` (azul-petróleo), `#8ecde2` (azul-claro), `#f5f6f5` (fundo) e branco (superfícies). Na plataforma, azul-petróleo marca ações primárias e azul-claro marca estados ativos, foco e realces. A navegação permanece branca, com seleção em azul-claro. Tipografia, espaçamento, tamanho dos controles e acabamento dos cartões foram ajustados a partir das medidas existentes, sem reutilizar imagens, logotipo gráfico, conteúdo ou composição dividida da tela de login.

## Revisão da plataforma

Após a alteração, a página de demandas e o formulário “Nova demanda” foram renderizados e inspecionados em 1270 × 713. A navegação, o painel de resumo, o quadro e os cartões foram conferidos; o modal permanece rolável e mantém o conteúdo visível dentro da janela.

Em 390 × 844, a navegação compacta, o quadro, os controles e o formulário também foram inspecionados. O seletor de demanda manteve largura de 337 px entre x=19 e x=356; o documento mediu 375 px em uma viewport de 390 px, sem rolagem horizontal da página. O modal teve 776 px de área visível e rolagem interna para o restante do formulário.

## Limite desta comparação

Para revisar o dashboard do CRM como ele aparece após o login, é necessária uma sessão de demonstração já autorizada pela Mix7. A referência atual usa a tela pública de entrada renderizada e as regras visuais do dashboard verificadas no projeto local. Nenhum arquivo do CRM foi alterado.
