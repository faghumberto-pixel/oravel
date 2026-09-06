# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Oravel is a multi-tenant SaaS Laravel 12 app for maintenance/fleet management (checklists, maintenance orders, assets, parts requests, contracts, accounts payable, etc.), built on Filament 3. Backend and admin UI are almost entirely server-rendered through Filament Resources/Pages/Widgets plus a handful of Livewire components (`app/Livewire`) — there is no SPA frontend; Vite/Tailwind/Alpine only style the auth pages and Filament shell.

## Commands

```bash
# Install & local setup
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

# Run the full dev stack (server + queue listener + log tailer + vite), one command
composer dev

# Run tests
php artisan test                       # full suite
php artisan test --filter=TestName     # single test
php artisan test tests/Feature/Auth/AuthenticationTest.php

# Lint / format (Pint, Laravel's PHP-CS-Fixer wrapper)
vendor/bin/pint
vendor/bin/pint --test    # check only, no changes

# Frontend build
npm run dev      # vite dev server
npm run build     # production build

# Filament housekeeping (run after adding/editing Resources)
php artisan filament:upgrade
```

Tests run against an in-memory SQLite DB (`phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) with sync queue/array cache/session — no external services needed to run the suite.

## Architecture

### Two Filament panels
- **`admin` panel** (`/admin`, `AdminPanelProvider`) — the tenant-facing app. Resources/Pages/Widgets are auto-discovered from `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets`.
- **`central` panel** (`/central`, `CentralPanelProvider`) — the SaaS operator/super-admin panel (plans, revenue, cross-tenant management). Resources live in `app/Filament/Central/Resources`; only usable by super admins.

Filament `Resource` classes for the `admin` panel extend `App\Filament\Resources\BaseResource` (not Filament's `Resource` directly).

### Multi-tenancy — this is the most safety-critical part of the codebase
Tenant isolation is enforced at the Eloquent layer, **not** just in controllers/policies:
- `App\Models\Concerns\BelongsToTenant` is the real trait: it adds a global scope filtering by `tenant_id` for the logged-in user, and auto-sets `tenant_id` on `creating()`. Super admins (see below) bypass the scope. Console/CLI/no-auth contexts are unscoped.
- `App\Models\Traits\BelongsToTenant` is a **compatibility shim** that just `use`s the Concerns trait — some older models reference this path. Don't add new logic there; put it in `Concerns\BelongsToTenant`.
- `App\Models\Scopes\TenantScope` is a separate, older `Scope` class implementation (role-based via `hasRole('admin')` rather than `isSuperAdmin()`, and console-aware). Check which mechanism a given model actually uses before assuming behavior — they are not equivalent (e.g. different admin bypass check).
- **Every tenant-owned model must use one of these traits.** Run `php artisan tenant:audit-models` to list which `App\Models\*` classes are missing tenant scoping — this is the standing regression check after touching models. A related command `tenant:isolation-audit` (`TenantIsolationAudit`) also exists.
- "Super admin" is a fixed allowlist of emails from `config('oravel.super_admins')` (populated by the `SUPER_ADMINS` env var), checked via `User::isSuperAdmin()`. It is intentionally **not** role- or domain-based and not editable from the UI — never re-derive super-admin status from roles/permissions.
- Models implementing `App\Models\Contracts\FiltersByTechnician` get an additional scope restricting rows to the logged-in technician's own records (used by `TenantScope`, e.g. `Asset`).

When adding a new tenant-owned model: give it a `tenant_id` column + the `BelongsToTenant` concern, and re-run the audit command.

### SaaS module registry — the real source of truth for plan/permission gating
`App\Support\SaaSRegistry::modules()` auto-discovers every `App\Models\*` class that `use`s `App\Models\Concerns\HasSaaSMetadata` and declares `$saasFeatureKey` (e.g. `tabela_clients`), `$saasPermissionSlug` (e.g. `cliente`, used to build permission names like `ler_cliente`/`criar_cliente`), and `$saasModuleLabel`. This is genuinely the single live source consulted by:
- `App\Policies\AbstractPolicy` — commercial gate (`Tenant::hasFeature($saasFeatureKey)`) + individual permission check (`{ler|criar|editar|excluir}_{saasPermissionSlug}`).
- `App\Filament\Resources\RoleResource` — builds one tab per module in the "Perfis de Acesso" form.
- `App\Models\Plan::getAvailableFeaturesOptions()` — feeds the "Tabelas e Recursos" checklist on `PlanResource`/`TenantResource` in the `central` panel, so the SaaS operator can gate each module per plan or per tenant.

**To add a new tenant-owned table/resource and have it show up everywhere automatically** (Central plan screen, RoleResource permission tabs, AbstractPolicy gating): add `use HasSaaSMetadata;` to the model plus the three static properties above. No other file needs editing. This was a real gap until 2026-07 (several models with real Filament Resources, e.g. `Asset`, `Supplier`, had no SaaS metadata and were invisible to the Central plan screen) — as of a 2026-07-04 audit (`tenant:audit-saas-metadata`), every model backing an admin-panel Resource has it applied correctly except `Role` (see caveat below).

**MANDATORY for every new module/table**: applying `HasSaaSMetadata` (with all three properties actually filled in) is part of a feature's definition of "done" — not an optional follow-up step. Before considering any new tenant-owned model/Resource complete, run `php artisan tenant:audit-saas-metadata` and confirm it reports zero gaps. This command scans every Resource under `app/Filament/Resources` and flags any whose `$model` lacks the trait (or has the trait with a blank `saasFeatureKey`/`saasPermissionSlug`/`saasModuleLabel`).

**Known caveat (2026-07-04, deferred on purpose, not a bug to copy):** `RoleResource.php` imports `Spatie\Permission\Models\Role` for its `$model`, not `App\Models\Role` — a different PHP class from Filament's point of view, even though `App\Models\Role extends` it. Because of this, `RolePolicy` (a hand-rolled policy that does **not** extend `AbstractPolicy`) never consults `SaaSRegistry`/`Tenant::hasFeature()` at all — the "Perfis de Acesso" module is not actually gated by plan today, despite `App\Models\Role` declaring `HasSaaSMetadata`. Don't use `RolePolicy` as a policy pattern to copy; it predates and doesn't follow the `AbstractPolicy` convention described above.

**Every model needs its own named `App\Policies\{Model}Policy` extends `AbstractPolicy` (empty body)**, even though all logic is inherited. `viewAny`/`create` checks call the policy without a `$record`, and Laravel's `Gate::callPolicyMethod()` strips the class-string argument in that case (documented framework behavior, assumes one policy = one model) — so a policy *shared* across models (`App\Policies\DynamicPolicy`, the fallback from `Gate::guessPolicyNamesUsing()` in `AppServiceProvider`) can't identify which model it's authorizing and silently misbehaves for non-admin users with only granular permissions (tenant admins don't hit this because `isAdmin()` bypasses the permission check entirely). A dedicated named subclass fixes it because `AbstractPolicy::resolveModelClass()` guesses the model from the policy's own class name as a fallback.

**(Removed 2026-07-26)** There used to be a second, unrelated, dead feature-gating mechanism here (`App\Enums\Feature` + a `#[BelongsToFeature]` attribute + a never-registered middleware + a discovery service, plus orphaned `ResourcePolicy`/`ResourceAccessPolicy`/`SaaSResourcePolicy` classes and the `AutoMarkResources`/`AutoMarkResourcePages` scaffolding commands that generated the attribute in the first place). None of it was ever wired up or read by anything. Deleted wholesale rather than left as a trap — don't recreate this path; `SaaSRegistry` + `HasSaaSMetadata` (above) is the one real mechanism.

### Permissions
Uses `spatie/laravel-permission`. `User::isAdmin()` checks for the `admin` role via `model_has_roles`/`roles` tables directly (bypassing spatie's cache-heavy helpers) rather than `hasRole()`. A user with the tenant-scoped `admin` role gets a full bypass of the individual-permission check in `AbstractPolicy` (still subject to the plan's feature gate) — so a tenant admin does not need explicit Permissions granted to see everything their plan includes. The granular `{ler|criar|editar|excluir}_{saasPermissionSlug}` `Permission` rows aren't pre-seeded anywhere — `App\Filament\Resources\RoleResource` creates them on the fly (`Permission::firstOrCreate()`) the first time anyone opens "Perfis de Acesso" for a tenant, scoped to whatever modules that tenant's plan currently includes; Spatie's Gate registration only recognizes `Permission` rows that already exist in the DB, so a permission check for a brand-new module before that first page load just resolves to a clean deny, not an error. `DiagnosePermissions`, `DebugPermissions`, `DebugPolicyFlow`, `TestPermissionSystem` commands exist for inspecting/repairing the permission state — reach for these instead of hand-writing debug scripts when something looks like a permissions/policy bug. **(`SyncPermissions` / `permissions:sync` was removed 2026-07-26** — it was a stale hardcoded list of 8 models unrelated to `SaaSRegistry`, and it created global non-tenant-scoped Spatie roles by name, which is exactly the footgun the "EquipmentReplacementObserver::notifyRole()" pattern elsewhere in the code exists to avoid. Don't recreate it.)

### Other services worth knowing
- `App\Services\TenantProvisioner::provision(Tenant $tenant, array $adminData)` — creates the tenant's first user with the tenant-scoped `admin` role, called from `CreateTenant::afterCreate()` (`central` panel) right after a `Tenant` is created. `TenantResource`'s create form collects `admin_name`/`admin_email`/`admin_password` (stripped out before the `Tenant` itself is saved, since they aren't tenant columns).
- `App\Services\AsaasService` — integration with Asaas (Brazilian payments provider) for billing; the `/api/webhooks/asaas` route is intentionally commented out in `routes/api.php` because `WebhookAsaasController` doesn't exist yet — see the TODO comment there before wiring it up.
- `App\Services\GovernanceService`, `MaintenanceService`, `AssetService` — domain logic for maintenance orders/assets kept out of Filament classes.

## Repo hygiene notes

- The repo root and several `app/` subdirectories accumulate stray `*.bak.<timestamp>` files (e.g. `app/Filament/Pages/AgendaTecnico.php.bak.*`) and one-off empty/junk files from prior shell mishaps. These are not part of the app — don't `require`/reference them, and don't assume a `.bak` file reflects current behavior. Prefer `git log`/`git diff` over reading `.bak` files to understand history.
- `MEGA_SCRIPT.sh` and `mark_remaining.sh` are one-off code-generation scripts used to scaffold the feature-flag system; they are not part of the normal dev workflow.
- `deploy.sh` pushes to `origin` and SSHes into the production host (`app.oravel.com.br`) to pull, backup, and migrate. Treat it as a real production deploy action, not a dev tool — don't run it without explicit user instruction. `PRE_DEPLOY_CHECKLIST.md` documents the manual checklist expected before running it.
- **⚠️ `public/hot` ghost file = every page silently renders unstyled/broken.** This file is created automatically whenever `npm run dev`/`vite`/`composer dev` runs, and tells Laravel's `@vite()` to load ALL CSS/JS from the Vite dev server (`http://127.0.0.1:5173`) instead of the already-compiled `public/build/assets/*`. If that dev server isn't actually running (e.g. the process was killed with `kill -9`/`pkill` instead of a clean `Ctrl+C`, or the terminal was closed abruptly), every asset request silently fails (`curl` shows `HTTP 000`, not a normal error) and the page loses ALL styling — inputs render bare, icons/SVGs render at their huge intrinsic size instead of the intended `h-N w-N`, which can look like a bizarre/broken layout rather than the obvious "missing CSS". The HTML itself looks perfectly correct in `curl`, so this is easy to mistake for a Blade/Livewire/Alpine bug and burn hours rewriting unrelated code (real incident: 2026-09-06, ~2h lost on `/admin/login` before finding this). **If ANY page looks broken/unstyled in DEV — especially if it persists in an incognito window or a different browser (i.e. it's not a caching issue) — check `ls -la public/hot` FIRST, before touching any view/CSS/component code.** Fix is one line: `rm public/hot && php artisan view:clear`.
