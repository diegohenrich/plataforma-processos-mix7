# Conhecimento e onboarding

## Origem do requisito

No áudio 3 (02:54–03:50), o usuário pede um mapa de referências, treinamento e informações necessárias ao trabalho; uma trilha de onboarding em passos para uma pessoa recém-contratada; e uma base de contatos. Também sugere investigar um modo seguro de obter acesso sem expor senhas, sem afirmar que a plataforma deve guardar credenciais.

O cartão Trello [#10 — Definir central de conhecimento e onboarding](https://trello.com/c/4V8fFh5l/10-definir-central-de-conhecimento-e-onboarding) acrescenta, como critério de conclusão de conteúdo, responsável, público e data de revisão. Esses três metadados vêm do critério de planejamento no Trello, não de uma fala do áudio.

## O que a fatia local permite

No menu **Conhecimento**, é possível criar, editar, buscar e filtrar quatro tipos: referência, treinamento, contato e onboarding. Um registro tem nome, resumo/instrução, responsável textual, público textual e data de revisão; pode ter um link http(s). Treinamentos e onboardings podem ter passos em ordem, e uma trilha de onboarding precisa de ao menos um passo. Um item pode ser arquivado e restaurado sem exclusão definitiva.

Esses dados são gravados na chave `mix7.knowledge.v1` do `localStorage` deste perfil de navegador. O módulo é independente dos dados de demanda. Use apenas conteúdo, nomes e contatos fictícios; não registre senhas, dados reais de clientes ou informações pessoais.

## Limites e decisões pendentes

- Não há login, permissões, sincronização entre pessoas, armazenamento central, cópia de segurança ou histórico de revisões de conteúdo.
- A trilha é uma lista de passos consultável e editável; não há atribuição nem acompanhamento de conclusão por pessoa, pois as identidades reais ainda não estão definidas.
- Responsável e público são texto livre e não concedem acesso. Os campos propostos para contato ainda precisam ser validados com a Mix7.
- Não há cofre de senhas ou integração de autenticação. Pesquisar essa possibilidade em serviço apropriado após definir requisitos, segurança e gestão de acessos; nunca guardar credenciais na demonstração.
- A agência ainda precisa confirmar os conteúdos reais, quem os mantém, o público autorizado, regras e frequência de revisão, campos da base de contatos, anexos e como acompanhar onboarding individual.

## Critérios verificados nesta demonstração

1. Criar cada um dos quatro tipos, com nome, resumo, responsável, público e data de revisão.
2. Criar treinamento e onboarding com passos em ordem; impedir onboarding sem passo.
3. Rejeitar data inválida e links que não usem `http` ou `https`.
4. Editar, pesquisar, filtrar, arquivar, restaurar e recarregar sem perder o registro no mesmo navegador.
5. Usar o formulário e a lista em desktop e em 390 × 844 sem rolagem horizontal.

A suíte automatizada está em `tests/knowledge.test.js`; a conferência visual e de interação está registrada em `docs/PRIMEIRA-IMPLEMENTACAO.md`.
