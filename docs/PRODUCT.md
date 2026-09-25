# Produto

## Objetivo

Reduzir a dependência de conhecimento informal no dia a dia da Mix7, mantendo demandas, responsáveis, instruções, versões, feedbacks e decisões em um processo recuperável.

## Público inicial

- Equipe operacional: recebe e executa trabalho, consulta instruções e registra andamento.
- Gestores e direção: planejam, acompanham, revisam e decidem.
- Clientes: avaliam os materiais que lhes forem enviados para aprovação.

A primeira versão atende à Mix7 e seus clientes. Transformar a plataforma em produto para outras agências não faz parte da decisão atual.

## Experiência pretendida

Gestão interna focada, com Kanban e lista e consulta do trabalho atribuído a cada profissional; aprovações de imagem e vídeo conectadas às demandas; comentários preservados por versão e, no vídeo, associados a um instante. A visão por profissional organiza o trabalho, enquanto permissões de acesso seguem uma definição separada. O produto deve permitir ampliar os tipos de aprovação conforme as necessidades da agência forem validadas.

A referência visual vigente é a interface do projeto local `C:\Users\anony\ProjetosPessoais\Projetos de Sistemas\CRM-MIX7-RENEW`. Os arquivos `public/css/dashboard-light.css` e `resources/views/dashboard/overview.blade.php` foram inspecionados. A implementação deste protótipo usa as cores confirmadas no CRM (texto `#202e35`, azul-petróleo `#204b61`, azul-claro `#8ecde2`, fundo `#f5f6f5` e superfície branca) e mantém o pedido do usuário por predominância de branco e azul-claro. A paleta e o modo claro são diretrizes; a composição própria do protótipo continua validada nesta aplicação.

## Protótipo navegável

O [protótipo do fluxo integrado](../prototipo/README.md) materializa o fluxo-alvo aprovado: quadro, briefing, versões, feedback, revisão interna, decisão do cliente e registro de entrega antes da conclusão. Os nomes, números, clientes e demandas da tela são dados fictícios. As ações são demonstrações locais no navegador; não representam a aplicação pronta nem descrevem por si só o processo atual da Mix7.

Na demonstração, cada atalho visível deve responder à ação indicada. O painel de uma demanda pode ser minimizado sem apagar a demanda: ela permanece como atalho fixo reabrível no quadro e no perfil local do navegador. As telas de equipe, clientes e calendário são consultas derivadas desses mesmos dados; Conhecimento e Acessos explicam regras atuais e limites, sem simular cadastro de contas ou permissões ainda não especificadas.

## Limites atuais

Avaliação de desempenho, onboarding, central de conhecimento e gestão de acessos fazem parte da visão do produto, mas sua fase de implementação ainda não está definida. Os requisitos precisam ser validados antes de escolher stack, arquitetura ou solução reaproveitada.
