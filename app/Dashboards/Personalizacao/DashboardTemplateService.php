<?php

namespace App\Dashboards\Personalizacao;

use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Operações da Central sobre modelos de dashboard: duplicar, criar a versão exclusiva de uma empresa e atribuir.
 * Roda no painel Central (super admin), então o escopo dos models não filtra nada aqui.
 */
class DashboardTemplateService
{
    /** Cópia do modelo e dos blocos (com config e ficha). Um modelo exclusivo continua exclusivo da mesma empresa. */
    public static function duplicar(DashboardTemplate $modelo, ?string $nome = null): DashboardTemplate
    {
        return DB::transaction(function () use ($modelo, $nome) {
            $copia = DashboardTemplate::create([
                'tenant_id' => $modelo->tenant_id,
                'source_template_id' => $modelo->id,
                'name' => $nome ?: $modelo->name.' (cópia)',
                'profile' => $modelo->profile,
                'description' => $modelo->description,
                'is_active' => true,
            ]);

            static::copiarBlocos($modelo, $copia);

            return $copia;
        });
    }

    /** Copia um modelo da plataforma para uma empresa, para ajustar só para ela, e já o atribui a ela. */
    public static function exclusivoPara(DashboardTemplate $modelo, Tenant $empresa, ?string $nome = null): DashboardTemplate
    {
        if (! $modelo->isPlatform()) {
            throw new InvalidArgumentException('Só um modelo da plataforma pode virar exclusivo de uma empresa.');
        }

        return DB::transaction(function () use ($modelo, $empresa, $nome) {
            $copia = DashboardTemplate::create([
                'tenant_id' => $empresa->id,
                'source_template_id' => $modelo->id,
                'name' => $nome ?: $modelo->name.' — '.$empresa->name,
                'profile' => $modelo->profile,
                'description' => $modelo->description,
                'is_active' => true,
            ]);

            static::copiarBlocos($modelo, $copia);
            static::atribuir($copia, [$empresa->id]);

            return $copia;
        });
    }

    /**
     * Atribui o modelo a empresas. Modelo exclusivo só pode ir para a própria empresa dele.
     *
     * @param  iterable<string>  $tenantIds
     * @return int quantas atribuições novas foram criadas (repetir a mesma não duplica)
     */
    public static function atribuir(DashboardTemplate $modelo, iterable $tenantIds): int
    {
        $ids = collect($tenantIds)->filter()->unique()->values();

        if (! $modelo->isPlatform() && $ids->contains(fn ($id) => $id !== $modelo->tenant_id)) {
            throw new InvalidArgumentException('Um dashboard exclusivo só pode ser atribuído à empresa dona dele.');
        }

        $existentes = DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $modelo->id)->pluck('tenant_id');
        $criadas = 0;

        foreach ($ids->diff($existentes) as $id) {
            DashboardAssignment::withoutGlobalScopes()->create(['tenant_id' => $id, 'dashboard_template_id' => $modelo->id]);
            $criadas++;
        }

        return $criadas;
    }

    private static function copiarBlocos(DashboardTemplate $de, DashboardTemplate $para): void
    {
        foreach ($de->blocks as $bloco) {
            $para->blocks()->create($bloco->only(['block_key', 'position', 'config', 'ficha_dor', 'ficha_indicador', 'ficha_meta', 'ficha_revisao_em']));
        }
    }
}
