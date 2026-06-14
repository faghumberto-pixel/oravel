<?php

namespace App\Providers\Filament;

use App\Livewire\GlobalChat;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Http\Middleware\{Authenticate, DisableBladeIconComponents, DispatchServingFilamentEvent};
use Illuminate\Cookie\Middleware\{AddQueuedCookiesToResponse, EncryptCookies};
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\{AuthenticateSession, StartSession};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(function (): HtmlString {
                $tenant = Auth::user()?->tenant;
                $suffix = filled($tenant?->name) ? ' - ' . e($tenant->name) : '';

                return new HtmlString(
                    'O<span style="color: rgb(var(--primary-500))">R</span>avel' . $suffix
                );
            })
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                function (): string {
                    $user = Auth::user();

                    if (! $user) {
                        return '';
                    }

                    // Deriva a "funcao" exibida.
                    if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                        $role = 'Super Admin';
                    } elseif (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                        $role = 'Administrador';
                    } else {
                        $role = $user->roles->first()?->name ?? 'Funcionario';
                    }

                    return '<span style="display:inline-flex;align-items:center;gap:.4rem;'
                        . 'margin-right:.75rem;padding:.25rem .7rem;font-size:.75rem;font-weight:600;'
                        . 'border-radius:9999px;color:rgb(var(--primary-600));'
                        . 'background:rgb(var(--primary-500) / 0.12);">'
                        . e($user->name)
                        . '<span style="opacity:.55;font-weight:500;">&middot;</span>'
                        . '<span style="font-weight:500;">' . e($role) . '</span>'
                        . '</span>';
                }
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                function (): string {
                    $plan = Auth::user()?->tenant?->plan?->name;

                    if (blank($plan)) {
                        return '';
                    }

                    return '<span style="display:inline-flex;align-items:center;gap:.35rem;'
                        . 'margin-right:.75rem;padding:.25rem .7rem;font-size:.75rem;font-weight:600;'
                        . 'border-radius:9999px;color:rgb(var(--primary-600));'
                        . 'background:rgb(var(--primary-500) / 0.12);">'
                        . 'Plano: ' . e($plan)
                        . '</span>';
                }
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => <<<'CLOCK'
<div id="oravel-clock" style="position:fixed;top:0;left:50%;transform:translateX(-50%);height:4rem;display:flex;align-items:center;font-size:.8rem;font-weight:600;letter-spacing:.02em;color:rgb(var(--gray-400));pointer-events:none;z-index:20;white-space:nowrap;"></div>
<script>
(function () {
  function fmt() {
    var n = new Date();
    var d = n.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
    var t = n.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    return d.charAt(0).toUpperCase() + d.slice(1) + ' - ' + t;
  }
  function tick() { var el = document.getElementById('oravel-clock'); if (el) el.textContent = fmt(); }
  tick();
  if (!window.__oravelClock) { window.__oravelClock = setInterval(tick, 1000); }
})();
</script>
CLOCK
            )
            ->favicon(asset('images/favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->font('Poppins')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Descoberta automática: o Filament encontrará sua página de Gestão automaticamente
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->livewireComponents([
                'global-chat' => GlobalChat::class,
            ]);
    }
}
