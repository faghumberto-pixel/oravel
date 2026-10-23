<?php

namespace App\Support\Colaborador;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Collection;

/** Situação de manutenção do ativo e dados de OS exibidos no app do colaborador. */
class SituacaoOs
{
    /** Status da OS que a tiram do "em aberto" (mesma lista do dossiê do ativo em PDF). */
    public const FECHADAS = ['Concluída', 'Concluida', 'Cancelada', 'Cancelado'];

    private const ROTULO_OS = [
        'aguardando_peca' => ['Aguardando peça', 'amber'],
        'em_manutencao' => ['Em manutenção', 'orange'],
        'aguardando_diagnostico' => ['Aguardando diagnóstico', 'red'],
    ];

    private const ROTULO_ATIVO = [
        Asset::STATUS_DISPONIVEL => ['Liberado', 'green'],
        Asset::STATUS_OPERANDO => ['Operando', 'sky'],
        Asset::STATUS_LOCADO => ['Locado', 'yellow'],
        Asset::STATUS_MANUTENCAO => ['Em manutenção', 'orange'],
        Asset::STATUS_AGUARDANDO_TRIAGEM => ['Aguardando triagem', 'red'],
        Asset::STATUS_QUARENTENA => ['Em quarentena', 'red'],
        Asset::STATUS_RESERVADO => ['Reservado', 'sky'],
    ];

    /** @return array{rotulo: string, cor: string} situação de UMA OS */
    public static function daOs(MaintenanceOrder $os): array
    {
        [$rotulo, $cor] = self::ROTULO_OS[$os->internal_status] ?? ['Em manutenção', 'orange'];

        if (in_array($os->status, self::FECHADAS, true)) {
            return ['rotulo' => str_starts_with((string) $os->status, 'Cancel') ? 'Cancelada' : 'Concluída', 'cor' => 'green'];
        }

        return ['rotulo' => $rotulo, 'cor' => $cor];
    }

    /** Dias corridos desde a abertura (até a conclusão, se já fechada). */
    public static function diasEmAberto(MaintenanceOrder $os): int
    {
        $fim = in_array($os->status, self::FECHADAS, true) ? ($os->finished_at ?? $os->resolved_at ?? $os->updated_at) : now();

        return (int) $os->created_at->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay());
    }

    /** @return Collection<int, MaintenanceOrder> OS em aberto do ativo, da mais antiga para a mais nova */
    public static function abertas(Asset $asset): Collection
    {
        return MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->whereNotIn('status', self::FECHADAS)
            ->with('technician')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Situação geral do ativo para o cabeçalho do dossiê: com OS aberta vale a mais "travada"
     * (aguardando peça > em manutenção > aguardando diagnóstico); sem OS aberta vale o status do próprio ativo.
     *
     * @return array{rotulo: string, cor: string, abertas: Collection<int, array<string, mixed>>}
     */
    public static function doAtivo(Asset $asset): array
    {
        $abertas = self::abertas($asset);

        if ($abertas->isEmpty()) {
            [$rotulo, $cor] = self::ROTULO_ATIVO[$asset->status] ?? [ucfirst(str_replace('_', ' ', (string) $asset->status)), 'zinc'];

            return ['rotulo' => $rotulo, 'cor' => $cor, 'abertas' => collect()];
        }

        $ordem = ['aguardando_peca', 'em_manutencao', 'aguardando_diagnostico'];
        $principal = $abertas->sortBy(fn ($os) => array_search($os->internal_status, $ordem, true) === false ? 99 : array_search($os->internal_status, $ordem, true))->first();
        $geral = self::daOs($principal);

        return [
            'rotulo' => $geral['rotulo'],
            'cor' => $geral['cor'],
            'abertas' => $abertas->map(fn (MaintenanceOrder $os) => [
                'id' => $os->id,
                'numero' => $os->os_number,
                'situacao' => self::daOs($os),
                'aberta_em' => $os->created_at,
                'dias' => self::diasEmAberto($os),
                'tecnico' => $os->technician?->name,
            ]),
        ];
    }

    /** Classes Tailwind (literais, para o compilador enxergar) por cor. */
    public static function classes(string $cor): string
    {
        return match ($cor) {
            'green' => 'bg-emerald-100 text-emerald-800',
            'orange' => 'bg-orange-100 text-orange-800',
            'amber' => 'bg-amber-100 text-amber-800',
            'red' => 'bg-red-100 text-red-700',
            'yellow' => 'bg-yellow-100 text-yellow-800',
            'sky' => 'bg-sky-100 text-sky-800',
            default => 'bg-zinc-200 text-zinc-700',
        };
    }
}
