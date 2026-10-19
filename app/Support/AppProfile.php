<?php

namespace App\Support;

use App\Filament\Pages\PrimeirosPassos;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Onboarding\OnboardingService;

/**
 * Qual dos 3 apps instalaveis (Colaborador, Tecnico, Administrador) um
 * usuario enxerga. Decidido pelo que o perfil de acesso ja permite, nao por
 * um campo novo: administrador/supervisor = admin; quem pode ver Ordens de
 * Servico = tecnico; o resto = colaborador. Mesmo criterio de /dashboard e
 * /admin em routes/web.php.
 */
class AppProfile
{
    public const ADMIN = 'admin';

    public const TECNICO = 'tecnico';

    public const COLABORADOR = 'colaborador';

    public const ALL = [self::ADMIN, self::TECNICO, self::COLABORADOR];

    public static function for(?User $user): string
    {
        if (! $user) {
            return self::COLABORADOR;
        }

        if ($user->isAdmin() || ! empty($user->supervisedDepartmentIds())) {
            return self::ADMIN;
        }

        return $user->can('viewAny', MaintenanceOrder::class) ? self::TECNICO : self::COLABORADOR;
    }

    /** Perfil efetivo ao abrir /app/{perfil}: nunca acima do que o usuario tem. */
    public static function resolve(User $user, string $requested): string
    {
        $own = self::for($user);

        return ($own === self::ADMIN || $requested === $own || $requested === self::COLABORADOR)
            && in_array($requested, self::ALL, true)
            ? $requested
            : $own;
    }

    public static function homeUrl(User $user, string $profile): string
    {
        return match ($profile) {
            self::ADMIN => self::urlDoOnboarding($user) ?? self::urlDoGuiaSemPainel() ?? Pacotes::urlInicial(Tenancy::current() ?? $user->tenant) ?? self::urlDoPainelOuPrimeiraTela(),
            self::TECNICO => route('filament.admin.pages.technician-daily-tasks'),
            default => route('filament.admin.pages.app-colaborador'),
        };
    }

    /** Sem o Painel de Controle no contrato, a página inicial do administrador é o Guia dos módulos (vale antes da tela inicial do pacote). */
    private static function urlDoGuiaSemPainel(): ?string
    {
        return ! \App\Filament\Pages\PainelGestao::canAccess() && \App\Filament\Pages\PrimeirosPassos::canAccess()
            ? \App\Filament\Pages\PrimeirosPassos::getUrl()
            : null;
    }

    /**
     * Painel de Controle quando o contrato o inclui; senão o Guia dos módulos; senão a primeira tela do menu a que o usuário tem acesso (evita mandar o administrador
     * para uma página que dá 403 quando a Central tirou o painel do contrato).
     */
    private static function urlDoPainelOuPrimeiraTela(): string
    {
        if (\App\Filament\Pages\PainelGestao::canAccess()) {
            return route('filament.admin.pages.painel-controle');
        }

        // Sem o painel no contrato, o administrador cai no Guia dos módulos (telas importantes de cada módulo contratado).
        if (\App\Filament\Pages\PrimeirosPassos::canAccess()) {
            return \App\Filament\Pages\PrimeirosPassos::getUrl();
        }

        try {
            foreach (\Filament\Facades\Filament::getNavigation() as $grupo) {
                foreach ($grupo->getItems() as $item) {
                    if ($url = $item->getUrl()) {
                        return $url;
                    }
                }
            }
        } catch (\Throwable) {
            // sem navegação disponível neste contexto: cai no padrão
        }

        return route('filament.admin.pages.painel-controle');
    }

    /** Enquanto o onboarding da solução está pendente, o administrador abre direto nos "Primeiros passos". */
    private static function urlDoOnboarding(User $user): ?string
    {
        $tenant = Tenancy::current() ?? $user->tenant;

        if (! $tenant || ! $user->isAdmin() || ! app(OnboardingService::class)->pendente($tenant)) {
            return null;
        }

        return PrimeirosPassos::getUrl();
    }

    public static function manifestUrl(?User $user): string
    {
        return $user
            ? route('app.manifesto', self::for($user))
            : asset('manifest-'.self::for($user).'.json');
    }
}
