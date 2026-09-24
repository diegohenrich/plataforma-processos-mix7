# Pesquisa inicial de soluções

**Data da consulta:** 24/09/2026
**Estado:** comparação documental inicial; não é teste de produto, cotação comercial nem decisão de compra.

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

Os recursos foram verificados nas páginas oficiais disponíveis na data acima. Esta primeira etapa foi pesquisa documental, não auditoria de segurança nem análise de cadência de manutenção dos repositórios. Planos, preços e limites podem mudar; esta pesquisa não substitui demonstração ou teste com contas da Mix7.
