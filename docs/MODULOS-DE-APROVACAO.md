# Aprovações por módulos

## Direção confirmada

A plataforma terá um sistema de gestão compartilhado e aprovações conectadas a ele. Aprovar criativos de redes sociais é o primeiro tipo conhecido, mas a arquitetura precisa permitir aprovações de outras áreas da agência sem criar cadastros e históricos desconectados. Os áudios não identificam quais serão esses outros tipos; a Mix7 precisa nomeá-los com exemplos de trabalho.

## Núcleo compartilhado

Toda demanda de aprovação se associa ao mesmo cadastro de cliente/projeto, aos participantes autorizados e ao histórico de trabalho. Um módulo define o tipo de solicitação, os campos de entrada, etapas e transições, quem decide, os arquivos e evidências exigidos e o que significa concluir aquele tipo.

O núcleo comum mantém:

- a demanda e sua origem, responsável e histórico;
- tarefas, dependências e prazos ligados à demanda;
- permissões para equipe e cliente;
- ativos e versões preservados, sem substituir arquivos já avaliados;
- comentários, revisão e decisões ligados à versão e ao ator correto;
- registro auditável das transições e da evidência de entrega.

Um módulo pode acrescentar campos, validadores, âncoras de comentário, etapas ou evidências próprias. Ele não deve duplicar o cadastro de cliente, as identidades, o controle de acesso, a trilha de auditoria ou as regras comuns de transição.

## Primeiro módulo: criativos sociais

O tipo já descrito nos áudios aceita imagens e vídeos; cliente autorizado aprova ou solicita alterações e pode comentar. Comentários identificam a versão; os de vídeo guardam o ponto de reprodução e os de imagem podem apontar a região. Ajustes geram nova versão e repetem revisão interna e aprovação do cliente. Aprovação, por si só, não encerra a demanda: o módulo registra agendamento/publicação ou entrega com evidência antes de concluir.

O fluxo integrado aprovado para demanda também inclui briefing, planejamento revisado, execução, revisão interna, decisão do cliente, ajustes quando pedidos e conclusão conferida. Papéis e exceções do processo real continuam a validar com a Mix7.

## Requisitos de expansão

1. Cada demanda identifica seu módulo e sua versão de configuração, e registra os campos necessários para esse módulo.
2. O núcleo aplica identidade, permissão, isolamento de cliente, autoria e histórico em todos os módulos.
3. Um módulo pode declarar etapas, transições e evidências de conclusão sem alterar o ciclo de outro módulo.
4. Arquivos, comentários e decisões continuam ligados ao item e à versão corretos; novas versões não apagam decisões anteriores.
5. O estado do núcleo e os dados específicos do módulo podem ser exportados e compreendidos no histórico da demanda.
6. A criação de um novo módulo precisa ter cenário de sucesso, ajustes/reprovação, falha, acesso autorizado e critério de conclusão testáveis.

Estes requisitos descrevem expansão funcional. Se um novo tipo exigir lógica exclusiva, a necessidade será especificada e revisada; não se presume que todos os módulos possam ser criados só por configuração nem que precisem ser codificados da mesma maneira.

## Perguntas para a Mix7

- Quais outros trabalhos precisam de aprovação dentro da agência? Para cada tipo, que arquivo/entrega é avaliado e quem aprova?
- Quais etapas, comentários, versões, exceções e comprovantes finais esse trabalho exige?
- O fluxo do cliente usa os mesmos aprovadores do módulo de criativos ou há aprovações internas/externas distintas?
- Que informações são exclusivas daquele tipo e quais devem continuar na demanda compartilhada?

Até haver esses exemplos, o produto deve provar a arquitetura reutilizável e concluir o módulo de criativos conhecido, mantendo os outros tipos como módulos ainda não especificados. Ver também [requisitos](REQUIREMENTS.md), [proposta de fluxo](FLUXO-PROPOSTO.md) e a [proposta de arquitetura em revisão no PR #10](https://github.com/diegohenrich/plataforma-processos-mix7/pull/10).
