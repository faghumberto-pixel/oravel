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

## Fase 5 — Consumidores: bloqueio, liberação excepcional, OS automática, campo e painéis
- **Bloqueio de alocação/despacho** (`AptidaoBloqueio`, calcula na hora, só com a chave do contrato ligada): barra **Inapto**, **cadastro incompleto** e **regra especial de bloqueio**
  (ex.: guindaste sem plano de içamento). "Apto com restrição" não bloqueia. Pontos de barragem: mobilização **sem OS** (despacho/agenda da logística), solicitação de locação
  virando `contrato_fechado` e troca de equipamento (checa o substituto antes de criar qualquer movimentação). Mobilização **de OS de manutenção** e desmobilização não são
  barradas (o ativo Inapto precisa ir ao conserto). Operador exigido (`exige_operador`) **ainda não é avaliado**: depende de `EmployeeCertification`/`EquipmentAllocation` e da trigger de NR; fica para fatia própria.
- **Liberação excepcional** (`aptidao_liberacoes`): permissão própria `criar_liberacao_aptidao` (atribuída em Perfis de Acesso; admin do tenant tem o bypass padrão), justificativa
  ≥ 10 caracteres, prazo futuro de no máx. 30 dias, só para ativo **Inapto**, sem duplicar, revogável com justificativa, log com motivos da época. O status **continua Inapto**.
  **Não levanta** bloqueio de regra especial nem cadastro incompleto.
- **OS automática**: item **IMP** reprovado com `gera_os` abre **uma** OS corretiva (`origin = aptidao_auto`); se já há OS aberta para o mesmo item, vincula à mesma. **Fechar a OS não
  aprova o item**: o ativo segue Inapto até um novo checklist OK (reinspeção). Falha ao abrir a OS é logada e nunca derruba o registro do checklist.
- **Campo**: `Checklist do equipamento` (`/admin/checklist-aptidao`, atalho em `config/campo.php`): só itens IMP/ALR do grupo do ativo. Documentos e inspeções (validade/anexo) ficam na tela de gestão.
- **Painéis**: aptidão por grupo (tabela) e principais motivos de Inapto/restrição. Aba do ativo ganha "Liberar excepcionalmente" e "Revogar liberação".

## Fase 6 — Atualização de template (diff item a item) e "restaurar padrão"
- **Tela** Manutenção > Cadastros > "Atualizações do catálogo" (e botão com a contagem na lista de grupos). O diff é calculado na hora (template × catálogo do tenant); nada é aplicado sem confirmação.
- **Classificação por item:** `novo` (item/atributo/etc. que não existe no tenant), `grupo_novo`, `atualizar` (difere do padrão e **não** é personalizado), `personalizado` (difere e o tenant mexeu: **nunca** é alterado por aceite,
  só informado, com "Restaurar ao padrão"), `arquivado` (o que o tenant arquivou **não volta sozinho**), `removido_do_padrao` (saiu do template: **não é apagado** do tenant), `conflito` (categoria do tenant com o mesmo slug).
  `ordem` é cosmética (o tenant reordena) e não conta como diferença.
- **Aceitar** (item a item, atômico): edição sensível (IMP/DOC/INSP rebaixado, intervalo ampliado, obrigatório removido/desativado) exige justificativa e vai ao log (antes/depois/aviso do responsável técnico).
  **Ignorar**: mantém a versão do tenant (vira personalizado) e para de oferecer. **Restaurar padrão** (item ou grupo inteiro): sobrescreve a personalização, **sempre** com justificativa e log; reativa o que estava arquivado/desativado;
  itens que o tenant criou ficam intactos; a ordem escolhida é preservada.
- **Prévia de impacto** na aptidão: simula o aceite/restauração dentro de uma transação **desfeita** (nada é gravado nem logado). Depois de aceitar/restaurar, a aptidão do grupo é recalculada em fila.
- O mapeamento YAML → campos agora é único (`CatalogoTemplate`): instalador e diff não divergem. A coluna `catalogo_instalacoes.diff_pendente` segue reservada e sem uso (o diff é calculado sob demanda).
