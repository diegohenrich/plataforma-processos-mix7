# Conhecimento e onboarding

## Origem do requisito

No áudio 3 (02:54–03:50), o usuário pede um mapa de referências, treinamento e informações necessárias ao trabalho; uma trilha de onboarding em passos para uma pessoa recém-contratada; e uma base de contatos. Também sugere investigar um modo seguro de obter acesso sem expor senhas, sem afirmar que a plataforma deve guardar credenciais.

O cartão Trello [#10 — Definir central de conhecimento e onboarding](https://trello.com/c/4V8fFh5l/10-definir-central-de-conhecimento-e-onboarding) acrescenta, como critério de conclusão de conteúdo, responsável, público e data de revisão. Esses três metadados vêm do critério de planejamento no Trello, não de uma fala do áudio.

## O que a fatia local permite

No menu **Conhecimento**, é possível criar, editar, buscar e filtrar quatro tipos: referência, treinamento, contato e onboarding. Um registro tem nome, resumo/instrução, responsável textual, público textual e data de revisão; pode ter um link http(s). Treinamentos e onboardings podem ter passos em ordem, e uma trilha de onboarding precisa de ao menos um passo. Um item pode ser arquivado e restaurado sem exclusão definitiva.

Uma trilha ativa pode ser atribuída a um nome fictício. A atribuição salva uma cópia do título e dos passos do modelo para que uma edição futura do modelo não altere o percurso já iniciado. Cada passo pode ser marcado ou reaberto; horário e tipo de evento ficam no histórico local. O cartão mostra o progresso e só aparece como concluído quando todos os passos estão marcados. Esse estado é um registro demonstrativo do navegador, não confirmação de identidade ou conclusão por uma pessoa real.

Os modelos são gravados na chave `mix7.knowledge.v1`; as atribuições e seu progresso ficam separados em `mix7.onboarding-assignments.v1`, no `localStorage` deste perfil de navegador. O módulo é independente dos dados de demanda. Use apenas conteúdo, nomes e contatos fictícios; não registre senhas, dados reais de clientes ou informações pessoais.

## Limites e decisões pendentes

- Não há login, permissões, sincronização entre pessoas, armazenamento central, cópia de segurança ou histórico de revisões dos modelos.
- O nome de atribuição é texto livre e não identifica uma conta; o produto não comprova quem marcou os passos nem compartilha progresso com a pessoa ou com a equipe.
- Responsável e público são texto livre e não concedem acesso. Os campos propostos para contato ainda precisam ser validados com a Mix7.
- Não há cofre de senhas ou integração de autenticação. Pesquisar essa possibilidade em serviço apropriado após definir requisitos, segurança e gestão de acessos; nunca guardar credenciais na demonstração.
- A agência ainda precisa confirmar os conteúdos reais, quem os mantém, o público autorizado, regras e frequência de revisão, campos da base de contatos, anexos e como acompanhar onboarding individual.

## Critérios verificados nesta demonstração

1. Criar cada um dos quatro tipos, com nome, resumo, responsável, público e data de revisão.
2. Criar treinamento e onboarding com passos em ordem; impedir onboarding sem passo.
3. Rejeitar data inválida e links que não usem `http` ou `https`.
4. Editar, pesquisar, filtrar, arquivar, restaurar e recarregar sem perder o registro no mesmo navegador.
5. Atribuir um modelo ativo a uma pessoa fictícia, marcar todos os passos, reabrir um passo e conferir histórico e estado.
6. Recarregar o navegador e verificar se as atribuições e o progresso continuam neste mesmo perfil.
7. Usar modelos, atribuições e progresso em desktop e em 390 × 844 sem rolagem horizontal.

A suíte automatizada local está em `tests/knowledge.test.js` e `tests/onboarding.test.js`; a conferência visual dessa demonstração está registrada em `docs/PRIMEIRA-IMPLEMENTACAO.md`.

## Estado compartilhado no Laravel

A biblioteca web usa `knowledge_items`, `onboarding_assignments` e `onboarding_assignment_steps`, criadas pela migração `web-app/database/migrations/2026_09_25_000010_create_knowledge_library_tables.php`. Referências, treinamentos, contatos e trilhas pertencem à organização, registram autoria de criação/atualização, permitem busca, filtros, link HTTP/HTTPS e arquivamento/restauração. Direção e gerência mantêm conteúdo; profissionais ativos consultam. Clientes e contas inativas não acessam esta área. Estes são os limites implementados, sujeitos à matriz final de permissões dos quatro papéis.

Uma trilha pode ser atribuída a uma conta profissional ativa da mesma organização. O sistema registra quem atribuiu, copia título e passos para preservar o percurso iniciado e deixa apenas a própria pessoa ou gerência marcar/reabrir passos; o usuário e o horário de conclusão ficam registrados. Nenhuma senha ou credencial é armazenada. Essa entrega é a primeira fatia compartilhada; contatos e metadados não constituem diretório de clientes ou política de dados reais aprovada.
