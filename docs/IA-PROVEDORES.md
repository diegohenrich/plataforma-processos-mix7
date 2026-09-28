# Pesquisa inicial de provedores de IA

Levantamento em 27/09/2026 para escolher como testar o assistente textual da plataforma. Preços, modelos e cotas mudam; conferir as páginas oficiais novamente antes de configurar. Esta pesquisa não aprova envio de dados reais da Mix7.

## Necessidade do produto

A IA deve ajudar a equipe a entender pedidos, fazer perguntas úteis, estruturar e revisar briefings, oferecer orientação técnica/criativa e organizar feedback. No caso de vídeo, pode preparar roteiro, estrutura de cenas e instruções ou prompts para ferramentas externas; não precisa gerar o vídeo. Cada tipo de serviço terá um especialista com instruções próprias. A pessoa revisa e confirma qualquer rascunho ou proposta. A conexão é administrada centralmente, sem exigir que cada profissional forneça uma chave.

## Opções observadas

| Opção | O que oferece sem cobrança inicial | Limites relevantes para a Mix7 |
| --- | --- | --- |
| Google Gemini API / AI Studio | A documentação lista modelos e uso de API com tokens gratuitos em planos Free Tier. | A cota varia por modelo e projeto; há limites de requisição/tokens. A página de preços informa que conteúdo enviado no nível gratuito pode ser usado para melhorar produtos. Usar apenas exemplos sintéticos até política e termos serem aprovados. |
| Groq API | Há modelos acessíveis no Free Plan, com limites por organização e por modelo. | Cotas podem ser atingidas e a API retorna `429`; os limites exatos devem ser conferidos na conta e no modelo escolhido. É uma possibilidade de protótipo, não uma promessa de disponibilidade contínua. |
| OpenRouter | Catálogo com modelos gratuitos e API unificada. | O plano gratuito informa 50 chamadas/dia, sem SLA contratual. O resultado depende também da disponibilidade e das cotas dos provedores dos modelos. |
| Modelo local (ex.: Ollama) | Sem cobrança por token para executar um modelo instalado em equipamento próprio. | Requer máquina/servidor com memória e capacidade adequadas, energia, manutenção e acesso de rede seguro. Para a aplicação Laravel hospedada, seria necessária uma conexão operacional segura; não é gratuito em custo total nem garante serviço sempre disponível. |

## Recomendação

Começar por uma avaliação controlada com exemplos fictícios em dois provedores gratuitos (Gemini e Groq, por exemplo) e uma lista de tarefas representativas: briefing de site, campanha de e-mail, vídeo, criativo social, perguntas de esclarecimento, orientação técnica e revisão de feedback. Medir clareza em português, perguntas que detectam lacunas, aderência ao formato estruturado, consistência entre especialistas, latência, erros/limites e facilidade de revisão humana. Não escolher pelo nome do modelo ou por demonstração isolada.

As cotas gratuitas são adequadas para avaliação e prototipagem, mas não asseguram uso irrestrito nem disponibilidade para a operação diária. Assim, a resposta prática à pergunta “dá para começar sem pagar?” é sim, com conteúdo fictício e limites aceitos. Para “IA sempre online para a equipe”, não há garantia com esses tiers; será preciso escolher uma operação paga dentro de um orçamento aprovado ou hospedar um modelo com infraestrutura e responsabilidade de operação próprias.

## Integração implementada no CRM

A página **Configuração de IA** conecta três modos por organização:

- **API compatível com OpenAI Chat Completions:** informe URL-base, modelo e chave privada. Pode atender provedores que implementem o protocolo; ferramentas e saída estruturada precisam ser compatíveis com os recursos usados.
- **API Anthropic Messages:** informe a URL-base, um modelo válido e uma chave criada na Claude Platform. A assinatura do Claude Code não substitui uma chave de API nesse modo.
- **Claude Code local com assinatura:** executa o CLI instalado no mesmo computador da aplicação. Só habilita em `APP_ENV=local`; não é uma opção da hospedagem compartilhada. O usuário precisa fazer login no terminal com `claude` antes do teste. O CRM chama o CLI sem salvar sessão e sem ferramentas; as consultas do CRM continuam sujeitas às permissões e às confirmações humanas.

A chave de API é criptografada no banco e não é devolvida à interface. O botão de teste envia somente uma frase sintética. A integração alimenta o briefing guiado antes da demanda (incluindo campos personalizados do tipo escolhido), o copiloto em áreas internas, a ajuda contextual da demanda, o planejamento e a análise estruturada de feedback. No feedback, cada sugestão fica vinculada no servidor ao comentário, à versão e à marcação originais; a IA não altera esses dados nem cria tarefas. O operador revisa e só então copia o título sugerido para um formulário de tarefa ou envia a demanda.

## Claude Code com a assinatura atual

A documentação oficial informa que chamadas de `claude -p`, Agent SDK e aplicativos de terceiros continuam consumindo os limites de uso da assinatura; a mudança anunciada para 15/06/2026 foi pausada e o crédito mensal anunciado não está disponível. Portanto, a integração local reaproveita sua autenticação e seus limites atuais, sem prometer uso ilimitado ou separado. O CLI desta máquina está instalado, mas no teste de 27/09 respondeu que ainda não está conectado; é necessário autenticar manualmente no terminal e depois testar no CRM. [Orientação oficial sobre usar o Agent SDK com um plano Claude](https://support.claude.com/en/articles/15036540-use-the-claude-agent-sdk-with-your-claude-plan).

Para uso compartilhado na hospedagem, a documentação da Anthropic recomenda identidade de serviço para serviços de produção, em vez de chave pessoal de uma pessoa. A chave API pode ser cadastrada na configuração central do CRM; nunca deve ir para GitHub. [Autenticação e contas de serviço da Claude Platform](https://platform.claude.com/docs/en/manage-claude/authentication).

Referência dos parâmetros do CLI usados (`-p`, `--json-schema`, `--no-session-persistence`, `--tools`): [CLI do Claude Code](https://code.claude.com/docs/en/cli-usage).

O fluxo foi exercitado com respostas sintéticas em testes automatizados, mas nenhum provedor externo real nem login da assinatura foram usados. Antes de enviar conteúdo de cliente, a Mix7 ainda precisa aprovar dados e retenção do provedor, orçamento, modelo e operação da fila. O uso local autenticado depende do login manual descrito acima; o funcionamento em hospedagem depende de uma chave de API e worker/cron configurados.

## Fontes oficiais

- [Preços da Gemini API](https://ai.google.dev/gemini-api/docs/pricing) — modelos, tiers e aviso de uso de conteúdo para melhoria no tier gratuito.
- [Limites de requisição da Gemini API](https://ai.google.dev/gemini-api/docs/rate-limits) — limites por tier/projeto.
- [Limites de requisição da Groq](https://console.groq.com/docs/rate-limits) — cotas por modelo/organização e resposta `429`.
- [Preços e recursos do OpenRouter](https://openrouter.ai/pricing/) — modelos gratuitos, 50 requisições/dia no tier Free e ausência de SLA contratual nesse plano.
