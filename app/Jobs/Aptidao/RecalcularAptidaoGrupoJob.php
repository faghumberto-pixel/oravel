<?php

namespace App\Jobs\Aptidao;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Recalcula todos os ativos de um grupo (depois de mudar a regra do grupo), em lotes. */
class RecalcularAptidaoGrupoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $grupoId) {}

    public function handle(AssetFitnessService $motor): void
    {
        $grupo = AssetCategory::withoutGlobalScopes()->withTrashed()->find($this->grupoId);

        if (! $grupo || ! AptidaoGate::ligada($grupo->tenant_id)) {
            return;
        }

        Asset::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('asset_category_id', $grupo->id)->whereNull('deleted_at')
            ->chunkById(100, fn ($lote) => $lote->each(fn (Asset $a) => $motor->recalcular($a)));
    }
}
