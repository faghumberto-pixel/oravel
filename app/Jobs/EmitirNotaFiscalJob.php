<?php

namespace App\Jobs;

use App\Models\AccountReceivable;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Emissão automática de NFS-e quando a conta a receber é paga (só se a empresa ligou a emissão automática). */
class EmitirNotaFiscalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $contaId) {}

    public function handle(NotaFiscalService $service): void
    {
        $conta = AccountReceivable::withoutGlobalScopes()->find($this->contaId);

        if ($conta && $conta->status === 'pago') {
            $service->emitirAoReceber($conta);
        }
    }
}
