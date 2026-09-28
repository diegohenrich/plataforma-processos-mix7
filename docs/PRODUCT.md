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

## Protótipo navegável e aplicação Laravel

O [protótipo do fluxo integrado](../prototipo/README.md) valida a experiência no navegador com dados isolados daquele perfil; ele não sincroniza pessoas nem substitui a aplicação web.

`web-app/` contém a aplicação Laravel 12 com autenticação, organização, papéis, persistência MariaDB/SQLite, demandas, tarefas, autoria, fluxo de revisão, anexos privados, links de aprovação, cronômetro, produção, avaliações humanas, capacidade/Gantt, conhecimento/onboarding, catálogo de acessos, API e assistência de IA sujeita à revisão humana. O inventário de capacidades por perfil e seus limites está em [auditoria funcional](AUDITORIA-FUNCIONAL-POR-PERFIL.md); instruções e contas fictícias para explorar tudo localmente estão em [demonstração local](DEMONSTRACAO-LOCAL.md).

Na navegação, demandas e tarefas pertencem ao mesmo espaço de trabalho; aprovações e versões ficam na demanda correspondente, sem fila paralela. O cadastro de tipos de aprovação fica em sua configuração. A área Equipe agrupa pessoas, produção, disponibilidade e avaliações. Para direção e gerência, “Em que cada profissional está comprometido” mostra todas as tarefas abertas por pessoa (a fazer, em andamento, pausadas e impedidas), sua demanda, prazo/estimativa quando registrados e se há cronômetro ativo; atualiza a cada 30 segundos enquanto a página está visível. A pessoa profissional vê somente o próprio estado. O indicador é factual e não calcula desempenho nem bonificação.

## Estado e limites atuais

O Laravel funciona localmente com banco SQLite dedicado de demonstração e dados inteiramente sintéticos; esse ambiente inclui perfis de direção, gerência, profissionais e clientes, além de demandas em todas as etapas e exemplos dos módulos. O usuário pode entrar e experimentar as telas e rotas autorizadas, mas isso não equivale a uma operação compartilhada nem a validação das permissões pela Mix7.

Ainda não há implantação em Hostinger, SMTP operacional, armazenamento e restauração de backup exercitados, provedor de IA configurado e autorizado, integrações externas de acesso/publicação, matriz final de permissões validada, regras reais de avaliação/capacidade ou piloto aprovado. Os limites de upload, retenção, identidade do aprovador e regras específicas dos módulos também precisam de validação antes de dados reais. Não afirme prontidão de produção. Consulte o [roteiro para concluir a plataforma](ROADMAP.md), a [auditoria funcional por perfil](AUDITORIA-FUNCIONAL-POR-PERFIL.md), a [demonstração local](DEMONSTRACAO-LOCAL.md) e a [rastreabilidade dos áudios](TRACEABILIDADE-AUDIOS.md).
