<?php

namespace App\Console\Commands;

use App\Models\NotaFiscal;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Console\Command;

/** Rede de segurança do retorno assíncrono: confere no emissor as notas "processando" há mais de 10 minutos. */
class ReconsultarNotasFiscais extends Command
{
    protected $signature = 'fiscal:reconsultar';

    protected $description = 'Confere no emissor fiscal as NFS-e que ainda estão em processamento';

    public function handle(NotaFiscalService $service): int
    {
        $conferidas = 0;

        NotaFiscal::withoutGlobalScopes()->where('status', NotaFiscal::PROCESSANDO)->where('updated_at', '<', now()->subMinutes(10))->orderBy('created_at')->limit(200)->get()
            ->each(function (NotaFiscal $nota) use ($service, &$conferidas) {
                try {
                    $service->reconsultar($nota);
                    $conferidas++;
                } catch (\Throwable $e) {
                    // Sem resposta do emissor por mais de 3 dias: deixa de esperar e libera para tentar de novo.
                    if ($nota->created_at->lt(now()->subDays(3))) {
                        $nota->forceFill(['status' => NotaFiscal::ERRO, 'erro' => 'O emissor não retornou a situação desta nota. Emita novamente se necessário.'])->save();
                    }
                }
            });

        $this->info("Notas conferidas: {$conferidas}");

        return self::SUCCESS;
    }
}
