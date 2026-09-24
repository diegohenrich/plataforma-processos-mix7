# Pesquisa inicial de soluções

**Data da consulta:** 24/09/2026
**Estado:** comparação documental inicial; não é teste de produto, cotação comercial, auditoria de segurança nem decisão de compra.

## O que estamos procurando

A Mix7 precisa de gestão de demandas, equipe, prazos e capacidade junto de aprovações rastreáveis. A aprovação começa por conteúdo de redes sociais e deve poder ser usada por outras áreas. Para criativos, comentários precisam permanecer ligados à versão do arquivo e, em vídeo, ao ponto certo da reprodução. A comparação separa ferramentas de gestão de trabalho das ferramentas especializadas em revisão de conteúdo, pois nenhum produto consultado foi confirmado como cobrindo sozinho todos os requisitos.

## Comparação

| Solução | Onde parece forte | Limites ou riscos para a Mix7 | Licença / modelo verificado |
| --- | --- | --- | --- |
| [Planable](https://planable.io/guides/content-approvals-in-planable/) | Calendário e aprovação de posts sociais; aprovação requerida, comentários internos e histórico de versões conforme plano; cliente pode ser marcado como aprovador e aprova formalmente com conta. | Foco em publicação social; aprovação requerida está no Pro e aprovação sequencial multinível no Enterprise. Histórico informado: 1 semana no Basic, 30 dias no Pro, ilimitado no Enterprise. Não substitui a gestão completa de equipe, onboarding, acessos e aprovações de outras áreas. | Produto comercial em nuvem. Na consulta de 24/09/2026, a tabela comercial mostrava Basic US$ 33 / Pro US$ 49 por workspace/mês, enquanto a ajuda mostrava US$ 39 / US$ 59; confirmar cotação, modalidade e limites por escrito. |
| [Frame.io](https://help.frame.io/en/articles/9105251-commenting-on-your-media) | Revisão de mídia com comentários pontuais e por intervalo em vídeo, comentários ancorados visualmente, anotações e comentários internos por plano. | É uma camada de revisão de mídia, não o registro principal de demanda, capacidade e trabalho da agência. Testar identidade e acesso do cliente, exportação de histórico, integração e custo total por membro. | Serviço comercial da Adobe. A página atual lista Free (2 membros, 2 GB, 2 projetos), Pro a US$ 15/membro/mês (até 5) e Team a US$ 25/membro/mês (até 15, com comentários internos). Confirmar impostos, região e condições de convidados no piloto. |
| [Filestage](https://help.filestage.io/en/articles/2562896-submit-your-review-decision) | Revisão de vários tipos de arquivo, decisões explícitas por versão, grupos de revisores, comentários e comparação de versões. Revisores externos podem comentar/aprovar por link sem conta. | Especializada em revisão de conteúdo, não gestão completa de capacidade/equipe. O plano Free lista 1 projeto ativo, 5 novos arquivos/mês, 2 grupos e 10 assentos internos; Starter aparece a US$ 199/mês na página atual. Confirmar moeda, cobrança e limites pagos no piloto. | Serviço comercial; testar retenção, armazenamento, exportação, acesso de convidado e integrações antes de escolher. |
| [Wekan](https://github.com/wekan/wekan) | Kanban open source, cartões, colunas, membros e etiquetas; pode ser auto-hospedado. | Não há evidência nesta pesquisa de que cubra prova visual, revisão de vídeo, aprovação externa por versão e os requisitos de agência sem extensões e operação próprias. Auto-hospedagem exige manutenção, backup e segurança que ainda precisam de análise. | Código sob MIT no repositório oficial. |
| [Plane Community](https://developers.plane.so/self-hosting/editions-and-versions) | Gestão de trabalho open source, implantação própria, documentação e APIs; pode ser avaliado como base de gestão. | Não foi confirmado como solução de comentários ancorados em imagens/vídeo ou fluxo de aprovação criativa. A Community é AGPL-3.0; recursos comerciais de outras edições têm condições próprias. É necessário avaliar obrigações da licença antes de modificar ou oferecer o serviço. Segurança e manutenção ainda precisam de análise. | Community sob AGPL-3.0; o fornecedor também mantém edições comerciais. |

## Recomendação provisória

Não adotar um único produto como plataforma inteira ainda. Prototipar um núcleo de processos com demandas, projetos/clientes, tarefas, responsáveis, prazos, arquivos e registro de decisões; tratar os fluxos de aprovação como módulos configuráveis sobre esse núcleo. O módulo de criativos adicionaria tipos de arquivo, versões e comentários ancorados (incluindo timecode para vídeo). Outros módulos poderiam reutilizar estados, permissões, trilha de auditoria e decisões sem herdar os campos exclusivos de criativos.

Antes de implementar um visualizador próprio de mídia, fazer uma prova de integração com uma solução especializada (Frame.io ou Filestage) e comparar a experiência social completa do Planable. Wekan e Plane entram como bases candidatas para gestão, não como substitutos presumidos da aprovação de mídia. A prova deve medir o percurso inteiro: abrir demanda, dividir tarefas, enviar versão ao cliente, receber a decisão, ligar retorno à versão, gerar nova rodada e registrar publicação/entrega.

Manter a escolha técnica em aberto até: validar o caso real da Mix7; testar pelo menos uma jornada ponta a ponta; confirmar custos, limites de usuários e arquivos; inspecionar permissões e APIs; verificar exportação/portabilidade e hospedagem dos dados; e analisar a licença com atenção antes de incorporar ou modificar qualquer código open source. Não copiar código ou interface proprietários.

## Manutenção, segurança e custo para uma prova

Consulta a fontes oficiais em 24/09/2026 complementa a leitura funcional acima. O número de estrelas e a frequência de publicação são apenas sinais de atividade comunitária; não comprovam qualidade, suporte nem segurança. A escolha continua condicionada a uma avaliação prática. Preços abaixo são os valores exibidos nas páginas consultadas, não uma cotação para a Mix7; modalidade, impostos e disponibilidade regional precisam ser confirmados.

| Solução | Sinal observado em 24/09/2026 | Implicação para a avaliação Mix7 |
| --- | --- | --- |
| Wekan | Repositório ativo, licença MIT e release v11.98 publicado em 24/09/2026; o projeto mantém política e avisos públicos de segurança. Avisos recentes incluem falhas de controle de acesso, upload e SSRF em versões anteriores, com versões corrigidas especificadas em cada aviso. | Fixar uma release corrigida, conferir cada aviso contra a versão que será testada, revisar configuração de autenticação/importação/exportação e provar atualização/backup antes de avaliar dados reais. A cadência alta também pede um processo de atualização e regressão. |
| Plane Community | Repositório ativo, AGPL-3.0 e release v1.4.2 em 23/08/2026. O histórico público contém avisos críticos e altos publicados em 2026; releases recentes incluem correções de segurança, mas a lista de avisos e a versão afetada/corrigida precisa ser comparada item a item com o pacote candidato. | Testar a versão estável mais recente, inspecionar avisos e configuração de segredos, SSO, convites, webhooks e permissões. Fazer análise jurídica da AGPL antes de modificar ou disponibilizar o serviço. |
| Planable | Página comercial: Basic US$ 33 e Pro US$ 49 por workspace/mês; ajuda oficial: US$ 39 e US$ 59. A página comercial lista 50 posts iniciais, depois 60/150 posts e 4/10 páginas em Basic/Pro; aprovação Required no Pro e multinível no Enterprise. Ajuda informa aprovação formal por usuário com conta, embora aprovadores possam ser convidados sem custo extra, e retenção de histórico por plano. | Não calcular custo até fornecedor esclarecer a divergência, modalidade de cobrança e impostos. No piloto, confirmar estrutura por workspace/cliente, aprovação, retenção e saída dos dados. |
| Frame.io | Página atual: Free = 2 membros, 2 GB e 2 projetos; Pro = US$ 15/membro/mês, até 5 membros e 2 TB incluídos; Team = US$ 25/membro/mês, até 15 membros, 3 TB e comentários internos. Página de ajuda documenta comentários pontuais/por intervalo, ancoragem e anotação. | Confirmar preço local/impostos, acesso de cliente, exportação e propriedade/retensão de comentários. Avaliar se a equipe e os clientes cabem nos limites sem misturar workspaces. |
| Filestage | Página atual: Free = 1 projeto ativo, 5 arquivos novos/mês, 2 grupos, 10 membros internos e revisores externos ilimitados sem conta. Starter aparece a US$ 199/mês e inclui projetos/arquivos ilimitados, 10 assentos e grupos adicionais; confirmar modalidade e cobrança. Histórico permite consultar aprovação, comentários e grupo por versão e exportar relatórios PDF, conforme ajuda oficial. | Pedir confirmação comercial e testar custos adicionais, armazenamento, retenção, exportação e identidade de revisores durante piloto com dados sintéticos. |

As páginas comerciais e de ajuda do Planable divergem na mesma consulta; no Frame.io e no Filestage os limites aqui reproduzem as páginas atuais e devem ser reconfirmados antes da compra. Recursos e preços podem mudar a qualquer momento.

Os avisos públicos citados não significam que as versões atuais continuem vulneráveis: alguns avisos informam versões corrigidas e releases posteriores. Também não substituem auditoria independente. Para qualquer teste auto-hospedado, registrar versão/tag e configuração, aplicar correções publicadas, restringir acesso à rede, não importar dados reais e conferir restauração de backup. Antes de integrar um serviço SaaS, confirmar contrato, tratamento/retensão dos arquivos, região de dados, permissões externas e exportabilidade.

## Critérios para a prova de conceito

1. Equipe consegue acompanhar demanda, responsáveis e prazo em uma visão simples de quadro e lista.
2. Cliente vê apenas itens enviados para sua aprovação e registra decisão inequívoca por versão.
3. Comentário de vídeo abre no timecode correto; comentário visual fica ligado à mídia e à versão correta.
4. Nova versão não apaga comentários, decisões ou autoria das versões anteriores.
5. Aprovação não marca automaticamente como concluída uma publicação ou entrega ainda não registrada.
6. Uma configuração futura de aprovação reutiliza permissões, decisões e auditoria sem mudar o núcleo da demanda.
7. A integração não duplica dados nem deixa o histórico de aceite inacessível ao sistema central.

## Fontes primárias consultadas

- Planable, [aprovar um post](https://help.planable.io/hc/en-us/articles/21715469772188-Approve-a-post) e [modos e configuração de aprovação](https://planable.io/guides/content-approvals-in-planable/).
- Adobe Frame.io, [comentários em mídia no Frame.io V4](https://help.frame.io/en/articles/9105251-commenting-on-your-media).
- Filestage, [decisão de revisão](https://help.filestage.io/en/articles/2562896-submit-your-review-decision), [comparação de versões](https://help.filestage.io/en/articles/9113215-how-to-verify-that-everyone-s-feedback-has-been-met) e [insights e disponibilidade por plano](https://help.filestage.io/en/articles/7033846-monitor-your-review-progress-with-insights).
- Wekan, [repositório oficial e licença](https://github.com/wekan/wekan) e [arquivo de licença MIT](https://github.com/wekan/wekan/blob/main/LICENSE).
- Plane, [edições self-hosted e licença da Community](https://developers.plane.so/self-hosting/editions-and-versions).
- Wekan, [releases](https://github.com/wekan/wekan/releases), [avisos de segurança](https://github.com/wekan/wekan/security) e [exemplo de aviso com versão afetada e corrigida](https://github.com/wekan/wekan/security/advisories/GHSA-j9p2-jm73-p549).
- Plane, [releases](https://github.com/makeplane/plane/releases) e [avisos de segurança](https://github.com/makeplane/plane/security/advisories).
- Planable, [preços e limites oficiais](https://planable.io/pricing/) e [artigo oficial sobre planos](https://help.planable.io/hc/en-us/articles/21715370520092-Questions-on-pricing).
- Adobe Frame.io, [preços e recursos por plano](https://frame.io/pricing/).
- Adobe Frame.io, [limites descritos na central de ajuda](https://help.frame.io/en/articles/10150181-getting-started-what-are-the-pricing-plans-you-offer).
- Filestage, [preços, teste e limites por plano](https://filestage.io/pricing/).
- Planable, [permissões de aprovador, conta de cliente, bloqueio após aprovação e retenção de versões](https://planable.io/guides/content-approvals-in-planable/).
- Filestage, [relatórios por revisão/versão e comparação de versões](https://help.filestage.io/en/articles/9113215-how-to-verify-that-everyone-s-feedback-has-been-met).
- Frame.io, [comentários internos, âncoras, intervalos e anotações de mídia](https://help.frame.io/en/articles/9105251-commenting-on-your-media).

Os recursos, páginas comerciais e metadados dos repositórios foram verificados nas fontes oficiais disponíveis em 24/09/2026. Preços, versões e avisos publicados podem mudar. A comparação não substitui demonstração, análise legal, auditoria de segurança ou teste com contas da Mix7. O roteiro e as evidências exigidas para a próxima avaliação estão em [PROVA-DE-CONCEITO.md](PROVA-DE-CONCEITO.md); nenhum produto foi acessado com conta ou recebeu arquivo nesta etapa.
