# Ficha para validar uma demanda real da Mix7

**Objetivo:** registrar como uma demanda percorreu a operação real e comparar os fatos com o fluxo-alvo aprovado. Esta ficha não altera o fluxo desejado; identifica quem exerce cada papel, como a rotina varia e quais evidências o sistema precisa guardar.

## Cuidados antes de registrar

- Use um caso encerrado ou suficientemente avançado para percorrer as etapas. Anonimize o nome do cliente, da campanha, das pessoas e quaisquer dados pessoais.
- Não anexe criativos, credenciais, links privados ou informações confidenciais. Descreva o tipo de artefato e o sistema usado; registre apenas evidências que possam ser compartilhadas com segurança.
- Separe **fato observado**, **regra desejada** e **hipótese**. Se ninguém souber a resposta, anote “em aberto”; não complete por suposição.
- Descreva papéis (por exemplo, atendimento, gestor, designer, aprovador do cliente), não nomes de pessoas. Uma pessoa pode acumular papéis; registre isso quando ocorrer.

## Identificação do caso

| Campo | Registro anonimizado |
| --- | --- |
| Código do caso | |
| Tipo de serviço/entrega | |
| Canal ou formato | |
| Origem inicial do pedido | |
| Estado do caso | |
| Fonte de cada informação (relato, cartão, mensagem, arquivo autorizado) | |

## Percurso observado

Preencha uma linha por passagem relevante, inclusive espera, devolução, aprovação parcial ou etapa pulada. Se a operação atual divergir do fluxo-alvo, registre a divergência como fato sem alterar automaticamente a decisão de produto.

| Etapa e gatilho | Papel que agiu/decidiu | Sistema ou canal usado | Informação/material recebido | Saída ou decisão registrada | Evidência segura disponível | Espera/tempo conhecido | Exceção ou retrabalho |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Entrada e briefing | | | | | | | |
| Planejamento e atribuição | | | | | | | |
| Execução e acompanhamento | | | | | | | |
| Revisão interna | | | | | | | |
| Envio e aprovação do cliente | | | | | | | |
| Ajustes e nova versão | | | | | | | |
| Entrega, agendamento/publicação e conclusão | | | | | | | |

## Verificação dos requisitos dos áudios

| Área | Perguntas sobre este caso ou rotina | Resposta/fato | Fonte ou evidência segura | Estado |
| --- | --- | --- | --- | --- |
| Briefing | Quais campos faltaram ou mudaram? Quais são obrigatórios para este serviço? | | | |
| Gestão | Quem dividiu, atribuiu e executou cada tarefa? Como a pessoa via suas prioridades? | | | |
| Tempo | Houve estimativa, cronômetro, prazo ou espera externa? Que tempos são confiáveis? | | | |
| Aprovação interna | Quem revisou antes do cliente? O que verificou? Quem decidiu enviar? | | | |
| Cliente | Quem podia aprovar? A decisão foi por peça ou conjunto? Como foram feitos comentários e pedido de alteração? | | | |
| Versões | Como se identificaram versões? Feedback de imagem/vídeo ficou associado ao arquivo e ao ponto/instante correto? | | | |
| Pós-aprovação | O que ocorreu depois do aceite: entrega, agendamento, publicação ou outro destino? Quem conferiu a evidência? | | | |
| IA e automação | Que divisão de tarefas ou síntese de feedback ajudaria? Quem revisaria a sugestão antes de aplicá-la? | | | |
| Conhecimento/onboarding | Que instruções, contatos ou treinamento foram necessários para esta entrega? | | | |
| Acessos | Quais serviços foram acessados? Como o acesso foi concedido e revogado sem expor senha, se aplicável? | | | |
| Avaliação | Que dado objetivo de prazo, produção ou qualidade existe? Como bloqueios e mudanças de escopo afetam a leitura? | | | |

## Pendências e resultado

- Fatos confirmados e fonte:
- Diferenças entre a operação observada e o fluxo-alvo:
- Exceções que precisam de regra de produto ou política da agência:
- Requisitos que variam por tipo de serviço:
- Respostas ainda em aberto e papel que pode confirmá-las:
- Decisões de produto propostas (registrar separadamente em `docs/DECISIONS.md` e `.ai/DECISIONS.md` quando aprovadas):
- Critério para considerar a descoberta deste caso suficiente: etapas e decisões identificadas, exceções relevantes registradas, evidências de conclusão descritas e lacunas atribuídas a um papel para resposta.

## Proteção contra conclusões indevidas

- O fluxo-alvo aprovado inclui revisão interna, decisão do cliente por versão e registro/conferência de evidência antes da conclusão. O caso valida a aplicação e os responsáveis; divergência da rotina atual é dado para discussão, não uma revogação automática.
- A transcrição menciona pontuação e notas de desempenho, inclusive possibilidade de recompensas ou medidas disciplinares. Um caso isolado não define fórmula, política de RH nem decisão automatizada. Qualquer especificação requer critérios transparentes, contexto de bloqueios e mudanças, finalidade e revisão humana explicitamente definidos.
- A ideia de ocultar senhas não autoriza guardar credenciais no protótipo. A solução técnica e o provedor de gestão de segredos permanecem por pesquisar; nunca registrar segredos nesta ficha.
