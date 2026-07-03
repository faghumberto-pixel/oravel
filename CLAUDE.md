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

**To add a new tenant-owned table/resource and have it show up everywhere automatically** (Central plan screen, RoleResource permission tabs, AbstractPolicy gating): add `use HasSaaSMetadata;` to the model plus the three static properties above. No other file needs editing — this was a real gap until 2026-07 (several models with real Filament Resources, e.g. `Asset`, `Supplier`, had no SaaS metadata and were invisible to the Central plan screen).

**Every model needs its own named `App\Policies\{Model}Policy` extends `AbstractPolicy` (empty body)**, even though all logic is inherited. `viewAny`/`create` checks call the policy without a `$record`, and Laravel's `Gate::callPolicyMethod()` strips the class-string argument in that case (documented framework behavior, assumes one policy = one model) — so a policy *shared* across models (`App\Policies\DynamicPolicy`, the fallback from `Gate::guessPolicyNamesUsing()` in `AppServiceProvider`) can't identify which model it's authorizing and silently misbehaves for non-admin users with only granular permissions (tenant admins don't hit this because `isAdmin()` bypasses the permission check entirely). A dedicated named subclass fixes it because `AbstractPolicy::resolveModelClass()` guesses the model from the policy's own class name as a fallback.

Separately, `App\Enums\Feature` + `App\Filament\Attributes\BelongsToFeature` + `App\Filament\Middleware\FilterResourcesByFeatures` + `App\Services\FeatureDiscoveryService` are an **unrelated, dead** feature-gating mechanism — the attribute is present on most Resources but the middleware that would consume it is never registered in `AdminPanelProvider`. Don't extend this path; it doesn't run.

### Permissions
Uses `spatie/laravel-permission`. `User::isAdmin()` checks for the `admin` role via `model_has_roles`/`roles` tables directly (bypassing spatie's cache-heavy helpers) rather than `hasRole()`. A user with the tenant-scoped `admin` role gets a full bypass of the individual-permission check in `AbstractPolicy` (still subject to the plan's feature gate) — so a tenant admin does not need explicit Permissions granted to see everything their plan includes. `SyncPermissions`, `DiagnosePermissions`, `DebugPermissions`, `DebugPolicyFlow`, `TestPermissionSystem` commands exist for inspecting/repairing the permission state — reach for these instead of hand-writing debug scripts when something looks like a permissions/policy bug.

### Other services worth knowing
- `App\Services\TenantProvisioner::provision(Tenant $tenant, array $adminData)` — creates the tenant's first user with the tenant-scoped `admin` role, called from `CreateTenant::afterCreate()` (`central` panel) right after a `Tenant` is created. `TenantResource`'s create form collects `admin_name`/`admin_email`/`admin_password` (stripped out before the `Tenant` itself is saved, since they aren't tenant columns).
- `App\Services\AsaasService` — integration with Asaas (Brazilian payments provider) for billing; the `/api/webhooks/asaas` route is intentionally commented out in `routes/api.php` because `WebhookAsaasController` doesn't exist yet — see the TODO comment there before wiring it up.
- `App\Services\GovernanceService`, `MaintenanceService`, `AssetService` — domain logic for maintenance orders/assets kept out of Filament classes.

## Repo hygiene notes

- The repo root and several `app/` subdirectories accumulate stray `*.bak.<timestamp>` files (e.g. `app/Filament/Pages/AgendaTecnico.php.bak.*`) and one-off empty/junk files from prior shell mishaps. These are not part of the app — don't `require`/reference them, and don't assume a `.bak` file reflects current behavior. Prefer `git log`/`git diff` over reading `.bak` files to understand history.
- `MEGA_SCRIPT.sh` and `mark_remaining.sh` are one-off code-generation scripts used to scaffold the feature-flag system; they are not part of the normal dev workflow.
- `deploy.sh` pushes to `origin` and SSHes into the production host (`app.oravel.com.br`) to pull, backup, and migrate. Treat it as a real production deploy action, not a dev tool — don't run it without explicit user instruction. `PRE_DEPLOY_CHECKLIST.md` documents the manual checklist expected before running it.
