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

O usuário também confirmou revisão de sites por link sem exigir uma conta do cliente: a pessoa precisa conseguir avaliar, escrever comentários, selecionar texto e marcar uma área visual da página. Sites são, portanto, um segundo cenário concreto de revisão além dos criativos sociais. O modo de capturar as anotações (página incorporada, script, captura ou alternativa) depende de teste técnico; não presumir que um iframe pode ler ou marcar qualquer site, pois políticas de origem podem bloquear isso. As anotações precisam apontar para a versão revisada para continuar compreensíveis após atualização do site.

O link sem login é requisito de acesso para o cliente. A primeira implementação Laravel estabelece uma política inicial: token aleatório de 64 caracteres, armazenado somente como SHA-256; escopo em uma demanda e versão; data de expiração escolhida por quem envia; revogação pela gerência; links abertos anteriores revogados ao gerar nova versão; nome informado pelo cliente registrado como autodeclarado; comentários permitidos até uma única decisão final. A resposta só é aceita enquanto a demanda estiver em Aprovação do cliente. Rate limit protege leitura e envio. Esta política técnica ainda precisa ser validada pela Mix7 antes de compartilhar material real; não representa identificação verificada do cliente.

A tela pública exibe título, organização, número da demanda, versão e material indicado pela gerência. O material pode ser uma URL HTTP/HTTPS ou um PDF, imagem (JPEG, PNG, WebP) ou vídeo (MP4, WebM) de até 20 MB. Arquivos carregados são guardados em armazenamento privado; uma rota limitada ao token da versão os transmite apenas enquanto o link está válido e ativo. A equipe visualiza os mesmos anexos na demanda, com autorização por função e organização. Briefing, tarefas, equipe e histórico interno não são expostos. Comentários e decisão são vinculados à versão e entram na atividade interna. Aprovação leva a demanda para Entrega; pedido de ajuste leva para Ajustes. O próximo envio durante outra rodada cria nova versão.

O Laravel também aceita anotações estruturadas ligadas ao link e à versão: trecho de texto informado pelo cliente, posição aproximada X/Y em porcentagem, instante HH:MM:SS de vídeo ou página do material. Para vídeo enviado como arquivo, o cliente pode pausar a prévia e copiar o instante atual para o campo da anotação; o preenchimento manual continua disponível. Em páginas que permitem incorporação, o cliente pode clicar na prévia para preencher X/Y; se a incorporação for bloqueada, as coordenadas ficam disponíveis para preenchimento manual. A equipe consulta a âncora junto à resposta na demanda. O endereço anotado é preservado com cada comentário; anexos conservam o nome da versão sem guardar URL/token secreto na anotação. PDFs e imagens podem ser abertos no link privado do material. Seleção automática de texto, desenho livre sobre a página e leitura do tempo de vídeo remoto por URL ainda não estão implementados. A prévia usa iframe isolado e não injeta código no site. A coordenada se refere à área visível da prévia, não a um ponto DOM persistente ao rolar a página. Essa entrega não substitui a validação interativa do requisito visual com a Mix7 e em navegadores reais. O tamanho final permitido também depende dos limites de upload e espaço confirmados no plano Hostinger.

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

- Além de criativos sociais e sites, quais outros trabalhos precisam de aprovação dentro da agência? Para cada tipo, que arquivo/entrega é avaliado e quem aprova?
- Quais etapas, comentários, versões, exceções e comprovantes finais esse trabalho exige?
- O fluxo do cliente usa os mesmos aprovadores do módulo de criativos ou há aprovações internas/externas distintas?
- Que informações são exclusivas daquele tipo e quais devem continuar na demanda compartilhada?
- Para comentários de sites, a pessoa precisa revisar URL publicada, ambiente de teste ou captura? Como controlar a versão e os domínios que aceitam anotações?
- A política inicial registra nome autodeclarado, sem e-mail ou verificação. A Mix7 precisa validar se isso basta ou se deve exigir outra confirmação antes de uso real.

Até haver esses exemplos, o produto deve provar a arquitetura reutilizável e concluir o módulo de criativos conhecido, mantendo os outros tipos como módulos ainda não especificados. Ver também [requisitos](REQUIREMENTS.md), [proposta de fluxo](FLUXO-PROPOSTO.md) e a [proposta de arquitetura em revisão no PR #10](https://github.com/diegohenrich/plataforma-processos-mix7/pull/10).
