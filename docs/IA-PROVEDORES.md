# IA local da Mix7 com Gemma 3:4b

## Configuração atual

O CRM usa exclusivamente o modelo local `gemma3:4b`, servido pelo Ollama em `http://127.0.0.1:11434`. A página **Configuração de IA** permite ativar ou desativar esse provedor. Não usa login do Codex, Claude ou chave de API. O modelo e o runtime ficam instalados neste computador; os arquivos do modelo não fazem parte do Git. A instância compartilhada/Hostinger não pode alcançar o Ollama em `127.0.0.1` e permanece sem provedor até existir uma decisão específica de implantação.

O modelo oficial tem aproximadamente 4,3 bilhões de parâmetros, quantização Q4_K_M e download de 3,3 GB. Gemma 3 aceita texto e imagens. O CRM restringe o serviço local a `APP_ENV=local`, host de loopback, modelo e endpoint fixos; não aceita um endpoint externo nesta configuração. Instale Ollama 0.6 ou mais recente, baixe `gemma3:4b` e mantenha o serviço local disponível durante o uso. Para reinstalar, execute `ollama pull gemma3:4b`.

## Assistentes do CRM

A conexão alimenta o briefing conversacional antes de salvar uma demanda, o copiloto contextual das áreas, o assistente de demanda, o planejamento e a organização dos comentários de aprovação. O feedback continua vinculado pelo CRM à resposta original, versão e marcação do cliente. A IA só propõe texto e consultas; o servidor valida permissões e escopo das consultas; uma pessoa revisa toda sugestão. A IA não cria/aplica tarefas, não envia mensagens, não altera versões e não decide aprovações.

O catálogo oficial do Ollama não lista Gemma 3 como modelo com tool calling nativo. Para manter os assistentes com consultas internas, o adaptador do CRM usa a API nativa `/api/chat`, contexto de 8.192 tokens e resposta JSON estruturada com as ferramentas permitidas. A aplicação valida nomes e argumentos contra a lista da área antes de executar qualquer consulta. Falha de formato ou consulta inválida é recusada sem executar ferramenta. As ferramentas continuam somente leitura. Repetição de tokens e limites do Ollama são apresentados como erro recuperável, sem salvar propostas.

Na tela da demanda, direção e gerência também podem gerar uma solução sugerida com base apenas no título e no briefing. O CRM registra o texto na demanda e audita o solicitante; a equipe revisa antes de usar a orientação, e a geração não cria tarefas nem dispara ações. Uma nova geração substitui a propriedade atual; versões anteriores do texto não são mantidas nesta funcionalidade.

Na criação de demanda, a equipe pode anexar PDF, DOCX, TXT, MD ou CSV de até 5 MB. PDF.js, no navegador, extrai texto selecionável do PDF; Mammoth extrai o texto do Word. O texto é limitado a 12 mil caracteres e enviado somente ao Ollama local junto da conversa. O arquivo original não é transmitido, armazenado nem anexado automaticamente à futura demanda. PDF escaneado sem camada de texto exige transcrição/OCR externo; OCR não está incluído. A resposta guiada propõe título, briefing, campos do tipo e até cinco tarefas iniciais sem responsáveis; se o formulário estiver vazio, esses itens são inseridos para revisão, e nada é salvo até a pessoa escolher responsáveis e criar a demanda.

As solicitações para inferência são locais; o download inicial do modelo usa a biblioteca oficial Ollama. Respostas locais não têm SLA e dependem da máquina. Este computador tem uma GPU RTX 3050 Ti com 4 GB de VRAM; o modelo Q4 de 3,3 GB pode dividir execução entre GPU e RAM e responder lentamente. Chamadas sintéticas de briefing levaram 32,8 s na primeira execução e 21,3 s com o modelo aquecido. O CRM limita o contexto a 8.192 tokens, respostas conversacionais sem JSON a até 600 tokens, briefing a 800 e respostas estruturadas a 1.200, além de manter o modelo aquecido por cinco minutos. O timeout PHP dessas chamadas Gemma foi elevado de 30 para 270 s para não cortar a geração. Isso reduz recargas e evita o timeout observado, mas não oferece garantia de velocidade fixa. A configuração compartilhada em Hostinger segue pendente e não deve expor o computador pessoal à internet.

O teste inicial usa somente dados fictícios. O envio de conteúdo real dos clientes depende da política de privacidade, retenção, acesso local e consentimento operacional da Mix7; rodar local reduz a transferência a um provedor de IA, mas não substitui essas decisões.

## Fontes oficiais

- [Gemma 3:4b no catálogo Ollama](https://ollama.com/library/gemma3:4b) — tamanho, quantização, licença e modalidades.
- [Instalador Ollama para Windows](https://ollama.com/download/windows).
- [Compatibilidade da API Chat Completions do Ollama](https://ollama.com/blog/openai-compatibility).
- [Saídas estruturadas JSON Schema](https://ollama.com/blog/structured-outputs).
- [Suporte a ferramentas e modelos listados](https://ollama.com/blog/tool-support).
