# Pesquisa inicial de soluções

**Data da consulta:** 24/09/2026
**Estado:** comparação documental inicial; não é teste de produto, cotação comercial, auditoria de segurança nem decisão de compra.

## O que estamos procurando

A Mix7 precisa de gestão de demandas, equipe, prazos e capacidade junto de aprovações rastreáveis. A aprovação começa por conteúdo de redes sociais e deve poder ser usada por outras áreas. Para criativos, comentários precisam permanecer ligados à versão do arquivo e, em vídeo, ao ponto certo da reprodução. A comparação separa ferramentas de gestão de trabalho das ferramentas especializadas em revisão de conteúdo, pois nenhum produto consultado foi confirmado como cobrindo sozinho todos os requisitos.

## Comparação

| Solução | Onde parece forte | Limites ou riscos para a Mix7 | Licença / modelo verificado |
| --- | --- | --- | --- |
| [Planable](https://planable.io/guides/content-approvals-in-planable/) | Calendário e aprovação de posts sociais; permissões de aprovador, histórico de versões e agendamento após aprovação. | Especializada em publicação social; aprovações sequenciais de múltiplos níveis aparecem como recurso Enterprise. Não substitui por si só gestão completa de equipe, onboarding, acessos e aprovações de outras áreas. | Produto comercial em nuvem; plano e preço precisam ser cotados/confirmados para a Mix7. Segurança, manutenção e integração ainda precisam de análise. |
| [Frame.io](https://help.frame.io/en/articles/9105251-commenting-on-your-media) | Revisão de imagem e vídeo com comentários ancorados, timecode e colaboração sobre mídia. | É uma camada de revisão de mídia, não o registro principal de demandas, capacidade e trabalho da agência. Integração, identidade de cliente, exportação do histórico e custo precisam ser testados. Segurança e manutenção ainda precisam de análise. | Serviço comercial da Adobe; condições e limites atuais precisam ser verificados no piloto. |
| [Filestage](https://help.filestage.io/en/articles/2562896-submit-your-review-decision) | Fluxos de revisão de arquivos com decisões explícitas, grupos de revisores, versões e comentários; oferece relatórios de revisão. | Especializada em prova/aprovação de arquivos; métricas avançadas e algumas exportações completas dependem de plano Enterprise. Confirmar cobertura e integração com a gestão central. Segurança e manutenção ainda precisam de análise. | Serviço comercial; preço, limites de arquivo e retenção a confirmar. |
| [Wekan](https://github.com/wekan/wekan) | Kanban open source, cartões, colunas, membros e etiquetas; pode ser auto-hospedado. | Não há evidência nesta pesquisa de que cubra prova visual, revisão de vídeo, aprovação externa por versão e os requisitos de agência sem extensões e operação próprias. Auto-hospedagem exige manutenção, backup e segurança que ainda precisam de análise. | Código sob MIT no repositório oficial. |
| [Plane Community](https://developers.plane.so/self-hosting/editions-and-versions) | Gestão de trabalho open source, implantação própria, documentação e APIs; pode ser avaliado como base de gestão. | Não foi confirmado como solução de comentários ancorados em imagens/vídeo ou fluxo de aprovação criativa. A Community é AGPL-3.0; recursos comerciais de outras edições têm condições próprias. É necessário avaliar obrigações da licença antes de modificar ou oferecer o serviço. Segurança e manutenção ainda precisam de análise. | Community sob AGPL-3.0; o fornecedor também mantém edições comerciais. |

## Recomendação provisória

Não adotar um único produto como plataforma inteira ainda. Prototipar um núcleo de processos com demandas, projetos/clientes, tarefas, responsáveis, prazos, arquivos e registro de decisões; tratar os fluxos de aprovação como módulos configuráveis sobre esse núcleo. O módulo de criativos adicionaria tipos de arquivo, versões e comentários ancorados (incluindo timecode para vídeo). Outros módulos poderiam reutilizar estados, permissões, trilha de auditoria e decisões sem herdar os campos exclusivos de criativos.

Antes de implementar um visualizador próprio de mídia, fazer uma prova de integração com uma solução especializada (Frame.io ou Filestage) e comparar a experiência social completa do Planable. Wekan e Plane entram como bases candidatas para gestão, não como substitutos presumidos da aprovação de mídia. A prova deve medir o percurso inteiro: abrir demanda, dividir tarefas, enviar versão ao cliente, receber a decisão, ligar retorno à versão, gerar nova rodada e registrar publicação/entrega.

Manter a escolha técnica em aberto até: validar o caso real da Mix7; testar pelo menos uma jornada ponta a ponta; confirmar custos, limites de usuários e arquivos; inspecionar permissões e APIs; verificar exportação/portabilidade e hospedagem dos dados; e analisar a licença com atenção antes de incorporar ou modificar qualquer código open source. Não copiar código ou interface proprietários.

## Manutenção, segurança e custo para uma prova

Consulta aos repositórios oficiais e páginas comerciais em 24/09/2026 complementa a leitura funcional acima. O número de estrelas e a frequência de publicação são apenas sinais de atividade comunitária; não comprovam qualidade, suporte nem segurança. A escolha continua condicionada a uma avaliação prática.

| Solução | Sinal observado em 24/09/2026 | Implicação para a avaliação Mix7 |
| --- | --- | --- |
| Wekan | Repositório ativo, licença MIT e release v11.95 publicado no próprio dia da consulta; o projeto mantém política e avisos públicos de segurança. Avisos recentes incluem falhas de controle de acesso, upload e SSRF em versões anteriores, com versões corrigidas especificadas em cada aviso. | Fixar uma release corrigida, conferir cada aviso contra a versão que será testada, revisar configuração de autenticação/importação/exportação e provar atualização/backup antes de avaliar dados reais. A cadência alta também pede um processo de atualização e regressão. |
| Plane Community | Repositório ativo, AGPL-3.0 e release v1.4.2 em 23/08/2026. O histórico público contém avisos críticos e altos publicados em 2026; releases recentes incluem correções de segurança, mas a lista de avisos e a versão afetada/corrigida precisa ser comparada item a item com o pacote candidato. | Testar a versão estável mais recente, inspecionar avisos e configuração de segredos, SSO, convites, webhooks e permissões. Fazer análise jurídica da AGPL antes de modificar ou disponibilizar o serviço. |
| Planable | A página comercial oferece teste gratuito limitado a 50 posts e planos cobrados por workspace; a página consultada lista aprovação obrigatória a partir do Pro e aprovação multinível no Enterprise. Há diferença entre alguns preços exibidos na página e no artigo de ajuda oficial. | Tratar valores como referência a confirmar diretamente com fornecedor. Para o caso Mix7, medir quantos workspaces/clientes, páginas sociais, posts, aprovadores e níveis seriam necessários e pedir cotação dos recursos multinível. |
| Frame.io | Página oficial exibe camada gratuita limitada (2 membros, 2 GB, 2 projetos) e planos pagos por membro: Pro a US$ 15/mês e Team a US$ 25/mês, antes de impostos. Recursos como comentários internos aparecem no Team; condições podem mudar. | Piloto deve comparar custo real por equipe, armazenamento, permissões de cliente e exportação de comentários. Confirmar preço, disponibilidade regional e integração com o núcleo antes de estimar custo total. |
| Filestage | Página oficial oferece teste de 14 dias e plano gratuito com limite de 5 arquivos novos por mês; planos pagos incluem 10 membros e permitem acrescentar assentos em grupos. O preço dos planos não ficou estabelecido na página consultada. | Pedir cotação e testar limites por volume, assentos, armazenamento, exportação e recursos Enterprise durante um piloto com dados fictícios. |

Os avisos públicos citados não significam que as versões atuais continuem vulneráveis: alguns avisos informam versões corrigidas e releases posteriores. Também não substituem auditoria independente. Para qualquer teste auto-hospedado, registrar versão/tag e configuração, aplicar correções publicadas, restringir acesso à rede, não importar dados reais e conferir restauração de backup. Antes de integrar um serviço SaaS, confirmar contrato, tratamento/retensão dos arquivos, região de dados, permissões externas e exportabilidade.

## Revalidação das fontes oficiais — 25/09/2026

Esta consulta atualiza os preços, limites e versões registrados em 24/09. Os valores abaixo são publicados pelos fornecedores, em dólares americanos, e não incluem uma cotação da Mix7, impostos ou conversão cambial.

| Solução | Evidência oficial atualizada | Consequência para uma prova Mix7 |
| --- | --- | --- |
| Planable | A página pública exibe Basic por US$ 33 e Pro por US$ 49 por workspace/mês; a página oficial de ajuda, atualizada em 04/09/2026, exibe US$ 39 e US$ 59, respectivamente, e diz que Enterprise começa tipicamente em US$ 2.500/ano. A página pública coloca aprovações multinível no Enterprise. O produto recomenda um workspace por cliente. | Há divergência de preço no próprio material oficial; não projetar custo sem cotação escrita. Simular o número real de clientes/workspaces e confirmar qual plano atende fluxo, armazenamento, histórico e usuários externos. |
| Frame.io | A página de preços lista Pro por US$ 15 por membro/mês (até 5 membros) e Team por US$ 25 por membro/mês (até 15 membros), mais imposto; Pro inclui 2 TB e Team 3 TB. A tabela atribui comentários internos e pastas restritas ao Team. | A cobrança é por membro e a organização de acesso mais granular aparece no Team. O teste gratuito exige informações de contato e cobrança e, segundo o fornecedor, converte automaticamente em assinatura se não for cancelado antes do fim; não iniciar sem um plano de cancelamento e aprovação da Mix7. |
| Filestage | O plano gratuito lista 1 projeto ativo, 5 arquivos novos/mês, 10 lugares e 2 grupos de revisores; revisores externos são ilimitados e não precisam criar conta. Starter aparece por US$ 199/mês e Business por US$ 329/mês; cada plano pago inclui 10 lugares, com pacotes adicionais de cinco. O teste de 14 dias retorna ao plano gratuito ao terminar, sem upgrade. | A faixa gratuita permite validar uma jornada pequena, mas cinco arquivos por mês não provam volume operacional. Cotar planos pagos e testar isolamento de revisores, exportação dos relatórios e comentários vinculados a versões. |
| Wekan | A release oficial v11.99 foi publicada em 24/09/2026. O repositório lista avisos de controle de acesso publicados em 14/09. | A release recente não é evidência de que cada aviso esteja corrigido. Qualquer candidato exige mapear aviso→versão corrigida, conferir configuração e executar testes de permissão antes de importar dados. |
| Plane Community | A release oficial mais recente continua v1.4.2 (23/08/2026), com código comunitário sob AGPL-3.0. A página de avisos lista divulgações críticas em 03/08; por exemplo, o limite de tentativas no login por código está corrigido desde 1.4.0. | Conferir todos os intervalos afetados e correções contra a imagem exata do piloto; revisar a licença com orientação jurídica antes de alterar/distribuir a edição comunitária. A análise de avisos não constitui auditoria de segurança. |

**Síntese provisória:** a prova de revisão criativa pode usar um plano gratuito de Filestage para validar um caso sintético; isso não demonstra adequação de custo, privacidade ou escala. Planable deve entrar somente com preço confirmado devido à divergência pública. Frame.io exige atenção ao custo por membro e ao fim automático do teste. Wekan e Plane não são recomendados para dados da agência antes da verificação de avisos, permissões, backup e atualização. Esta revalidação não escolhe fornecedor nem arquitetura.

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
- Filestage, [preços, limites e teste do plano atual](https://filestage.io/pricing/).
- Wekan, [repositório oficial e licença](https://github.com/wekan/wekan) e [arquivo de licença MIT](https://github.com/wekan/wekan/blob/main/LICENSE).
- Plane, [edições self-hosted e licença da Community](https://developers.plane.so/self-hosting/editions-and-versions).
- Wekan, [releases](https://github.com/wekan/wekan/releases), [avisos de segurança](https://github.com/wekan/wekan/security) e [exemplo de aviso com versão afetada e corrigida](https://github.com/wekan/wekan/security/advisories/GHSA-j9p2-jm73-p549).
- Plane, [releases](https://github.com/makeplane/plane/releases) e [avisos de segurança](https://github.com/makeplane/plane/security/advisories).
- Planable, [preços e limites oficiais](https://planable.io/pricing/) e [artigo oficial sobre planos](https://help.planable.io/hc/en-us/articles/21715370520092-Questions-on-pricing).
- Adobe Frame.io, [preços e recursos por plano](https://frame.io/pricing/).
- Filestage, [preços, teste e limites por plano](https://filestage.io/pricing/).
- Wekan, [releases oficiais](https://github.com/wekan/wekan/releases) e [avisos de segurança](https://github.com/wekan/wekan/security/advisories).
- Plane, [releases oficiais](https://github.com/makeplane/plane/releases), [avisos de segurança](https://github.com/makeplane/plane/security/advisories), [exemplo de correção de aviso](https://github.com/makeplane/plane/security/advisories/GHSA-mqjv-rwgv-4gxq) e [licença AGPL-3.0](https://github.com/makeplane/plane/blob/preview/LICENSE.txt).

As características iniciais foram consultadas em 24/09/2026 e os preços, limites, releases e avisos foram revalidados nas fontes oficiais em 25/09/2026. Preços, versões e avisos publicados podem mudar. A comparação não substitui demonstração, análise legal, auditoria de segurança ou teste com contas da Mix7.
