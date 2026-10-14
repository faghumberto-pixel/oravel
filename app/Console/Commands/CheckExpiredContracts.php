<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Tenant;
use App\Services\DestinatariosAvisos;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/**
 * Contrato Ativo cuja data de fim já passou continua segurando o equipamento
 * (Locado) até alguém encerrar ou renovar. Não encerra sozinho de propósito:
 * o equipamento pode ainda estar no cliente e a cobrança continuar. Avisa o
 * Comercial no 1º dia de atraso e repete a cada 7 dias (sem tabela de controle:
 * o dia de atraso decide).
 */
class CheckExpiredContracts extends Command
{
    protected $signature = 'contracts:check-expired';

    protected $description = 'Avisa sobre contratos Ativos com a vigência vencida (no 1º dia de atraso e a cada 7 dias)';

    public function handle(): int
    {
        $enviados = 0;

        foreach (Tenant::all() as $tenant) {
            $vencidos = Contract::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'Ativo')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', today())
                ->with(['client', 'asset'])
                ->get()
                ->filter(function (Contract $contract) {
                    $diasDeAtraso = (int) $contract->end_date->startOfDay()->diffInDays(today());

                    return $diasDeAtraso % 7 === 1;
                });

            if ($vencidos->isEmpty()) {
                continue;
            }

            $linhas = $vencidos->map(fn (Contract $c) => '#'.$c->contract_number.' — '.($c->client?->name ?? 'sem cliente').' (fim em '.$c->end_date->format('d/m/Y').')')->implode('; ');

            foreach (DestinatariosAvisos::para($tenant->id, 'contrato_vencido') as $destinatario) {
                Notification::make()
                    ->title('Contratos com a vigência vencida')
                    ->body($vencidos->count().' contrato(s) Ativo(s) passaram da data de fim: '.$linhas.'. Encerre ou renove; enquanto isso o equipamento segue como Locado.')
                    ->warning()
                    ->actions([
                        Action::make('ver')->label('Ver contratos')->url(route('filament.admin.resources.contracts.index'))->button(),
                    ])
                    ->sendToDatabase($destinatario);
                $enviados++;
            }
        }

        $this->info("Verificação concluída. {$enviados} aviso(s) enviado(s).");

        return Command::SUCCESS;
    }
}
