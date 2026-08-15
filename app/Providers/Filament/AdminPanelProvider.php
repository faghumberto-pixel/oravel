<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ApontamentoHorimetro;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\LogUserActivity;
use App\Http\Middleware\TrackSiteVisit;
use App\Models\Asset;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Jeffgreco13\FilamentBreezy\Pages\MyProfilePage;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->topNavigation()
            ->colors([
                // Paleta do artefato "Central de Artefatos" (2026-07-25):
                // laranja de destaque + neutros quentes (stone) no lugar do
                // slate frio, pra bater com o fundo creme/bordas do tema novo.
                'primary' => Color::hex('#ea580c'),
                'gray' => Color::Stone,
                // So' pro status "quarentena" do Ativo (Asset::statusColor()) --
                // os 6 nomes padrao do Filament nao cobrem os 7 status reais.
                'purple' => Color::Purple,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('1.25rem')
            ->favicon(asset('favicon.png').'?v=5')
            ->navigationGroups([
                NavigationGroup::make('Manutenção'),
                NavigationGroup::make('Logística'),
                NavigationGroup::make('Ativos e Materiais'),
                NavigationGroup::make('Equipe'),
                NavigationGroup::make('Departamento Pessoal'),
                NavigationGroup::make('Comercial'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Relatórios'),
                NavigationGroup::make('Configurações'),
            ])
            ->navigationItems([
                // Tela dedicada de registro de horimetro (offline-first, JS
                // puro) -- rota comum (HourMeterOfflineController), nao uma
                // Filament Page, entao entra no menu via NavigationItem em
                // vez de discoverPages(). Mesma visibilidade de "Ativos"
                // (viewAny Asset), nao a permissao granular restrita que
                // ApontamentoHorimetro (pagina desktop) exige -- essa aqui
                // e' pensada pro tecnico comum, nao so pra quem tem
                // 'criar_apontamento_horimetro'.
                NavigationItem::make('Registrar Horímetro')
                    ->icon('heroicon-o-clock')
                    ->group('Manutenção')
                    ->sort(-8)
                    ->url(fn () => route('hour-meter.offline'))
                    ->visible(fn () => (bool) auth()->user()?->can('viewAny', Asset::class)),

                // Mesmo padrão -- so' aparece pra quem tem Employee vinculado
                // ao próprio User (TimeClockOfflineController::show() aborta
                // 404 sem isso, o link nem precisa aparecer nesse caso).
                NavigationItem::make('Bater Ponto')
                    ->icon('heroicon-o-finger-print')
                    ->group('Departamento Pessoal')
                    ->sort(-8)
                    ->url(fn () => route('time-clock.offline'))
                    ->visible(fn () => \App\Models\Employee::where('user_id', auth()->id())->exists()),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.topbar-brand-and-ticker'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.topbar-tenant-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.help-icon'),
            )
            ->renderHook(
                // Busca de tela/menu -- o global search nativo do Filament so'
                // aparece se algum Resource declarar getGloballySearchableAttributes()
                // (nenhum declara hoje), entao a lupa nativa nunca renderiza. Ver
                // App\Livewire\ScreenSearch.
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.screen-search-mount'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.acting-tenant-banner'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.login-background'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.brand-header-background'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.bfcache-reload'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.chat-widget-mount'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.keyboard-shortcuts'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.oravel-gauge-chart-plugin'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('filament.panel-footer'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->databaseNotifications()
            // 15s -> 8s: unico ajuste de "tempo real" que o usuario pediu pra
            // essa fase -- sininho continua sendo polling, nao push de
            // verdade (decisao explicita, ver plano de Suprimentos/Frota).
            ->databaseNotificationsPolling('8s')
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->selectable(false)
                    ->editable()
                    ->locale('pt-br')
            )
            ->plugin(
                BreezyCore::make()
                    ->myProfile(hasAvatars: true)
                    ->enableTwoFactorAuthentication()
            )
            ->userMenuItems([
                // Chave != 'account' de proposito: BreezyCore::boot() roda depois de
                // panel() e registra o proprio item na chave 'account' (sem label,
                // por isso o menu mostrava soh o nome do usuario) -- usar a mesma
                // chave faria o nosso ser sobrescrito. Isso soma um segundo atalho,
                // visivel e com rotulo, pra mesma pagina.
                'my_profile_link' => MenuItem::make()
                    ->label('Minha Conta')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => MyProfilePage::getUrl()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                DispatchServingFilamentEvent::class,
                // Painéis Filament têm pilha de middleware própria, não passam
                // pelo grupo "web" global (bootstrap/app.php) -- por isso
                // TrackSiteVisit precisa ser registrado aqui também, senão
                // /admin/login e todo o restante do painel nunca gera visita.
                TrackSiteVisit::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                LogUserActivity::class,
            ]);
    }
}
