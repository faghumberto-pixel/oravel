<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // LEITURA: so enxerga registros do tenant do usuario logado.
        static::addGlobalScope('tenant', function (Builder $builder) {
            $user = Auth::user();

            // Console, seeders, jobs, tela de login -> sem filtro.
            if (! $user) {
                return;
            }

            // Super admin enxerga tudo.
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return;
            }

            // Usuario sem tenant_id -> nada a escopar.
            if (blank($user->tenant_id)) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                $user->tenant_id
            );
        });

        // ESCRITA: novo registro nasce com o tenant do usuario logado (ou,
        // para super admin, o tenant "atuante" escolhido em sessao --
        // ver App\Support\Tenancy::current()).
        static::creating(function (Model $model) {
            if (blank($model->tenant_id)) {
                $tenant = Tenancy::current();

                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
