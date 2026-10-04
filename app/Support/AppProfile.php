<?php

namespace App\Support;

use App\Models\MaintenanceOrder;
use App\Models\User;

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
            self::ADMIN => route('filament.admin.pages.painel-controle'),
            self::TECNICO => route('filament.admin.pages.technician-daily-tasks'),
            default => route('filament.admin.pages.app-colaborador'),
        };
    }

    public static function manifestUrl(?User $user): string
    {
        return asset('manifest-'.self::for($user).'.json');
    }
}
