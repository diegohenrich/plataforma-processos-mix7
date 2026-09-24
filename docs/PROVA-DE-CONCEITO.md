# Roteiro de prova de conceito das soluções

**Estado:** protocolo para futura avaliação controlada; nenhum fornecedor foi acessado com conta, nenhum arquivo foi enviado e nenhum produto foi selecionado.

## Para que serve

Comparar as funções de gestão e aprovação com o mesmo cenário, para descobrir se a Mix7 deve reaproveitar componentes existentes, integrar ferramentas especializadas ou construir os módulos. A proposta de fluxo-alvo aceita é a referência do teste; não serve como relato da rotina real da agência. Um caso real ainda é necessário para confirmar atores, exceções e evidências de encerramento.

## Cenário de teste

Usar somente uma demanda fictícia: briefing de uma campanha social, duas tarefas internas atribuídas, um arquivo de imagem e um vídeo curtos gerados para teste, um comentário em cada mídia, revisão interna, pedido de alteração, V02, nova decisão do cliente e registro fictício de agendamento/publicação. Evitar nome, marca, criativo, credencial ou informação de cliente real. Não conectar contas sociais nem publicar.

O roteiro deve registrar por ferramenta: tempo para configurar e concluir cada etapa; cliques ou passos que causaram dúvida; quem vê briefing, comentário interno e arquivo compartilhado; como se identifica aprovador e versão; se o comentário retorna ao mesmo ponto; como criar e comparar nova versão; quais metadados e decisões podem ser exportados; e o que falha quando integração ou convite não funciona.

## Candidatos e papel a investigar

| Candidato | Papel possível | Perguntas que o ensaio deve responder |
| --- | --- | --- |
| Planable | Provar criação, calendário, aprovação e publicação de post social. | A estrutura de workspace/cliente encaixa na carteira da Mix7? Aprovação requerida, usuário de cliente, retenção e eventual uso de várias etapas servem ao alvo? Como exportar decisão e evento para o registro central? |
| Frame.io | Provar revisão de vídeo/imagem, comentário ancorado, timecode e versões. | O cliente acessa com o nível de conta desejado? As âncoras e anotações mantêm sentido na versão seguinte? Comentários e decisões são exportáveis e associáveis a uma demanda externa? |
| Filestage | Provar revisão genérica de arquivos, grupos, decisão por versão e comparação. | Link externo atende as regras de acesso? A diferença entre projeto, arquivo, grupo e rodada comporta processos de várias áreas? Relatórios preservam evidência suficiente para o núcleo? |
| Wekan | Avaliar quadro e cartões como gestão interna auto-hospedada. | A versão exata é mantida com correções de segurança? A configuração de papéis, API, exportação, mídia, backup/restauração e manutenção é viável? Revisão criativa exigiria integração adicional? |
| Plane Community | Avaliar gestão de trabalho auto-hospedada. | O conjunto de recursos comunitários cobre as visões e dependências necessárias? Qual trabalho de extensão é necessário para aprovação de mídia? Quais obrigações AGPL se aplicariam ao modo de uso/alteração pretendido? |
| OpenProject Community e Premium Enterprise | Avaliar gestão interna, dependências, Gantt, tempo e comparar capacidade por edição. | A versão/edição exata cobre a jornada sem adicionar fricção? Os papéis por projeto permitem separar com segurança equipe e clientes? O registro de decisões e arquivos pode ser exportado pela interface/API? O recurso de capacidade do Premium vale seu custo frente à necessidade real? Qual é o esforço de instalação, correção, backup, restauração e atualização? |

As funções acima são hipóteses de avaliação apoiadas por documentação dos fornecedores, não resultados observados em uma conta da Mix7. Comparar componentes por especialidade: não exigir que uma ferramenta de calendário social prove revisão de mídia, nem que um quadro de tarefas prove isolamento de cliente e aprovação por versão. Para OpenProject, contar como **documentado mas não observado** o Gantt, o registro de tempo, os papéis por projeto e a API até percorrer essas funções numa instalação fixada. A disponibilidade da equipe e atributos de usuário estão na Community; o planejador de capacidade é recurso Premium Enterprise e precisa de comparação de custo. Registrar como **sem evidência** qualquer isolamento de cliente ou aprovação de mídia que não seja demonstrada.

## Critérios de decisão

**Eliminatórios:** associar cada decisão à demanda e à versão exata; controlar o acesso do cliente e separar comentários internos; exportar ou recuperar histórico; permitir corrigir falha de integração sem duplicar demanda; não exigir credenciais de rede social compartilhadas como texto; permitir retirar o acesso externo. Se qualquer critério não puder ser demonstrado, registrar a lacuna e não usar o produto em dados reais.

**Comparativos:** fricção para equipe/cliente; rapidez para configurar nova área; capacidade de reutilizar etapas; qualidade de revisão de imagem/vídeo; lembretes; compatibilidade com desktop e arquivo da Mix7; esforço operacional de auto-hospedagem; custo total por usuário, cliente, armazenamento, add-on e integração; obrigações de licença; facilidade de exportar e sair do fornecedor.

Não reduzir a decisão a uma nota única. Para cada critério marcar **atende observado**, **documentado mas não observado**, **não atende** ou **sem evidência**; acrescentar fonte, data, plano/edição e captura ou evidência de teste. Separar fato do fornecedor de observação própria.

## Portões antes de qualquer piloto externo

1. Validar o cenário e os papéis contra ao menos um caso real da Mix7.
2. Designar responsável da Mix7 pela conta de teste e confirmar termos, plano, região de dados, retenção, exclusão e forma de saída.
3. Aprovar explicitamente ferramenta, conta, destinatários e os dados sintéticos que poderão ser enviados. Não carregar arquivos ou conversas de cliente.
4. Fixar a edição/release avaliada; para auto-hospedagem, revisar licença, avisos de segurança, configuração, rede, backup e restauração antes de iniciar.
5. Registrar resultado e recomendação no comparativo, arquitetura conceitual e cartão Trello antes de escolher stack ou comprar plano.

Enquanto esses portões não forem atendidos, a comparação documental pode orientar protótipo local e perguntas, mas não prova segurança, integração, adequação operacional ou custo final.

## Resultado a preencher depois do ensaio

Para cada ferramenta: data, edição/plano/release, cenário percorrido, evidências, critérios eliminatórios, limitações, estimativa de custo total, exportação testada, decisão provisória (descartar, repetir ou considerar) e responsável da Mix7. Uma decisão favorável exige sucesso no fluxo de ponta a ponta e recuperação independente do histórico, além da confirmação operacional da Mix7.
