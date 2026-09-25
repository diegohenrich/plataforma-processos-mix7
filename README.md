# Plataforma de Processos Mix7

Projeto da Mix7 para reunir gestão de equipe e aprovações em um fluxo documentado, rastreável e integrado. O primeiro módulo de aprovação tratará criativos de redes sociais; o modelo deverá comportar outras áreas da agência.

**Estado:** requisitos priorizados, pesquisa comparativa, arquitetura de produto, protótipo visual e primeira fatia local estão preparados em pull requests encadeados e ainda aguardam revisão. A fatia local é demonstrativa, não é ferramenta operacional. Não há stack ou arquitetura técnica escolhidas.

## Primeira entrega pretendida

Demanda/briefing → planejamento confirmado → execução → revisão interna → decisão do cliente por versão → ajustes e nova versão, se necessários → registro de publicação/entrega → conclusão conferida. O usuário aceitou esse fluxo como direção do produto. Ele não afirma como a Mix7 trabalha hoje: papéis, exceções, evidências e variações reais ainda dependem de um caso real. A IA poderá sugerir tarefas, responsáveis e estimativas, mas uma pessoa deverá confirmar antes de aplicar as sugestões.

## Organização

- [Requisitos e dúvidas abertas](docs/REQUIREMENTS.md)
- [Fluxo proposto para uma demanda de criativo](docs/FLUXO-PROPOSTO.md)
- [Pesquisa inicial de soluções](docs/PESQUISA-SOLUCOES.md)
- [Roteiro da prova de conceito](docs/PROVA-DE-CONCEITO.md)
- [Proposta inicial de arquitetura de produto](docs/ARQUITETURA-PROPOSTA.md)
- [Identidade, papéis e acessos](docs/IDENTIDADE-E-ACESSOS.md)
- [Especificação funcional inicial](https://github.com/diegohenrich/plataforma-processos-mix7/blob/spec/requisitos-priorizados/docs/ESPECIFICACAO-MVP.md) (PR #8)
- [Produto e público](docs/PRODUCT.md)
- [Fases e critérios de avanço](docs/ROADMAP.md)
- [Como usar o quadro e as etiquetas do Trello](docs/TRELLO.md)
- [Como contribuir](CONTRIBUTING.md)
- [Quadro de trabalho no Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7)

O Trello é a fonte canônica para cartões e andamento. Este repositório guarda documentação durável e, futuramente, código, testes e histórico de alterações. Os arquivos `.ai/` resumem o contexto vigente para continuidade entre agentes.

**Regra de continuidade:** cada alteração ou tarefa concluída deve resultar em commit enviado e verificado no GitHub e atualização do cartão correspondente no Trello, com o resultado e o link. Consulte [AGENTS.md](AGENTS.md) e [Como contribuir](CONTRIBUTING.md). A organização inicial está preservada na tag `marco-2026-09-24-organizacao-inicial`.

As pastas `Sistema de gestão de equipe` e `Sistema de aprovação de criativos das redes sociais` representam as duas frentes iniciais. A decisão arquitetural é tratá-las como núcleo de gestão e módulos de aprovação integrados; o desenho técnico será decidido somente depois do caso real e da avaliação prática.
