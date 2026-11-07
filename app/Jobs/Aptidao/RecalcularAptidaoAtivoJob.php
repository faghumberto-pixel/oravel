<?php

namespace App\Jobs\Aptidao;

use App\Models\Asset;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Recalcula a aptidao de UM ativo (disparado por observers). Chave do contrato desligada = nao faz nada. */
class RecalcularAptidaoAtivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $assetId) {}

    public function handle(AssetFitnessService $motor): void
    {
        $ativo = Asset::withoutGlobalScopes()->whereNull('deleted_at')->find($this->assetId);

        if (! $ativo || ! AptidaoGate::ligada($ativo->tenant_id)) {
            return;
        }

        $motor->recalcular($ativo);
    }
}
