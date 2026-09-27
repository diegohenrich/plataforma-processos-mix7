# Plataforma de Processos Mix7

Projeto da Mix7 para reunir gestão de equipe e aprovações em um fluxo documentado, rastreável e integrado. O primeiro módulo de aprovação tratará criativos de redes sociais; o modelo deverá comportar outras áreas da agência.

**Estado:** engenharia de requisitos, protótipo local em `prototipo/` e fundação web Laravel em `web-app/`. O protótipo valida o fluxo no navegador; a nova aplicação inicia autenticação e integração com banco/API, mas ainda não está implantada nem pronta para dados reais.

## Fluxo integrado já definido

Demanda/briefing → planejamento revisado → execução → revisão interna → aprovação do cliente → ajustes e nova versão quando pedidos → entrega/agendamento/publicação com evidência → conclusão conferida. Este fluxo é um módulo do projeto completo, não o limite do escopo. A IA poderá sugerir tarefas, responsáveis e estimativas, mas uma pessoa deverá confirmar antes de aplicar as sugestões. O usuário aprovou este fluxo-alvo; um caso real ainda deve confirmar papéis, exceções e evidências por tipo de serviço, sem confundir essa validação factual com a diretriz do produto.

## Organização

- [Requisitos e dúvidas abertas](docs/REQUIREMENTS.md)
- [Rastreabilidade dos três áudios](docs/TRACEABILIDADE-AUDIOS.md)
- [Auditoria funcional por perfil e roteiro de teste](docs/AUDITORIA-FUNCIONAL-POR-PERFIL.md)
- [Fluxo proposto para uma demanda de criativo](docs/FLUXO-PROPOSTO.md)
- [Glossário e critério de conclusão](docs/GLOSSARIO.md)
- [Ficha para validar um caso real da Mix7](docs/VALIDACAO-CASO-REAL.md)
- [Produto e público](docs/PRODUCT.md)
- [Fases e critérios de avanço](docs/ROADMAP.md)
- [Arquitetura web Laravel e Hostinger](docs/ARQUITETURA-HOSTINGER-LARAVEL.md)
- [Operação e cópias de segurança](docs/OPERACOES-E-BACKUP.md)
- [Contrato atual da API versionada](docs/API.md)
- [Primeira implementação e seus limites](docs/PRIMEIRA-IMPLEMENTACAO.md)
- [Demonstração local com contas e fluxos fictícios](docs/DEMONSTRACAO-LOCAL.md)
- [Conhecimento e onboarding: escopo e pendências](docs/CONHECIMENTO-ONBOARDING.md)
- [Histórico de validações anteriores](docs/VALIDACOES-HISTORICAS.md)
- [Referência visual validada no CRM Mix7](docs/REFERENCIA-VISUAL.md)
- [Como usar o quadro e as etiquetas do Trello](docs/TRELLO.md)
- [Como contribuir](CONTRIBUTING.md)
- [Quadro de trabalho no Trello](https://trello.com/b/RkWOzDcu/desenvolvimento-de-projetos-mix7)

O Trello é a fonte canônica para cartões e andamento. Este repositório guarda documentação durável, código, testes e histórico de alterações. Os arquivos `.ai/` resumem o contexto vigente para continuidade entre agentes.

**Regra de continuidade:** cada alteração ou tarefa concluída deve resultar em commit enviado e verificado no GitHub e atualização do cartão correspondente no Trello, com o resultado e o link. Consulte [AGENTS.md](AGENTS.md) e [Como contribuir](CONTRIBUTING.md). A organização inicial está preservada na tag `marco-2026-09-24-organizacao-inicial`.

Para executar a demonstração local, siga [prototipo/README.md](prototipo/README.md). Para a nova aplicação web, consulte [a configuração Laravel](docs/ARQUITETURA-HOSTINGER-LARAVEL.md). Não use dados reais: o protótipo guarda dados só neste navegador e a aplicação Laravel ainda não tem permissões completas, operação de produção nem implantação. As pastas `Sistema de gestão de equipe` e `Sistema de aprovação de criativos das redes sociais` representam as duas frentes iniciais.
