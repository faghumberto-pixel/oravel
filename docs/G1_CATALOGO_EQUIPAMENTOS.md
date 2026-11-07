# G1 — Catálogo de Equipamentos (Fase 1: base de dados e instalador)

Fonte da verdade = banco, por tenant. Os YAML em `database/seeders/catalog/` são só o **template inicial**.

- **Grupo = `asset_categories`** (estendida; `is_active` = "ativo"; agora com soft delete). Sem tabela paralela.
- **Tabelas novas:** `catalogo_itens` (biblioteca), `catalogo_atributos`, `catalogo_requisitos` (liga item↔grupo com efeito IMP/ALR/DOC/INSP/ACESS),
  `catalogo_preventivas`, `catalogo_operadores`, `catalogo_pecas_criticas` (vínculo com `materials`: a OS consome Material),
  `catalogo_sla`, `catalogo_regras`, `catalogo_instalacoes`.
- **Instalar:** `php artisan catalogo:instalar {tenant} [--dry-run]`. Idempotente por slug; nunca sobrescreve; item/grupo arquivado pelo
  tenant não volta; grupo já instalado é pulado inteiro (novidades só pela atualização com diff, fase 6); categoria do tenant com o mesmo
  slug vira "conflito" (não é adotada nem duplicada).
- **Provisionamento:** instala automaticamente só se o contrato tiver `tabela_catalogo_equipamentos` ligada (desligada por padrão:
  `saas:sync-modules` grava `false` em chave nova).
- **SaaSRegistry só lê `app/Models/*.php` (sem subpastas):** por isso os models do catálogo ficam planos em `app/Models`.

## ⛔ Pendência bloqueante para ligar a chave em cliente real
As normas citadas nos YAML (NR-10/11/12/13/18/35, CTB/ANTT) são **hipóteses**. Itens que dependem de norma têm `validar_norma: true`.
**Responsável técnico a definir; validar todas antes de ligar `tabela_catalogo_equipamentos` em qualquer contrato real.**

## Testes
`tests/Feature/G1CatalogoInstaladorTest.php` recusa rodar se o nome do banco não contiver "test" (o `config/database.php` fixa `pgsql`,
então o sqlite do `phpunit.xml` NÃO vale). Rodar só em banco descartável: `DB_DATABASE=oravel_test_g1 php artisan test --filter=G1Catalogo`.

## Fase 3 — Motor de aptidão (`AssetFitnessService`)
- **Status:** `apto` / `apto_restricao` / `inapto` / `cadastro_incompleto` (ativo sem grupo ou com grupo arquivado). Cache em `assets.aptidao_*`.
- **Evidências:** tabela `catalogo_cumprimentos` (documento, inspeção, checklist, acessório, preventiva) com **snapshot imutável** da regra da época
  (`valido_ate` calculado uma vez, snapshot write-once) + NR-13 já existente (`Nr13Inspection`) + medidores `horimetro_atual`/`odometro_atual`.
- **Evidência ausente ≠ vencida:** padrão = Apto com restrição (`parametros.sem_registro`: `inapto` | `restricao` | `ignorar`), para ligar a chave sem derrubar a frota.
- **Preventiva:** em dia / até `tolerancia_pct` (padrão 10%, editável) acima do intervalo = restrição / acima = Inapto; dimensão por medidor e por dias (`primeiro` | `ambos`).
- **Regras em dados:** condições `sem_cumprimento` e `atributo_vazio`; efeitos `restricao`, `inapto`, `bloqueia_alocacao` (flag, sem mudar o status). Strategy `nr13`.
- **Gatilhos** (só com a chave do contrato ligada; senão custam uma consulta): ativo criado/trocou de grupo/horímetro/km/atributos, leitura de horímetro e odômetro,
  mudança de status da OS, checklist de frota concluído, NR-13, documento do ativo, cumprimento. **Job diário** 02:30 (`aptidao:recalcular` para rodar manual).
  Mudou a regra do grupo → recálculo do grupo em fila. **Prévia de impacto** na tela de edição do grupo (nada é salvo).
- **Fora desta fase (fases 4–7):** formulário de campos técnicos no cadastro do ativo, operador, peças críticas, sobrescrita por modelo/ativo,
  liberação excepcional, bloqueio de despacho, OS automática em IMP reprovado, SLA por grupo. Nesta fase `os_a_gerar` só é calculado.
- Em PROD a fila é `sync` (sem worker): os jobs rodam inline; o recálculo de um grupo grande acontece dentro da requisição de salvar.

## Fase 4 — Grupos do MVP e cadastro do ativo por grupo
- **Template:** compressor, gerador, empilhadeira, plataforma elevatória, munk e guindaste (50 itens na biblioteca). **Veículos leves, caminhão e reboque ficam na fase 8**
  (dependem da unificação com `FrotaModeloChecklist`). Todo item que cita norma tem `validar_norma: true` (teste garante); operadores e a regra do guindaste também.
- **Munk (chassi + guindaste):** medidor `ambos`. Km do chassi = `assets.odometro_atual`; horas do guindaste = `assets.horimetro_atual` (os dois já ficam no `Asset`); duas preventivas,
  uma por medidor. **Os intervalos do munk e do guindaste são PLACEHOLDER** (`parametros.validar_manual`): a especificação não os define; usar o manual do fabricante.
- **Validade de documento/inspeção** sem validade padrão (laudos, CRLV, seguro, plano de içamento): informada no registro do cumprimento (`valido_ate`).
  Itens "quando aplicável" (ART) usam `sem_registro: ignorar`.
- **Guindaste:** regra em dados `bloqueia_alocacao` enquanto não houver plano de içamento válido (`sem_cumprimento: plano-icamento`); o status não muda, só a flag.
- **Cadastro do ativo:** seção "Campos técnicos do grupo" mostra só os campos do grupo escolhido (gravados em `assets.atributos_tecnicos`); obrigatório vazio = aviso, nunca bloqueia.
  Coluna e filtro "Aptidão" na lista e aba "Aptidão e cumprimentos" no ativo (registrar cumprimento; histórico sem editar/excluir). Widget "Aptidão da frota" com alerta de cadastro incompleto.
  Tudo inerte com a chave do contrato desligada.
