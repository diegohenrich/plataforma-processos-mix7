# Produto

## Escopo da primeira entrega operacional

A primeira versão em uso inclui todas as áreas da plataforma descritas nos três áudios. As etapas de construção e protótipos menores servem para ordenar e validar o trabalho, sem deixar áreas do projeto para uma versão posterior. Veja o [roteiro e os critérios](ROADMAP.md).

## Objetivo

Concluir uma plataforma de processos para a Mix7 que reduza a dependência de cobranças informais e conhecimento individual, mantendo demandas, tarefas, responsáveis, instruções, versões, feedbacks e decisões em um sistema compartilhado e recuperável. O escopo inclui gestão da equipe, módulos de aprovação expansíveis, tempo e capacidade, IA sujeita à revisão humana, avaliação, conhecimento, onboarding e acessos seguros. A jornada local atual é uma demonstração para validar interações, não o produto completo.

## Público inicial

- Equipe operacional: recebe e executa trabalho, consulta instruções e registra andamento.
- Gestores e direção: planejam, acompanham, revisam e decidem.
- Clientes: avaliam os materiais que lhes forem enviados para aprovação.

A plataforma concluída atenderá à equipe e aos clientes da Mix7. O núcleo de gestão também sustentará módulos de aprovação para áreas diferentes; criativos sociais são o primeiro módulo conhecido, e os tipos adicionais serão definidos com exemplos da operação. O trabalho será entregue por etapas, mas a demonstração local ou a primeira jornada integrada não limita o escopo final. Transformar a plataforma em produto para outras agências não faz parte da decisão atual.

## Experiência pretendida

Gestão interna focada, com Kanban e lista, consulta do trabalho de cada profissional, planejamento de tarefas, registro de tempo, disponibilidade e Gantt; aprovações de imagem e vídeo conectadas às demandas; comentários preservados por versão e, no vídeo, associados a um instante; conhecimento, contatos, treinamento e onboarding; gestão segura de acessos; e avaliação com regras transparentes. A IA sugere tarefas, responsáveis, estimativas e organização de feedback; uma pessoa revisa antes de aplicar. Permissões reais precisam ser definidas e aplicadas separadamente da visão individual. O produto deve permitir ampliar os tipos de aprovação conforme as necessidades da agência forem validadas.

O quadro deve mostrar cartões curtos, com resumo do briefing e acesso ao contexto completo no detalhe; o histórico continua consultável sem ocupar a tela por padrão. Materiais anexados devem poder ser visualizados com clareza, inclusive PDFs. A autoria de cada ação e a atribuição de tarefas devem usar identidades reais após a fundação de contas e permissões. O profissional precisa acessar rapidamente a própria lista e o cronômetro ativo.

O cliente deve revisar criativos e sites por um link sem precisar criar conta, aprovar ou pedir ajustes e comentar com contexto visual, inclusive marcando texto ou área de uma página. O link não substitui segurança: o produto precisa limitar a demanda/versão exposta e permitir que a equipe revogue o acesso, segundo política ainda a definir. Dono da agência e gerente precisam acompanhar produção e evolução por profissional; escala e fórmula de avaliação permanecem pendentes.

A referência visual vigente é a interface do projeto local `C:\Users\anony\ProjetosPessoais\Projetos de Sistemas\CRM-MIX7-RENEW`. Os arquivos `public/css/dashboard-light.css` e `resources/views/dashboard/overview.blade.php` foram inspecionados. A implementação deste protótipo usa as cores confirmadas no CRM (texto `#202e35`, azul-petróleo `#204b61`, azul-claro `#8ecde2`, fundo `#f5f6f5` e superfície branca) e mantém o pedido do usuário por predominância de branco e azul-claro. A paleta e o modo claro são diretrizes; a composição própria do protótipo continua validada nesta aplicação.

## Protótipo navegável

O [protótipo do fluxo integrado](../prototipo/README.md) materializa o fluxo-alvo aprovado: quadro, briefing, versões, feedback, revisão interna, decisão do cliente e registro de entrega antes da conclusão. Os nomes, números, clientes e demandas da tela são dados fictícios. As ações são demonstrações locais no navegador; não representam a aplicação pronta nem descrevem por si só o processo atual da Mix7.

Na demonstração, cada atalho visível deve responder à ação indicada. O painel de uma demanda pode ser minimizado sem apagar a demanda: ela permanece como atalho fixo reabrível no quadro e no perfil local do navegador. Durante a execução, a bandeja permite escolher a tarefa, iniciar e pausar o cronômetro e ver o tempo registrado sem reabrir o painel; apenas uma sessão pode ficar ativa por vez neste perfil local. Ao fechar a página, a sessão é encerrada e salva; abrir de novo não conta o tempo offline. Um encerramento abrupto ainda pode não executar essa pausa. Isso registra tempo realizado, não calcula disponibilidade. As telas de equipe, clientes e calendário são consultas derivadas desses mesmos dados. Conhecimento agora permite cadastrar, editar, pesquisar, filtrar e arquivar/restaurar referências, treinamentos, contatos e trilhas com passos, responsável, público e data de revisão; isso fica no navegador local, sem conteúdo real, distribuição pessoal do onboarding ou permissões. Acessos continua explicando categorias e limites sem simular contas.

## Estado e limites atuais

Há uma demonstração local de partes da gestão e aprovação e uma biblioteca local de conhecimento; ela também permite atribuir modelos de onboarding a nomes fictícios e acompanhar passos no mesmo navegador. A prévia semanal de capacidade usa horas configuradas manualmente e estimativas com prazo; não aplica uma política confirmada pela Mix7. Não há autenticação, permissões, dados ou arquivos compartilhados, contas reais, onboarding identificado por pessoa, gestão de acessos reais, cálculo operacional de capacidade, IA integrada, avaliação implementada, nem implantação operacional. Nomes, materiais, contatos, regras de revisão e públicos reais precisam ser confirmados. Requisitos e pesquisa de produção ainda estão em revisão; stack, arquitetura ou solução reaproveitada não estão aprovadas. Veja o [roteiro para concluir a plataforma](ROADMAP.md) e a [rastreabilidade dos áudios](TRACEABILIDADE-AUDIOS.md).
