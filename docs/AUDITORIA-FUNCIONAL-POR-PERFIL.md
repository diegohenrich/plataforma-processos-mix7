# Auditoria funcional por perfil

**Revisão:** 26/09/2026. **Escopo:** pedidos dos três áudios, decisões posteriores e aplicação Laravel em `web-app/`.

## Resultado da auditoria

A plataforma segue a direção aprovada: gestão interna integrada a módulos de aprovação, com planejamento sujeito a revisão humana. Há uma fundação Laravel real para demandas, tarefas, acesso por perfil, tempo, revisão por link, conhecimento e assistência de IA. Isso ainda não comprova que o produto cumpre integralmente seu propósito em uso pela Mix7: a aplicação não foi implantada, os fluxos foram verificados localmente com contas/dados sintéticos e faltam decisões operacionais e testes compartilhados. Portanto, não está pronta para receber trabalho, arquivos ou clientes reais.

“Implementado localmente” nesta auditoria significa que existe código Laravel e a cobertura automatizada correspondente; não significa operação de produção. “Protótipo” significa comportamento demonstrativo em `prototipo/`, sem identidade nem sincronização entre pessoas. “Pendente” pode exigir regra aprovada, implementação ou validação em ambiente compartilhado.

## O que cada pessoa consegue fazer hoje no Laravel local

| Perfil | Ações existentes no código | Limites e pendências |
| --- | --- | --- |
| Direção da agência | Criar e consultar demandas da organização; conduzir etapas; vincular cliente; criar tarefas; aprovar ou descartar plano sugerido por IA; consultar assistente somente de leitura; emitir e revogar link de revisão; administrar convites e acessos de profissionais/clientes; transferir tarefas abertas; consultar produção da equipe; manter conhecimento e atribuir onboarding; ajustar datas planejadas; avaliar prazo e qualidade de tarefas concluídas com peso 2 registrado. | Permissões ainda não foram validadas pela Mix7. Avaliações não calculam pontos/nota: escala e fórmula não aprovadas. Publicação/agendamento social integrado, matriz de senhas ou operação compartilhada não verificados. |
| Gerência de marketing | Ações operacionais autorizadas no escopo da organização: conduzir demandas/tarefas, vincular cliente, propor e revisar plano de IA, consultar assistente permitido, emitir/revogar revisão, transferir tarefa aberta, acompanhar produção, manter conhecimento, ajustar cronograma e registrar avaliação humana de prazo/qualidade com peso 1. | Não administra convites nem suspende/restaura contas. Avaliações não calculam pontos/nota; a separação de acesso e os critérios ainda dependem da validação da matriz final pela Mix7. |
| Profissional executor | Ver demandas onde tem tarefa atribuída (e as que criou conforme a regra atual); consultar contexto necessário; mudar o estado das próprias tarefas; iniciar/pausar ou recuperar e encerrar o próprio cronômetro; ver sua produção e cronograma; ler conhecimento compartilhado, avançar etapas do onboarding atribuídas e responder às próprias avaliações. | A recuperação encerra no momento da ação, pausa a tarefa e registra auditoria; o intervalo desde a falha pode incluir tempo offline e requer revisão manual. Não há detecção automática do momento da queda nem cálculo de disponibilidade. A abrangência de dados além do trabalho atribuído precisa de aprovação da matriz final. |
| Cliente com conta | Listar e abrir apenas demandas vinculadas à própria conta; ver título, etapa e data de atualização. | A conta não abre briefing, tarefas, histórico interno ou conhecimento. O acesso a aprovação é separado e usa link público escopado, não a sessão da conta. |
| Aprovador externo por link | Abrir material da versão compartilhada, aprovar ou pedir ajuste, escrever comentário, apontar área/desenhar sobre prévia e marcar tempo de vídeo ou página de PDF; não precisa criar conta. | Nome é autodeclarado, não identidade autenticada. Seleção automática de texto não está pronta; sites podem bloquear iframe. Política contra encaminhamento, identidade do aprovador e tratamento após expiração/revogação ainda requer validação. |

As ações acima são observadas em rotas, políticas, telas e testes, mas a autorização formal de cada capacidade não foi aprovada em uma matriz da Mix7. Os perfis técnicos também não resolvem a designação por demanda de quem prepara briefing, revisa internamente, valida planejamento e confere publicação/conclusão.

## Cobertura das necessidades dos áudios e pedidos seguintes

| Necessidade | Estado comprovado | O que falta para considerar atendida |
| --- | --- | --- |
| Gestão integrada e fluxo demanda → planejamento → execução → aprovação → ajustes → conclusão | Transições e tarefas persistidas no Laravel; planejamento humano; revisão interna prevista no fluxo e conclusão protegida por tarefas. Testes cobrem transições e bloqueios. Quadro de demandas agrupa as etapas e permite movimentação somente entre transições autorizadas para direção/gerência. | Validar caso real por tipo de serviço, atores, exceções e evidência de conclusão; anexos operacionais e equivalência completa da experiência ainda precisam ser validados. |
| Quadro e lista, trabalho visível por profissional | Laravel tem quadro e lista paginada de demandas; profissionais veem apenas demandas ligadas às suas tarefas ou criadas por eles, em leitura. Um quadro separado agrupa tarefas por estado; profissionais veem e movem apenas tarefas atribuídas, enquanto direção/gerência veem tarefas da organização. O servidor valida acesso, transições e dependências, mantém autoria e encerra cronômetro quando a transição exige. Os dois quadros foram renderizados e conferidos em desktop e 390 px; a rolagem horizontal fica dentro das colunas, sem esticar a página. | Arraste real entre colunas e transições autorizadas ainda precisam de validação visual; volumes e desempenho precisam ser verificados. Anexos operacionais e equivalência completa da experiência ainda não foram validados. |
| Responsáveis e histórico identificados | Laravel registra ator e eventos em ações implementadas; convites e transferência preservam autoria e responsáveis. | Completar trilha para todo evento do fluxo e validar a matriz de papéis; SMTP/convite real não foi operado. |
| Cronômetro e painel de atividade | Laravel salva intervalos, mostra contador durante navegação, limita uma sessão ativa por pessoa, fecha ao concluir/pausar/transferir e permite recuperação manual de intervalo após encerramento abrupto; painel gerencial é factual. | A recuperação não detecta automaticamente a hora da falha; período desconhecido pode continuar contado até a ação e requer revisão. Testar em operação compartilhada e definir regras de jornada/ausência; pontuação não existe. |
| Gantt e disponibilidade | Gantt local no Laravel usa datas planejadas informadas por pessoas. | Não calcula jornada, disponibilidade, ausências nem distribuição diária de estimativas; regras ainda não foram definidas. |
| Aprovação de imagem, vídeo, PDF e site por link | Link sem conta, versões, respostas, escopo por material e arquivos privados implementados; comentário pode registrar área, rabisco, instante de vídeo e página PDF. A fila autenticada lista versão, validade, estado e até três respostas recentes; direção/gerência veem a organização, profissional somente demandas ligadas ao próprio trabalho e cliente não acessa a fila interna. | Testes reais em dispositivos/sites, seleção de texto, acessibilidade e regras operacionais de identidade/encaminhamento ainda pendentes. |
| Entrega, agendamento ou publicação | O fluxo-alvo pede evidência de destino e conclusão conferida; o protótipo representa etapas. | Integração e prova de publicação/agendamento não estão implementadas; a Mix7 precisa dizer quais serviços e evidências são necessários. |
| IA assistida e agentes por área | Laravel propõe tarefas/perfis/estimativas/dependências, permite editar e requer aprovação humana; assistente por demanda é somente leitura. Testes usam respostas simuladas. | Nenhuma chamada real foi feita; provedor, custo, retenção, envio de briefing e worker precisam ser aprovados/configurados. Agentes para todas as áreas e automações com escrita não existem. |
| Avaliação e evolução | Painel mede estados, estimativas, conclusões e tempo como fatos. Direção/gerência registram avaliação humana de prazo e qualidade por tarefa concluída, evidências e fatores externos; peso 2/1 do avaliador fica gravado. Profissional responde e preserva histórico. | Pontuação calculada, nota, escala, fórmula, período, contestação e finalidade não foram decididos nem implementados. |
| Conhecimento, contatos, treinamento e onboarding | CRUD compartilhado por organização, busca, arquivo/restauração e atribuição de trilha com progresso registrado no Laravel. | Conteúdo real validado, governança, política de atualização, gestão segura de credenciais, backup e operação multiusuário. |
| Serviço web e futura migração | Aplicação Laravel e API inicial estão em desenvolvimento; MariaDB/Hostinger é direção aprovada. | Sem implantação nem validação do plano Hostinger, SMTP, fila, armazenamento/backup compartilhado ou cliente Windows. |
| Referência visual Mix7 | Direção de produto aponta para o CRM Mix7 local, não para TryCRM. | Comparação autenticada e capturas visuais atuais ainda não foram feitas nesta auditoria; não alegar paridade. |

## Roteiro de teste manual para a apresentação

Use exclusivamente banco isolado, contas e conteúdo fictícios. Antes de apresentar, confirme que a instalação é local e que os testes não enviam e-mail nem publicam em serviço externo.

1. **Direção:** entrar; criar demanda fictícia e cliente de teste; consultar etapas; criar tarefa; conferir responsável e histórico; transferir para profissional ativo e conferir pausa do cronômetro e autoria; verificar painel da equipe; emitir, abrir e revogar link de revisão.
2. **Gerência:** conferir ações de planejamento, tarefa, cliente, cronograma e produção; confirmar que a tela bloqueia convites e suspensão/restauração de contas; revisar a proposta de IA sem aprovar e verificar que nenhuma tarefa foi criada.
3. **Profissional:** entrar em conta própria; confirmar que só vê seu trabalho; iniciar timer, navegar entre páginas, pausar e concluir; confirmar intervalo salvo e tentativa de controlar tarefa alheia recusada; abrir conhecimento e concluir uma etapa de onboarding atribuída.
4. **Cliente com conta:** confirmar que só vê as demandas vinculadas e os campos reduzidos; tentar abrir demanda de outra conta e rotas internas; confirmar acesso negado.
5. **Aprovador externo:** em janela privada, abrir link fictício; conferir material/versão, enviar comentário e pedido de ajuste, aprovar em um segundo link de teste; testar expiração/revogação e confirmar que o material deixa de abrir.
6. **Casos de erro e operação:** testar tarefa concluída, profissional inativo, organização diferente, arquivo inválido e link revogado; conferir resposta compreensível e histórico. Não usar dados de cliente nem mandar material externo.

Esse roteiro orienta uma demonstração; não substitui a execução. Até haver evidência de cada passo no ambiente compartilhado, marque cada resultado como “não testado”. Para links com cliente real, IA, SMTP, publicação e migração de dados, não prossiga na apresentação.

## Próximas lacunas que impedem declarar o propósito cumprido

1. Aprovar matriz completa de leitura e escrita por direção, gerência, profissional, cliente autenticado e aprovador por link.
2. Validar uma demanda real anonimizada, incluindo revisão interna, exceções e comprovante de conclusão/publicação.
3. Fechar regras de capacidade, disponibilidade, avaliação e contestação antes de calcular ou pontuar pessoas.
4. Aprovar política de IA/dados/custo, configurar worker e provedor e testar com conteúdo sintético antes de considerar dados reais.
5. Validar operações de revisão por dispositivo e tipo de site, segurança do link e comportamento das versões.
6. Confirmar implantação, SMTP, arquivos privados, cópia/restauração e limites da hospedagem antes de qualquer uso compartilhado.
7. Concluir a comparação visual usando somente o CRM Mix7 local como referência, sem migrar seu banco nem usar o TryCRM como referência visual.

## Decisões, dados de teste e continuidade do trabalho

Uma decisão pendente não deve parar o projeto inteiro. Ela bloqueia somente a parte que precisa daquela regra para evitar comportamento enganoso ou risco com dados reais. Para continuar sem longas esperas:

| Dependência | O que fica aguardando | O que pode continuar sem a resposta |
| --- | --- | --- |
| Caso real, responsáveis e exceções do fluxo | Aprovar o processo operacional da Mix7 e declarar validado para produção. | Implementar e testar com demandas, pessoas e arquivos fictícios; registrar a regra provisória como hipótese.
| Matriz final de permissões | Conceder acessos reais e afirmar que todos os papéis estão aprovados. | Criar usuários sintéticos por perfil, testar isolamento e refinar interfaces, mantendo a matriz como pendência explícita.
| Jornada, disponibilidade e capacidade | Calcular carga/dia ou recomendar alocação como se fosse correta para a Mix7. | Registrar tempo real de tarefa, exibir datas informadas, preparar componentes e avaliar cenários claramente fictícios.
| Fórmula e governança de avaliação | Emitir nota/ranking ou associar resultado a decisão de pessoal. | Exibir fatos sem pontuação (tarefas, horas, conclusões) e testar relatórios neutros.
| Política/provedor de IA | Enviar briefing ou dados da Mix7 a qualquer serviço externo e afirmar custo/gratuidade. | Desenvolver fluxo com respostas simuladas/local sintético, validar revisão humana, limites, auditoria e tratamento de falhas.
| SMTP, hospedagem, armazenamento e backup | Abrir a aplicação a usuários reais ou prometer e-mail/recuperação/backup funcionais. | Desenvolver localmente, usar notificações falsas e SQLite descartável, testar uploads em diretório temporário e documentar a preparação.
| Identidade do aprovador e política do link | Enviar link/material real e tratar o nome digitado como autenticação. | Testar token sintético, expiração, revogação, isolamento e feedback em ambiente local.
| Operações exatas de integração com Windows/serviços externos | Declarar integração pronta ou guardar credenciais. | Inventariar ações necessárias, construir fluxo manual e testar arraste no browser quando disponível.

**Regra de continuidade:** para teste, criar organização, perfis e registros fictícios em banco local isolado, nomeá-los “TESTE” e descartá-los ao encerrar o ambiente. Não inserir os mesmos dados em Hostinger, CRM publicado ou serviço de cliente. Uma falha em teste vira defeito registrado e a execução segue para a próxima tarefa independente; não aguardar indefinidamente uma inserção manual. Se um bloqueio impedir somente uma validação (por exemplo, conta SMTP), registrar a evidência ausente, encerrar aquela tentativa e continuar nas demais. A autorização de inspecionar `CRM-MIX7-RENEW` é apenas para obter layout; a inspeção não depende de limpar seu banco e não deve alterar persistência.

## Evidência de implementação consultada

Fontes do projeto: `web-app/routes/web.php`, políticas `DemandPolicy`, `DemandTaskPolicy`, `TeamMemberPolicy` e `KnowledgePolicy`, controladores de demandas, revisão, equipe, tarefas, conhecimento e IA; testes em `web-app/tests/Feature/`. Para requisitos e limites, consulte [requisitos](REQUIREMENTS.md), [rastreabilidade dos áudios](TRACEABILIDADE-AUDIOS.md), [arquitetura Laravel](ARQUITETURA-HOSTINGER-LARAVEL.md), [tempo e capacidade](TEMPO-E-CAPACIDADE.md) e [roadmap](ROADMAP.md).
