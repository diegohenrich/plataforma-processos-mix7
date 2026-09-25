# Identidade e acessos

**Estado:** levantamento de requisitos e comparação documental. Nenhum provedor, arquitetura técnica ou conta real foi selecionado ou criado.

## Pessoas citadas e o que sabemos

Os áudios citam tipos de participante, mas não fornecem uma lista de contas. “Tipo de participante” descreve uma capacidade observada na fala; ainda não é um cargo formal nem uma permissão pronta para cadastrar.

| Participante mencionado | Evidência dos áudios | O que está confirmado | O que ainda falta confirmar |
| --- | --- | --- | --- |
| Pessoa da Mix7 que fala em primeira pessoa | Áudio 3, trecho sobre avaliação, 01:58–02:53 | A pessoa descreve a própria nota com peso 2. | Nome do cargo, quais ações administrativas exerce e se essa regra de peso permanece na política do produto. |
| Gerente | Áudio 3, 01:58–02:53 | O gerente é citado como avaliador de peso 1. | Se também cria briefing, distribui tarefas, revisa peças ou administra contas; a fala não confirma essas ações. |
| Profissional da equipe | Áudio 3, 00:42–00:54 e 01:14–01:57 | Cada profissional precisa ver o trabalho destinado a ele e registrar tempo por tarefa. | Se pode ver outras demandas, quem pode atribuir/reabrir trabalho e como tratar substituições e prestadores. |
| Aprovador do cliente | Áudio 2, 00:00–00:26; 00:27–00:53 | O cliente deve abrir materiais, aprovar ou pedir alteração e comentar imagem/vídeo. | Quem convida o aprovador, quantos podem decidir, se há decisão conjunta, acesso a outras demandas e expiração do convite. |

Os mesmos participantes podem cumprir várias funções em uma demanda. O produto precisa registrar separadamente a pessoa autenticada, a organização a que pertence e a função que recebeu naquela demanda. Um nome digitado em uma tarefa não prova identidade nem concede permissão.

## Modelo de acesso a validar

Autenticação responde **quem entrou**. Autorização responde **o que essa pessoa pode fazer em qual objeto**. Entrar na conta, pertencer à Mix7 ou a um cliente e receber uma tarefa são relações distintas.

Proposta para detalhamento, não decisão técnica:

- **Conta:** identidade única da pessoa; não contém sozinha todas as permissões.
- **Vínculo de organização:** participação na equipe Mix7 ou em uma organização cliente. Um cliente só recebe demandas e arquivos explicitamente compartilhados com ele.
- **Papel amplo:** capacidades de equipe, gestão ou cliente. Administração técnica separada precisa ser confirmada; os áudios não identificam um administrador do sistema.
- **Função por demanda:** solicitante/briefing, responsável pela gestão, executor, revisor interno, aprovador do cliente ou responsável pela entrega e conclusão. A função é atribuída no contexto do trabalho e pode mudar sem mudar a identidade da pessoa.
- **Permissão por ação e escopo:** conferir no servidor cada leitura e alteração de demanda, comentário, arquivo, versão, decisão, tarefa e usuário. Um filtro por profissional não protege dados.

Antes de definir uma matriz, a Mix7 precisa confirmar quem pode convidar, atribuir e revogar usuários; se a mesma pessoa participa de clientes diferentes; o limite de visibilidade de cliente e profissional; quem acessa notas internas; como convites expiram; e o que ocorre com autoria e histórico quando uma conta é desativada.

## Caminhos técnicos pesquisados

São opções para avaliar depois da escolha de stack. A existência de funções no produto não prova configuração correta nem isolamento seguro para a Mix7.

| Caminho | O que oferece segundo documentação oficial | Implicação para Mix7 |
| --- | --- | --- |
| Autenticação do próprio framework (ex.: Laravel) | Os starter kits do Laravel oferecem cadastro, login, recuperação de senha e verificação de e-mail; o kit inclui opções configuráveis, como 2FA. O framework distingue autenticação de autorização por políticas. | Menos serviços para operar se a aplicação final for um monólito Laravel. Permissões por cliente/demanda, convites, auditoria e isolamento ainda são responsabilidade da aplicação e precisam de testes no servidor. |
| Provedor de identidade separado (Keycloak ou authentik) | Keycloak documenta usuários, grupos, papéis e realms, e mapeamentos de papéis para clientes de aplicação. authentik documenta integração OAuth 2.0/OIDC, grupos, papéis e vínculos de acesso à aplicação. | Centraliza identidade e pode atender SSO futuro, mas acrescenta outro serviço para configurar, monitorar, atualizar e recuperar. O vínculo que permite entrar na aplicação não substitui a autorização para cada demanda e arquivo; a aplicação Mix7 ainda precisa aplicar e testar esse escopo. |
| Backend integrado com Auth e políticas de banco (Supabase) | Supabase Auth integra identidade com PostgreSQL; Row Level Security aplica políticas por linha. A documentação exige combinar privilégios SQL com políticas e manter chaves que ignoram RLS somente no servidor. | Pode concentrar autenticação e isolamento no banco, se a arquitetura usar Supabase. Cada tabela e caminho de acesso precisa ser protegido e testado; uma tabela exposta sem política adequada pode vazar dados entre clientes. Há dependência operacional/comercial do serviço ou custo de auto-hospedagem. |

Nenhum caminho foi escolhido. Comparar somente depois de validar se a primeira implantação será um monólito ou serviços separados, número de clientes externos, necessidade real de SSO, capacidade de operação e custo. Requisitos funcionais e testes de autorização permanecem iguais qualquer que seja o provedor.

## Contas reais e testes obrigatórios

Não criar contas a partir de nomes fictícios do protótipo ou dos áudios. O provisionamento real exige uma lista fornecida e confirmada pela Mix7 com nome, e-mail de trabalho, organização/cliente, papel aprovado e pessoa autorizadora do convite. Senhas não devem ser compartilhadas em texto ou armazenadas pela plataforma; confirmar política de convite, recuperação, MFA, sessões e desligamento antes de ativar usuários.

Antes de usar dados reais, provar que:

1. um aprovador do Cliente A não consegue abrir, listar ou baixar demandas e arquivos do Cliente B, inclusive por URL direta;
2. uma nota interna não aparece para o cliente;
3. uma pessoa profissional não consegue acessar uma demanda só por conhecer seu identificador, salvo se a matriz de acesso permitir;
4. retirar o vínculo de uma pessoa remove acesso futuro e registra o autor/data da mudança sem apagar a autoria histórica;
5. aceitar um convite cria o papel e o escopo autorizados, e links inválidos, expirados ou já usados não concedem acesso;
6. somente pessoas explicitamente autorizadas podem convidar usuários ou alterar papéis de alto privilégio.

## Fontes oficiais consultadas em 25/09/2026

- Laravel, [starter kits e recursos de autenticação](https://laravel.com/framework/docs/starter-kits) e [autenticação, incluindo a distinção entre autenticação e autorização](https://laravel.com/framework/docs/13.x/authentication).
- Keycloak, [guia de administração do servidor: usuários, realms, grupos e papéis](https://www.keycloak.org/docs/latest/server_admin/) e [serviços de autorização](https://www.keycloak.org/docs/latest/authorization_services/index.html).
- authentik, [provedor OAuth 2.0/OIDC](https://docs.goauthentik.io/add-secure-apps/providers/oauth2), [vínculos que controlam acesso à aplicação](https://docs.goauthentik.io/add-secure-apps/applications/manage_apps), [grupos e herança de papéis](https://docs.goauthentik.io/users-sources/groups/manage_groups/) e [modelo de permissões e papéis do próprio authentik](https://docs.goauthentik.io/users-sources/access-control/permissions/).
- Supabase, [arquitetura Auth](https://supabase.com/docs/guides/auth/architecture) e [Row Level Security, privilégios e políticas](https://supabase.com/docs/guides/database/postgres/row-level-security).

Esta consulta documental não verifica segurança, custo total, residência de dados, contrato ou adequação operacional. As páginas podem mudar; registrar versão e data em qualquer prova futura.
