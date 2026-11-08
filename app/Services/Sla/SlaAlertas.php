<?php

namespace App\Services\Sla;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Models\User;
use App\Services\DestinatariosAvisos;
use Carbon\CarbonInterface;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

/**
 * F3: avisos de SLA. Cada cronometro aberto e nao pausado e' avaliado; cada aviso sai UMA vez (warned_at / breached_at / escalated_at):
 * - em risco (>= % de alerta do tenant, padrao 70%): quem a empresa escolheu para `sla_em_risco` + o tecnico da OS;
 * - vencido (100%): `sla_vencido` + o tecnico da OS;
 * - escalonado (>= % de escalonamento, padrao 150%): `sla_escalonado`.
 */
class SlaAlertas
{
    /** @return array{em_risco: int, vencido: int, escalonado: int} cronometros sinalizados (um aviso sai por OS e evento, listando os relogios) */
    public static function avaliar(?CarbonInterface $agora = null): array
    {
        $agora ??= now();
        $enviados = ['em_risco' => 0, 'vencido' => 0, 'escalonado' => 0];
        $titulos = ['sla_em_risco' => 'SLA em risco', 'sla_vencido' => 'SLA vencido', 'sla_escalonado' => 'SLA escalonado'];

        $timers = SlaTimer::withoutGlobalScopes()
            ->where('subject_type', MaintenanceOrder::class)->whereNull('stopped_at')->whereNull('paused_at')
            ->where(fn ($q) => $q->whereNull('warned_at')->orWhereNull('breached_at')->orWhereNull('escalated_at'))
            ->orderBy('started_at')->get();

        /** @var array<string, array{os: MaintenanceOrder, evento: string, timers: array<int, SlaTimer>, marcas: array<int, array<string, mixed>>}> $grupos */
        $grupos = [];
        $ordens = [];

        foreach ($timers as $t) {
            $config = SlaService::settings((string) $t->tenant_id);
            $estado = $t->estado($config->warn_pct, $agora);

            if (! in_array($estado, ['em_risco', 'vencido'], true)) {
                continue;
            }

            $os = $ordens[$t->subject_id] ??= MaintenanceOrder::withoutGlobalScopes()->whereNull('deleted_at')->find($t->subject_id);

            if (! $os || in_array($os->status, ['Concluída', 'Concluído', 'Completado', 'Cancelada', 'Cancelado'], true)) {
                continue;
            }

            $acoes = [];
            if ($estado === 'em_risco' && $t->warned_at === null) {
                $acoes[] = ['sla_em_risco', 'em_risco', ['warned_at' => $agora]];
            }
            if ($estado === 'vencido' && $t->breached_at === null) {
                $acoes[] = ['sla_vencido', 'vencido', ['breached_at' => $agora, 'warned_at' => $t->warned_at ?? $agora]];
            }
            if ($estado === 'vencido' && $t->escalated_at === null && $t->minutosGastos($agora) >= $t->target_minutes * $config->escalate_pct / 100) {
                $acoes[] = ['sla_escalonado', 'escalonado', ['escalated_at' => $agora]];
            }

            foreach ($acoes as [$evento, $contagem, $marca]) {
                $chave = $os->id.'|'.$evento;
                $grupos[$chave] ??= ['os' => $os, 'evento' => $evento, 'timers' => [], 'marcas' => []];
                $grupos[$chave]['timers'][] = $t;
                $grupos[$chave]['marcas'][] = $marca;
                $enviados[$contagem]++;
            }
        }

        foreach ($grupos as $g) {
            self::avisar($g['os'], $g['timers'], $g['evento'], $titulos[$g['evento']], $g['evento'] !== 'sla_escalonado', $agora);

            foreach ($g['timers'] as $k => $t) {
                $t->update($g['marcas'][$k]);
            }
        }

        return $enviados;
    }

    /** @param  array<int, SlaTimer>  $timers */
    private static function avisar(MaintenanceOrder $os, array $timers, string $evento, string $titulo, bool $incluiTecnico, CarbonInterface $agora): void
    {
        $destinatarios = DestinatariosAvisos::para((string) $os->tenant_id, $evento);

        if ($incluiTecnico && $os->technician_id && ($tecnico = User::withoutGlobalScopes()->find($os->technician_id))) {
            $destinatarios = $destinatarios->push($tecnico);
        }

        $linhas = collect($timers)->map(function (SlaTimer $t) use ($agora) {
            $restante = $t->minutosRestantes($agora);
            $quando = $restante >= 0 ? 'restam '.SlaService::formatarMinutos($restante) : 'venceu há '.SlaService::formatarMinutos(abs($restante));

            return "{$t->label()}: {$quando} (meta ".SlaService::formatarMinutos($t->target_minutes).')';
        })->implode(' · ');
        $ativo = $os->asset?->name ?? 'equipamento';

        foreach ($destinatarios->unique('id') as $usuario) {
            Notification::make()
                ->title("{$titulo} · OS {$os->os_number}")
                ->body("{$linhas} — {$ativo}")
                ->{$evento === 'sla_em_risco' ? 'warning' : 'danger'}()
                ->persistent()
                ->actions([Action::make('abrir')->label('Abrir OS')->button()->url(MaintenanceOrderResource::getUrl('edit', ['record' => $os], panel: 'admin'))])
                ->sendToDatabase($usuario);
        }
    }
}
