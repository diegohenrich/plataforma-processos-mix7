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

A página **Configuração de IA** conecta quatro modos por organização:

- **API compatível com OpenAI Chat Completions:** informe URL-base, modelo e chave privada. Pode atender provedores que implementem o protocolo; ferramentas e saída estruturada precisam ser compatíveis com os recursos usados.
- **API Anthropic Messages:** informe a URL-base, um modelo válido e uma chave criada na Claude Platform. A assinatura do Claude Code não substitui uma chave de API nesse modo.
- **Claude Code local com assinatura:** executa o CLI instalado no mesmo computador da aplicação. Só habilita em `APP_ENV=local`; não é uma opção da hospedagem compartilhada. O usuário precisa fazer login no terminal com `claude` antes do teste. O CRM chama o CLI sem salvar sessão e sem ferramentas; as consultas do CRM continuam sujeitas às permissões e às confirmações humanas.
- **Codex local com login ChatGPT:** executa o Codex CLI instalado na mesma máquina da aplicação, autenticado com `codex login` e a conta ChatGPT. Só habilita em `APP_ENV=local`; não é uma credencial compartilhada para hospedagem. Cada chamada é efêmera, usa uma pasta temporária isolada e somente leitura, ignora a configuração de usuário do Codex, desabilita shell, conectores, busca web e agentes paralelos, e não recebe ferramentas do sistema operacional. As consultas autorizadas do CRM continuam sujeitas ao papel da pessoa e à revisão humana. O modelo é opcional; em branco, o CLI escolhe o padrão da conta. `AI_CODEX_BIN` permite apontar para outro executável.

A chave de API é criptografada no banco e não é devolvida à interface. O botão de teste envia somente uma frase sintética. A integração alimenta o briefing guiado antes da demanda (incluindo campos personalizados do tipo escolhido), o copiloto em áreas internas, a ajuda contextual da demanda, o planejamento e a análise estruturada de feedback. No feedback, cada sugestão fica vinculada no servidor ao comentário, à versão e à marcação originais; a IA não altera esses dados nem cria tarefas. O operador revisa e só então copia o título sugerido para um formulário de tarefa ou envia a demanda.

## Claude Code com a assinatura atual

A documentação oficial informa que chamadas de `claude -p`, Agent SDK e aplicativos de terceiros continuam consumindo os limites de uso da assinatura; a mudança anunciada para 15/06/2026 foi pausada e o crédito mensal anunciado não está disponível. Portanto, a integração local reaproveita sua autenticação e seus limites atuais, sem prometer uso ilimitado ou separado. O CLI desta máquina está instalado, mas no teste de 27/09 respondeu que ainda não está conectado; é necessário autenticar manualmente no terminal e depois testar no CRM. [Orientação oficial sobre usar o Agent SDK com um plano Claude](https://support.claude.com/en/articles/15036540-use-the-claude-agent-sdk-with-your-claude-plan).

Para uso compartilhado na hospedagem, a documentação da Anthropic recomenda identidade de serviço para serviços de produção, em vez de chave pessoal de uma pessoa. A chave API pode ser cadastrada na configuração central do CRM; nunca deve ir para GitHub. [Autenticação e contas de serviço da Claude Platform](https://platform.claude.com/docs/en/manage-claude/authentication).

Referência dos parâmetros do CLI usados (`-p`, `--json-schema`, `--no-session-persistence`, `--tools`): [CLI do Claude Code](https://code.claude.com/docs/en/cli-usage).

## Codex CLI com login ChatGPT

O Codex CLI aceita autenticação com conta ChatGPT por `codex login`; a autenticação é mantida pelo próprio CLI e não é copiada para o banco do CRM. O CRM precisa rodar no mesmo computador e sob o mesmo perfil que possui essa sessão. As chamadas consomem os limites do plano ChatGPT disponível para o Codex, sujeitos às cotas e políticas atuais. [Entrar no Codex CLI com ChatGPT](https://help.openai.com/en/articles/11381614-api-codex-cli-and-sign-in-with-chatgpt) e [usar Codex no plano ChatGPT](https://help.openai.com/en/articles/11369540-using-codex-with-your-chatgpt-plan).

O adaptador usa `codex exec --ephemeral --ignore-user-config --sandbox read-only --output-schema`, em um diretório temporário exclusivo, e não concede ferramentas da máquina. `AI_CODEX_BIN` pode apontar para um caminho diferente do comando `codex`. Essa autenticação pessoal não é uma opção de produção/Hostinger; lá, configure uma chave de API de serviço compatível.

Em 28/09/2026 o Codex CLI estava autenticado nesta máquina. O teste real foi feito com mensagens fictícias no adaptador do CRM: conexão, briefing, copiloto de aprovação, planejamento e revisão criativa responderam. A sugestão de revisão manteve comentário original, versão e âncora; o CRM não aplicou nem enviou a proposta. Uma oscilação do modelo tentou preencher capacidade e responsável sem contexto; o servidor passou a descartar esses campos sem fonte, e os testes automatizados passaram.

O uso testado segue restrito a conteúdo sintético até a Mix7 aprovar política de dados, retenção, orçamento e disponibilidade. A ativação local não configura a hospedagem, nem garante serviço contínuo. A autenticação Claude continua disponível pela opção correspondente; neste computador, ela depende do login local `claude`.

## Fontes oficiais

- [Codex CLI: entrar com ChatGPT](https://help.openai.com/en/articles/11381614-api-codex-cli-and-sign-in-with-chatgpt).
- [Codex incluído nos planos ChatGPT](https://help.openai.com/en/articles/11369540-using-codex-with-your-chatgpt-plan).
- [Codex CLI — referência](https://developers.openai.com/codex/cli/reference).
- [Orientação oficial sobre usar o Agent SDK com um plano Claude](https://support.claude.com/en/articles/15036540-use-the-agent-sdk-with-your-claude-plan).
- [Autenticação e contas de serviço da Claude Platform](https://platform.claude.com/docs/en/manage-claude/authentication).
- [CLI do Claude Code](https://code.claude.com/docs/en/cli-usage).
- [Preços da Gemini API](https://ai.google.dev/gemini-api/docs/pricing) — modelos, tiers e aviso de uso de conteúdo para melhoria no tier gratuito.
- [Limites de requisição da Gemini API](https://ai.google.dev/gemini-api/docs/rate-limits) — limites por tier/projeto.
- [Limites de requisição da Groq](https://console.groq.com/docs/rate-limits) — cotas por modelo/organização e resposta `429`.
- [Preços e recursos do OpenRouter](https://openrouter.ai/pricing/) — modelos gratuitos, 50 requisições/dia no tier Free e ausência de SLA contratual nesse plano.
