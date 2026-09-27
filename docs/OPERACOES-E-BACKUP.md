# Operação e cópias de segurança

## O que precisa ser recuperado

O código-fonte e seu histórico ficam no GitHub. Isso não inclui o banco de produção, anexos privados em `storage/app/private`, o arquivo `.env`, a chave `APP_KEY` nem arquivos enviados pelos usuários. Uma recuperação completa precisa reunir versões compatíveis desses itens. Segredos e cópias com dados de clientes não devem entrar no GitHub, Trello ou em capturas.

## Proteção disponível na Hostinger

A documentação pública da Hostinger consultada em 27/09/2026 informa que os planos de hospedagem recebem backup semanal. Backups diários estão incluídos em planos Web Business ou superiores; em Single/Premium, a ativação diária pode exigir compra. O backup manual de todos os arquivos e bancos pelo hPanel é descrito para Business ou superior, uma vez a cada 24 horas. **O plano e os recursos contratados pela Mix7 ainda não foram conferidos**, portanto confirme no hPanel a disponibilidade, o último backup, a próxima execução e a retenção antes da implantação.

Mesmo quando a cópia automática existe, exporte o MariaDB pelo phpMyAdmin antes de uma migração ou mudança de dados. Guarde o arquivo SQL em local privado e protegido, fora da hospedagem, e registre a data e o commit associado. Arquivos privados do Laravel devem ser copiados separadamente por backup de arquivos do hPanel ou transferência autenticada; o SQL sozinho não recupera anexos. Preserve `.env` e `APP_KEY` em um cofre de segredos seguro e separado. Nunca compartilhe esses valores no cartão de trabalho.

## Antes de publicar uma mudança

1. Registre o SHA do commit que será publicado e confirme que ele está no GitHub.
2. Verifique no hPanel que há uma cópia recuperável recente de arquivos e banco. Se o plano oferecer backup manual, crie-o antes da migração.
3. Em **Databases → Management → phpMyAdmin**, selecione apenas o banco da plataforma e use **Export** para baixar uma cópia SQL. Confira o nome do banco e o horário do arquivo.
4. Se a mudança envolve anexos ou armazenamento, obtenha também uma cópia dos arquivos privados Laravel. Não os mova para `public_html`.
5. Execute migrations versionadas e registre o resultado junto do SHA. Não importe um dump em banco compartilhado sem confirmar o banco selecionado.

## Exercício de restauração

Até existir ambiente de teste na hospedagem, use somente dados sintéticos. Para o exercício compartilhado futuro:

1. Crie no hPanel um banco e uma área de teste separados da aplicação de produção.
2. Importe o SQL nessa base vazia pelo phpMyAdmin. Se o arquivo exceder o limite da interface, siga o método SSH oficial somente se o plano permitir.
3. Restaure os arquivos privados correspondentes em uma pasta não pública e configure um `.env` próprio. Use a `APP_KEY` correspondente ao backup somente no ambiente protegido, pois ela pode ser necessária para ler dados criptografados.
4. Aponte a aplicação de teste exclusivamente para a base e os arquivos de teste. Confira migrations, login de teste, dados esperados e abertura de um anexo sintético.
5. Registre data, origem, commit, duração, verificações e falhas. Apague a cópia de teste conforme o procedimento de retenção aprovado; não use teste de restauração para substituir a produção.

Um teste local com SQLite não comprova compatibilidade nem restauração do MariaDB da Hostinger. A etapa só será considerada validada quando a cópia do banco, arquivos e configuração for recuperada em ambiente isolado compatível, sem escrita na produção.

## Recuperação de incidente

Antes de restaurar produção, interrompa temporariamente novas gravações, preserve uma cópia do estado atual e identifique o instante a recuperar. A restauração pode substituir dados posteriores ao ponto escolhido. Confirme com a pessoa responsável pela operação qual backup e qual escopo devem voltar; depois valide login, demandas, arquivos, filas e logs antes de liberar o acesso. Registre impacto e diferenças entre o último backup e o incidente.

O backup semanal informado pela Hostinger pode deixar uma janela de perda de até vários dias; a frequência e o tempo de recuperação aceitáveis para a Mix7 ainda precisam ser definidos. Não prometa continuidade ou perda máxima de dados antes de verificar plano, retenção e um exercício real.

## Fontes oficiais consultadas em 27/09/2026

- [Backups na Hostinger: planos, cópia manual e limites](https://www.hostinger.com/support/2298928-how-to-create-backups-at-hostinger/)
- [Backups diários: disponibilidade por plano](https://www.hostinger.com/support/1665153-how-to-activate-daily-backups-in-hostinger/)
- [Exportar banco pelo phpMyAdmin](https://www.hostinger.com/support/4529011-how-to-export-a-database-with-phpmyadmin-in-hostinger/)
- [Restaurar banco e site pelo hPanel](https://www.hostinger.com/support/1583283-how-to-restore-a-deleted-website-in-hostinger/)

## Pendências para aceitar a operação

- Confirmar plano, frequência e retenção mostradas na conta hPanel da Mix7.
- Definir frequência própria, cópia fora da hospedagem, retenção, responsáveis e RPO/RTO aceitáveis.
- Planejar armazenamento protegido para SQL, anexos e `APP_KEY`.
- Executar e documentar uma restauração completa de teste MariaDB + arquivos em ambiente separado.
- Validar o procedimento real de backup antes de cada release que altere esquema ou dados.
