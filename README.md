# Plataforma de Processos Mix7

Projeto da Mix7 para reunir gestão de equipe e aprovações em um fluxo documentado, rastreável e integrado. O primeiro módulo de aprovação tratará criativos de redes sociais; o modelo deverá comportar outras áreas da agência.

**Estado:** descoberta e engenharia de requisitos. Ainda não há aplicação, stack ou arquitetura técnica escolhidas; há uma proposta inicial de arquitetura de produto. Nenhuma funcionalidade descrita aqui deve ser interpretada como implementada.

## Primeira entrega pretendida

Demanda → planejamento revisado → execução → aprovação do cliente → ajustes → conclusão. A IA poderá sugerir tarefas, responsáveis e estimativas, mas uma pessoa deverá confirmar antes de aplicar as sugestões. O significado exato de “conclusão” e a necessidade de revisão interna ainda serão validados com a operação da Mix7.

## Organização

- [Requisitos e dúvidas abertas](docs/REQUIREMENTS.md)
- [Fluxo proposto para uma demanda de criativo](docs/FLUXO-PROPOSTO.md)
- [Pesquisa inicial de soluções](docs/PESQUISA-SOLUCOES.md)
- [Proposta inicial de arquitetura de produto](docs/ARQUITETURA-PROPOSTA.md)
- [Produto e público](docs/PRODUCT.md)
- [Fases e critérios de avanço](docs/ROADMAP.md)
- [Como usar o quadro e as etiquetas do Trello](docs/TRELLO.md)
- [Como contribuir](CONTRIBUTING.md)
- [Quadro de trabalho no Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7)

O Trello é a fonte canônica para cartões e andamento. Este repositório guarda documentação durável e, futuramente, código, testes e histórico de alterações. Os arquivos `.ai/` resumem o contexto vigente para continuidade entre agentes.

**Regra de continuidade:** cada alteração ou tarefa concluída deve resultar em commit enviado e verificado no GitHub e atualização do cartão correspondente no Trello, com o resultado e o link. Consulte [AGENTS.md](AGENTS.md) e [Como contribuir](CONTRIBUTING.md). A organização inicial está preservada na tag `marco-2026-09-24-organizacao-inicial`.

As pastas `Sistema de gestão de equipe` e `Sistema de aprovação de criativos das redes sociais` representam as duas frentes iniciais. Sua organização técnica será definida após a pesquisa de soluções e o desenho da arquitetura.
