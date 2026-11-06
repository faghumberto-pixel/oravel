<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Numero da O.S., unico por empresa. Com prefixo/numero inicial configurados em Configuracoes, segue a sequencia da empresa
 * (ex.: MNT-000123); sem configuracao, mantem o padrao antigo OS-AAAAMM-NNNNN.
 */
class OsNumberGenerator
{
    public function next(string|int|null $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $tenant = $tenantId ? Tenant::query()->whereKey($tenantId)->lockForUpdate()->first() : null;

            if ($tenant && filled($tenant->os_prefix)) {
                $n = max(1, (int) ($tenant->os_next_number ?: 1));
                while ($this->existe($tenantId, $tenant->os_prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT))) {
                    $n++;
                }
                $tenant->forceFill(['os_next_number' => $n + 1])->saveQuietly();

                return $tenant->os_prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            }

            $prefix = 'OS-'.now()->format('Ym').'-';
            $max = MaintenanceOrder::withoutGlobalScopes()->withTrashed()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('os_number', 'like', $prefix.'%')->pluck('os_number')
                ->map(fn ($v) => ctype_digit($suffix = substr((string) $v, strlen($prefix))) ? (int) $suffix : 0)->max();

            return $prefix.str_pad((string) ($max ? $max + 1 : 10000), 5, '0', STR_PAD_LEFT);
        });
    }

    public function existe(string|int|null $tenantId, string $numero, string|int|null $ignorarId = null): bool
    {
        return MaintenanceOrder::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->where('os_number', $numero)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))->exists();
    }
}
