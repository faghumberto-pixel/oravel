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
