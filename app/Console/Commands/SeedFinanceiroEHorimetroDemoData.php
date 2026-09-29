<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Complementa o dataset criado por `demo:seed-ativo-frota`: horímetro do
 * Guincho e do Caminhão (só a Escavadeira tinha leituras), e Contas a
 * Receber simuladas desde a data de assinatura de cada Contrato, pra o
 * Dossiê do Ativo mostrar "cliente em dia com os pagamentos" de verdade.
 * Pedido do usuário 29/09/2026.
 *
 * Idempotente: pula qualquer contrato que já tenha Contas a Receber.
 */
class SeedFinanceiroEHorimetroDemoData extends Command
{
    protected $signature = 'demo:seed-financeiro-horimetro {email=faghumberto@gmail.com}';

    protected $description = 'Completa horímetro e Contas a Receber simuladas pros Contratos criados por demo:seed-ativo-frota';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $tenantId = $user->tenant_id;
        Auth::login($user);

        DB::transaction(function () use ($tenantId) {
            // Terraplenagem — Escavadeira, cobrança por hora. Cliente em dia:
            // 2 faturas quinzenais, ambas pagas.
            $this->seedContractBilling($tenantId, 'TERRA', [
                ['days_after_start' => 15, 'paid' => true],
                ['days_after_start' => 30, 'paid' => true],
            ], 185.00 * 8); // ~8h/dia úteis, só valor ilustrativo por período

            // Içamento/Guincho — cobrança diária, atendimento pontual. 1 fatura, paga.
            $this->seedContractBilling($tenantId, 'GUINCHO', [
                ['days_after_start' => 1, 'paid' => true],
            ], 950.00);

            // Transporte — cliente fixo mensal. Backdata o início do contrato
            // pra ter histórico de verdade (senão só teria 1 fatura ainda nem
            // vencida, pouco ilustrativo numa demo) e simula 4 meses: 3 pagas
            // em dia, a mais recente ainda dentro do prazo.
            $transporte = Contract::where('tenant_id', $tenantId)
                ->where('contract_number', 'like', '%TRANSP%')
                ->first();

            if ($transporte && ! AccountReceivable::where('contract_id', $transporte->id)->exists()) {
                $transporte->update(['start_date' => now()->subMonths(4)]);

                $this->seedMonthlyBilling($tenantId, $transporte, 4);
                $this->extendCaminhaoHorimetroHistory($tenantId, $transporte);
            }

            $this->seedGuinchoHorimeter($tenantId);
        });

        Auth::logout();

        $this->info('Horímetro e Contas a Receber de demonstração completados.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{days_after_start:int, paid:bool}>  $schedule
     */
    private function seedContractBilling(string $tenantId, string $contractNumberFragment, array $schedule, float $amount): void
    {
        $contract = Contract::where('tenant_id', $tenantId)
            ->where('contract_number', 'like', "%{$contractNumberFragment}%")
            ->first();

        if (! $contract || AccountReceivable::where('contract_id', $contract->id)->exists()) {
            return;
        }

        foreach ($schedule as $item) {
            $dueDate = $contract->start_date->copy()->addDays($item['days_after_start']);

            AccountReceivable::create([
                'tenant_id' => $tenantId,
                'client_id' => $contract->client_id,
                'contract_id' => $contract->id,
                'description' => "Locação {$contract->contract_number} — período até ".$dueDate->format('d/m/Y'),
                'amount' => $amount,
                'due_date' => $dueDate,
                'payment_date' => $item['paid'] ? $dueDate->copy()->subDay() : null,
                'status' => $item['paid'] ? 'pago' : 'pendente',
            ]);
        }
    }

    private function seedMonthlyBilling(string $tenantId, Contract $contract, int $months): void
    {
        for ($i = 1; $i <= $months; $i++) {
            $dueDate = $contract->start_date->copy()->addMonths($i);
            $isLast = $i === $months;
            $isPastDue = $dueDate->isPast();

            AccountReceivable::create([
                'tenant_id' => $tenantId,
                'client_id' => $contract->client_id,
                'contract_id' => $contract->id,
                'description' => "Mensalidade {$contract->contract_number} — ".$dueDate->format('m/Y'),
                'amount' => $contract->price,
                'due_date' => $dueDate,
                'payment_date' => (! $isLast || $isPastDue) ? $dueDate->copy()->subDays(2) : null,
                'status' => (! $isLast || $isPastDue) ? 'pago' : 'pendente',
            ]);
        }
    }

    private function extendCaminhaoHorimetroHistory(string $tenantId, Contract $contract): void
    {
        $caminhao = Asset::find($contract->asset_id);

        if (! $caminhao || HorimeterReading::where('asset_id', $caminhao->id)->exists()) {
            return;
        }

        HorimeterReading::create([
            'tenant_id' => $tenantId, 'asset_id' => $caminhao->id,
            'reading' => 200, 'recorded_at' => $contract->start_date->copy()->addDay(),
            'source' => HorimeterReading::SOURCE_MANUAL, 'notes' => 'Horímetro no início da locação.',
        ]);

        $caminhao->update(['horimetro_atual' => 620, 'last_horimetro' => 620]);
    }

    private function seedGuinchoHorimeter(string $tenantId): void
    {
        $guincho = Asset::where('tenant_id', $tenantId)
            ->where('name', 'Guincho de Reboque Pesado')
            ->first();

        if (! $guincho || HorimeterReading::where('asset_id', $guincho->id)->exists()) {
            return;
        }

        $contract = Contract::where('tenant_id', $tenantId)
            ->where('contract_number', 'like', '%GUINCHO%')
            ->first();

        $start = $contract?->start_date ?? now()->subDays(15);

        HorimeterReading::create([
            'tenant_id' => $tenantId, 'asset_id' => $guincho->id,
            'reading' => 80, 'recorded_at' => $start->copy()->addDay(),
            'source' => HorimeterReading::SOURCE_MANUAL, 'notes' => 'Horímetro no início da locação.',
        ]);

        $guincho->update(['horimetro_atual' => 96, 'last_horimetro' => 96]);
    }
}
