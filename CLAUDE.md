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

### Feature flags / plan gating
`App\Enums\Feature` enumerates app modules (assets, fleet, contracts, accounts_payable, …). `App\Filament\Attributes\BelongsToFeature` is a PHP attribute used to tag Filament Resources with the feature they belong to; `App\Filament\Middleware\FilterResourcesByFeatures` and `App\Services\FeatureDiscoveryService` use this to gate navigation/resource visibility per tenant plan. Super admins bypass feature filtering.

### Permissions
Uses `spatie/laravel-permission`. `User::isAdmin()` checks for the `admin` role via `model_has_roles`/`roles` tables directly (bypassing spatie's cache-heavy helpers) rather than `hasRole()`. `SyncPermissions`, `DiagnosePermissions`, `DebugPermissions`, `DebugPolicyFlow`, `TestPermissionSystem` commands exist for inspecting/repairing the permission state — reach for these instead of hand-writing debug scripts when something looks like a permissions/policy bug.

### Other services worth knowing
- `App\Services\TenantProvisioner` — provisions a new tenant (used by `tenant:test-provisioning` command).
- `App\Services\AsaasService` — integration with Asaas (Brazilian payments provider) for billing; the `/api/webhooks/asaas` route is intentionally commented out in `routes/api.php` because `WebhookAsaasController` doesn't exist yet — see the TODO comment there before wiring it up.
- `App\Services\GovernanceService`, `MaintenanceService`, `AssetService` — domain logic for maintenance orders/assets kept out of Filament classes.

## Repo hygiene notes

- The repo root and several `app/` subdirectories accumulate stray `*.bak.<timestamp>` files (e.g. `app/Filament/Pages/AgendaTecnico.php.bak.*`) and one-off empty/junk files from prior shell mishaps. These are not part of the app — don't `require`/reference them, and don't assume a `.bak` file reflects current behavior. Prefer `git log`/`git diff` over reading `.bak` files to understand history.
- `MEGA_SCRIPT.sh` and `mark_remaining.sh` are one-off code-generation scripts used to scaffold the feature-flag system; they are not part of the normal dev workflow.
- `deploy.sh` pushes to `origin` and SSHes into the production host (`app.oravel.com.br`) to pull, backup, and migrate. Treat it as a real production deploy action, not a dev tool — don't run it without explicit user instruction. `PRE_DEPLOY_CHECKLIST.md` documents the manual checklist expected before running it.
