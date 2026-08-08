<?php

namespace App\Providers\Filament;

use App\Filament\Central\Pages\DashboardCrm;
use App\Filament\Central\Pages\DashboardSaaS;
use App\Filament\Central\Pages\DashboardVisitantes;
use App\Filament\Central\Pages\FunilVendas;
use App\Filament\Central\Pages\Kanban;
use App\Filament\Central\Pages\Programacao;
use App\Filament\Central\Resources\PlanResource;
use App\Filament\Central\Widgets\AcquisitionChannelChart;
use App\Filament\Central\Widgets\ArrChart;
use App\Filament\Central\Widgets\ChurnChart;
use App\Filament\Central\Widgets\EngagementChart;
use App\Filament\Central\Widgets\LeadsBySegmentChart;
use App\Filament\Central\Widgets\LeadsBySourceChart;
use App\Filament\Central\Widgets\LeadsCreatedTrendChart;
use App\Filament\Central\Widgets\ProspectingMapWidget;
use App\Filament\Central\Widgets\RevenueChart;
use App\Filament\Central\Widgets\SaaSStatsOverview;
use App\Filament\Central\Widgets\SalesCrmStatsWidget;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use App\Filament\Central\Widgets\SiteVisitsStatsOverview;
use App\Filament\Central\Widgets\TopReferrersChart;
use App\Filament\Central\Widgets\WonLostTrendChart;
use App\Filament\Resources\RoleResource;
use App\Http\Middleware\TrackSiteVisit;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
// Importações corretas dos seus arquivos
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Jeffgreco13\FilamentBreezy\Pages\MyProfilePage;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('central')
            ->path('central')
            ->login()
            ->colors([
                'primary' => Color::Blue,
                // Grafite azulado fixo (nao preto/branco padrao do
                // Filament), pedido explicito do usuario pra ter uma
                // referencia visual clara de "estou no Central, nao no
                // Admin". Ate 2026-08-04 os tons 800/900 eram IDENTICOS
                // (30,41,59 duplicado) -- exatamente o motivo do usuario ver
                // "tudo cinza, topbar/sidebar/conteudo iguais": o Filament
                // usa 900 pra sidebar/topbar e 800 pra cards internos, sem
                // diferenca entre eles a hierarquia de profundidade some.
                // Agora sao 3 degraus reais e distintos: 950 (fundo, mais
                // escuro) < 900 (sidebar/topbar) < 800 (cards de conteudo,
                // mais claro = "mais perto do usuario"). Resto da escala
                // continua no slate autentico, texto/borda previsiveis.
                'gray' => [
                    50 => '248, 250, 252',
                    100 => '241, 245, 249',
                    200 => '226, 232, 240',
                    300 => '203, 213, 225',
                    400 => '148, 163, 184',
                    500 => '100, 116, 139',
                    600 => '71, 85, 105',
                    700 => '51, 65, 85',
                    800 => '38, 51, 71',
                    900 => '24, 33, 48',
                    950 => '15, 23, 42',
                ],
                // Cores por estágio/segmento do CRM (App\Support\CrmPalette
                // é a fonte única -- mudar lá já reflete aqui e no Kanban/
                // Funil). Registradas aqui pra virarem nomes usáveis direto
                // em Tables\Columns\*::color('crmBlue') etc, em vez de ficar
                // preso aos 6 nomes semânticos padrão do Filament.
                'crmSlate' => Color::Slate,
                'crmBlue' => Color::Blue,
                'crmIndigo' => Color::Indigo,
                'crmPurple' => Color::Purple,
                'crmOrange' => Color::Orange,
                'crmEmerald' => Color::Emerald,
                'crmPink' => Color::Pink,
                'crmCyan' => Color::Cyan,
                'crmAmber' => Color::Amber,
            ])
            // Tema escuro fixo (nao alternavel) -- reforca a mesma
            // referencia visual acima.
            ->darkMode(true, isForced: true)
            ->viteTheme('resources/css/filament/central/theme.css')
            // Central nunca teve isso (so' o Admin) -- caia no texto padrao
            // do Filament em vez do wordmark "Oravel" com o "r" laranja.
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.png'))
            ->resources([
                PlanResource::class,
                RoleResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\\Filament\\Central\\Resources')
            ->pages([
                // Dashboard padrao do Filament trocado por 2 paginas
                // separadas -- antes misturava metrica de SaaS com metrica
                // comercial numa pagina so'.
                DashboardSaaS::class,
                DashboardVisitantes::class,
                DashboardCrm::class,
                FunilVendas::class,
                Kanban::class,
                Programacao::class,
            ])
            ->widgets([
                // Registro aqui (mesmo os que so' aparecem via getWidgets()
                // das paginas de Dashboard, nao no array literal de nenhum
                // ->widgets() de outra pagina) e' o que da' a eles um alias
                // Livewire correto -- sem isso e' o mesmo bug do
                // SalesAgendaWidget (ComponentNotFoundException disfarcada
                // de 419 em todo clique).
                SaaSStatsOverview::class,
                RevenueChart::class,
                ArrChart::class,
                ChurnChart::class,
                SalesCrmStatsWidget::class,
                SalesLeadMapWidget::class,
                ProspectingMapWidget::class,
                LeadsCreatedTrendChart::class,
                WonLostTrendChart::class,
                LeadsBySegmentChart::class,
                LeadsBySourceChart::class,
                SiteVisitsStatsOverview::class,
                EngagementChart::class,
                TopReferrersChart::class,
                AcquisitionChannelChart::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('8s')
            ->renderHook(
                // Central nunca teve esse hook (so' o admin tinha) -- pagina
                // restaurada do bfcache do navegador carrega com token CSRF
                // desatualizado, e o primeiro POST /livewire/update (ex.:
                // qualquer acao na Programacao) da 419. Mesmo fix ja
                // aplicado no AdminPanelProvider antes.
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.bfcache-reload'),
            )
            ->renderHook(
                // Relogio ao vivo (dia/hora/mes) + total de compromissos de
                // hoje no topbar -- pedido explicito do usuario.
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.central.topbar-clock'),
            )
            ->renderHook(
                // Bloco de perfil fixo no rodape da sidebar (avatar+nome+
                // cargo+dropdown) -- design system "Convertico" pedido pelo
                // usuario 2026-07-28. Ver resources/views/filament/central/
                // sidebar-footer.blade.php.
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.central.sidebar-footer'),
            )
            ->plugin(
                // SalesAgendaWidget (Programacao) usa esse pacote -- sem
                // registrar aqui, a tela quebrava com 500 ("Plugin
                // [filament-fullcalendar] is not registered for panel
                // [central]"), achado em PROD depois do deploy.
                FilamentFullCalendarPlugin::make()
                    ->selectable(false)
                    ->editable()
                    ->locale('pt-br')
            )
            ->plugin(
                // central so e acessado por super admins (ver CLAUDE.md) -- forcar 2FA
                // aqui equivale a forcar 2FA so pra eles, sem logica extra de role.
                BreezyCore::make()
                    ->myProfile()
                    ->enableTwoFactorAuthentication(force: true)
            )
            ->userMenuItems([
                // chave != 'account' de proposito -- ver comentario em AdminPanelProvider
                'my_profile_link' => MenuItem::make()
                    ->label('Minha Conta')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => MyProfilePage::getUrl(panel: 'central')),
            ])
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
                // Painéis Filament não passam pelo grupo "web" global
                // (bootstrap/app.php) -- ver mesmo comentário em
                // AdminPanelProvider.
                TrackSiteVisit::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
