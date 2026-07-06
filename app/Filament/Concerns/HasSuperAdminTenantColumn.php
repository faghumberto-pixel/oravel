<?php

namespace App\Filament\Concerns;

use Filament\Tables\Columns\TextColumn;

/**
 * Super admin ve os dados de todos os tenants misturados nas listagens (o
 * bypass de leitura de BelongsToTenant e intencional e documentado -- nunca
 * mexemos nisso). Sem uma coluna indicando de qual empresa e cada registro,
 * fica facil confundir "editei um registro" com "os outros tenants tem
 * dados parecidos" quando na verdade sao tenants diferentes na mesma lista.
 */
trait HasSuperAdminTenantColumn
{
    protected static function tenantColumn(): TextColumn
    {
        return TextColumn::make('tenant.name')
            ->label('Tenant')
            ->badge()
            ->color('gray')
            ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
            ->toggleable();
    }
}
