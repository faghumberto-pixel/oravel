<?php

namespace App\Console\Commands;

use App\Models\TenantFiscalSetting;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/** Avisa os administradores da empresa quando o certificado digital A1 está para vencer (30, 15, 7 e 1 dia) ou venceu. */
class AvisarCertificadosFiscais extends Command
{
    protected $signature = 'fiscal:avisar-certificados';

    protected $description = 'Avisa os administradores quando o certificado digital da NFS-e está perto de vencer';

    private const MARCOS = [30, 15, 7, 1, 0];

    public function handle(): int
    {
        $avisos = 0;

        TenantFiscalSetting::withoutGlobalScopes()->where('enabled', true)->whereNotNull('certificado_validade')->each(function (TenantFiscalSetting $c) use (&$avisos) {
            $dias = (int) today()->diffInDays($c->certificado_validade, false);

            if (! in_array($dias, self::MARCOS, true) || ! TenantFiscalSetting::moduloLiberado($c->tenant_id)) {
                return;
            }

            $admins = User::withoutGlobalScopes()->where('tenant_id', $c->tenant_id)->get()->filter(fn (User $u) => $u->isAdmin());

            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()->title($dias === 0 ? 'O certificado digital das notas fiscais vence hoje' : "O certificado digital das notas fiscais vence em {$dias} dia(s)")
                ->body('Envie o certificado renovado em Configurações → Notas fiscais para não interromper a emissão.')->warning()->sendToDatabase($admins);
            $avisos++;
        });

        $this->info("Avisos enviados: {$avisos}");

        return self::SUCCESS;
    }
}
