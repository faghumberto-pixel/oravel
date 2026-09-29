<?php

namespace App\Filament\Concerns;

use App\Models\AccountReceivable;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados sintetizados do Dossie Rapido do Ativo -- compartilhado entre a
 * versao desktop (App\Filament\Pages\AssetDossier) e a mobile
 * (App\Livewire\AssetDossierMobile), que tem visual proprio (Tailwind puro,
 * tema escuro) mas a mesma logica de dados. Exige `public ?Asset $asset`
 * na classe que usa a trait.
 */
trait HasAssetDossierData
{
    public function getCurrentContractProperty(): ?Contract
    {
        return $this->asset?->contracts()
            ->where('status', 'Ativo')
            ->latest('start_date')
            ->first();
    }

    /** @return Collection<int, MaintenanceOrder> */
    public function getOpenOrdersProperty(): Collection
    {
        if (! $this->asset) {
            return collect();
        }

        return $this->asset->maintenanceOrders()
            ->with(['technician', 'reportedProblem'])
            ->whereNotIn('status', ['Concluída', 'Cancelada', 'Cancelado'])
            ->latest('created_at')
            ->get();
    }

    /** @return Collection<int, EquipmentDamage> */
    public function getRecentDamagesProperty(): Collection
    {
        if (! $this->asset) {
            return collect();
        }

        return $this->asset->damages()
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Pedido do usuário 29/09/2026: "horas trabalhadas" precisa contar a
     * partir do INÍCIO DA LOCAÇÃO atual (não desde a compra do equipamento)
     * -- compara o horímetro mais próximo da data de início do contrato
     * vigente contra o horímetro atual, e tira uma média diária real.
     *
     * @return array{horimetro_atual: float, horimetro_inicio_locacao: ?float, horas_trabalhadas: ?float, dias_locado: ?int, media_diaria: ?float}
     */
    public function getWorkedHoursSummaryProperty(): array
    {
        $horimetroAtual = (float) ($this->asset?->horimetro_atual ?? 0);

        $contract = $this->currentContract;

        if (! $contract || ! $contract->start_date) {
            return [
                'horimetro_atual' => $horimetroAtual,
                'horimetro_inicio_locacao' => null,
                'horas_trabalhadas' => null,
                'dias_locado' => null,
                'media_diaria' => null,
            ];
        }

        // Leitura mais próxima do início da locação: a primeira registrada
        // A PARTIR da data do contrato -- se não existir (ex: só teve
        // leitura antes de fechar o contrato), cai pra última leitura
        // anterior à data, que é a aproximação mais razoável do horímetro
        // "no dia que entregou o equipamento".
        $readingAtStart = HorimeterReading::where('asset_id', $this->asset->id)
            ->where('recorded_at', '>=', $contract->start_date)
            ->oldest('recorded_at')
            ->value('reading');

        if ($readingAtStart === null) {
            $readingAtStart = HorimeterReading::where('asset_id', $this->asset->id)
                ->where('recorded_at', '<', $contract->start_date)
                ->latest('recorded_at')
                ->value('reading');
        }

        $horimetroInicio = $readingAtStart !== null ? (float) $readingAtStart : null;
        $diasLocado = max(1, (int) Carbon::parse($contract->start_date)->diffInDays(now()));
        $horasTrabalhadas = $horimetroInicio !== null ? max(0, $horimetroAtual - $horimetroInicio) : null;
        $mediaDiaria = $horasTrabalhadas !== null ? round($horasTrabalhadas / $diasLocado, 2) : null;

        return [
            'horimetro_atual' => $horimetroAtual,
            'horimetro_inicio_locacao' => $horimetroInicio,
            'horas_trabalhadas' => $horasTrabalhadas,
            'dias_locado' => $diasLocado,
            'media_diaria' => $mediaDiaria,
        ];
    }

    /**
     * Situação de pagamento do cliente do contrato vigente -- pedido
     * explícito do usuário: o Dossiê deve mostrar só "em dia" ou "atrasado",
     * sem entrar em detalhe de fatura por fatura (isso fica em Contas a
     * Receber, não aqui).
     */
    public function getPaymentStatusProperty(): ?string
    {
        $clientId = $this->currentContract?->client_id;

        if (! $clientId) {
            return null;
        }

        $hasOverdue = AccountReceivable::where('client_id', $clientId)
            ->where('status', '!=', 'pago')
            ->where('due_date', '<', now())
            ->exists();

        return $hasOverdue ? 'atrasado' : 'em_dia';
    }
}
