# Deploy das Ondas 2 e 3 + teste público de treinamento (preparado em 14/10/2026)

Estado verificado (somente leitura) antes do deploy:
- PROD (`oravel-prod-v2`) está em `a49d06d` = `origin/main`; árvore limpa; **sem migrations pendentes**; cron `schedule:run` ativo; disco 51%.
- O branch `feat/treinamento-cadastros-propostas-receber` tem 13 commits à frente de `origin/main` (inclui todas as Ondas 2 e 3).
- Uma única migration nova: `2026_11_10_100000_create_treinamento_resultados_table` (aditiva).
- Sem mudança de front-end (`public/build` intacto; o teste público usa CSS inline).

## O que o deploy muda para quem usa o sistema
| Área | Mudança |
|---|---|
| Preventiva | Plano só de ativo aparece no celular/impressão/formulário e zera o vencimento ao executar; "próxima previsão" só com intervalo em horas; plano desativado deixa de alertar, abrir OS e bloquear ativo |
| Dashboard PMP | Deixa de dar erro no Postgres (gráfico O.S. por Tipo de Equipamento) |
| Checklist | Item Não Conforme conta como respondido e **abre Pendência** (avisa quem recebe o evento `pendencia_os`); itens PMP não se repetem na aba Vistoria |
| Contratos | Campo **Situação** (Rascunho/Ativo/Encerrado, padrão Ativo); só contrato Ativo trava o ativo (os antigos em Rascunho continuam liberando); avisos novos (serviços em Rascunho; vigência vencida, diário às 08:00 no 1º dia de atraso e a cada 7) |
| Propostas | Vendedor não revisa a própria proposta (admin pode); botão Reabrir para edição; template Padrão aplicado em proposta nova |
| Ativos | Nº Patrimônio único **dentro da empresa** (formulário e API) |
| Novo | Página pública `/treinamento/cadastros-propostas-receber`, tela Central "Resultados de Treinamento", importador com `--refresh-quiz` |

## Decisões suas antes de rodar
1. Contratos em **Rascunho com ativo Locado** em PROD (criados pela tela antes do campo Situação): não faturam. Listar e decidir se viram Ativo (não será feito sem ordem).
2. Quais aulas importar/atualizar em PROD (lista abaixo) e se o curso novo já fica **publicado**.
3. Avisos novos vão para quem estiver em Configurações → Responsáveis pelos Avisos (padrão: papel Comercial ou admin).

## Passos
1. Local: `git status` limpo; `git checkout main && git merge --ff-only feat/treinamento-cadastros-propostas-receber`.
2. `./deploy.sh main` (valida assets, **dá push em main**, backup na VM, pull, composer, `migrate --force`, caches, `saas:sync-modules`, health check). Não rodar de novo só para conferir log.
3. PDF do treinamento (ainda não existe no repositório): copiar para a VM em `/var/www/oravel/public/treinamento/treinamento_cadastros_propostas_receber_oravel.pdf` (dono `www-data`). Sem ele a prova funciona, mas o botão de download fica oculto.
4. Importar a Academia em PROD (na VM, como `www-data`, em `/var/www/oravel`):
   - Curso novo (cria como rascunho; use `--publish-only` para já publicar) e perguntas novas:
     `php artisan academy:import --publish-only="Cadastros, Propostas e Contas a Receber"`
   - Aulas atualizadas, **sem sobrescrever as outras**. Antes, comparar `md5(lessons.body)` de cada uma com o arquivo, para ver se alguém editou na Central:
     `php artisan academy:import --refresh-content --refresh-quiz --only-lessons=modulos__ordens-servico,modulos__manutencao-preventiva,modulos__grupos-checklist,modulos__checklist-inspecao,guias__configurar-preventiva,modulos__contratos`
   - `--refresh-quiz` troca as perguntas dessas aulas pelas do `quiz.json` (atualiza, adiciona e remove as antigas), **mas só remove se ninguém entregou a prova da aula**; senão mantém as antigas e avisa.
   - (Se quiser também as aulas da 3.2: acrescentar `modulos__proposta-comercial,modulos__orcamentos` na lista.)
5. Conferir (sem repetir o deploy): `curl -I https://app.oravel.com.br/treinamento/cadastros-propostas-receber` (200), abrir a Central → Resultados de Treinamento, enviar um teste de verdade e ver o e-mail em contato@oravel.com.br e a linha na Central (depois, apagar a linha de teste direto na base com ordem sua).
6. Link para os clientes: **https://app.oravel.com.br/treinamento/cadastros-propostas-receber**

## Reversão
- Código: `git revert` dos commits e novo deploy, ou restaurar o backup `/var/backups/oravel_backup_*` (código/config, sem uploads).
- Migration: `php artisan migrate:rollback --step=1` remove só `treinamento_resultados` (perde os envios do teste).
- Academia: o import é aditivo, exceto `--refresh-content`/`--refresh-quiz` nas aulas listadas (as edições feitas na Central nessas aulas se perdem; por isso o `md5` antes).
