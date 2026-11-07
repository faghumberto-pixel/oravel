<?php

namespace App\Jobs\Aptidao;

use App\Models\Asset;
use App\Models\Tenant;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Job diario: vencimentos por data (documento, inspecao, preventiva por dias) so mudam com o tempo, sem nenhum evento. */
class RecalcularAptidaoDiarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $tenantId = null) {}

    public function handle(AssetFitnessService $motor): void
    {
        $tenants = Tenant::withoutGlobalScopes()->when($this->tenantId, fn ($q) => $q->whereKey($this->tenantId))->pluck('id');

        foreach ($tenants as $tenantId) {
            if (! AptidaoGate::ligada($tenantId)) {
                continue;
            }

            Asset::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ->chunkById(100, fn ($lote) => $lote->each(fn (Asset $a) => $motor->recalcular($a)));
        }
    }
}
