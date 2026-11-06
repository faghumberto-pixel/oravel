<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Utilidades dos blocos novos do Comercial (formatacao e rotulos). */
trait ComercialIaHelpers
{
    protected function compacto(float $v): string
    {
        $abs = abs($v);

        return match (true) {
            $abs >= 1_000_000 => 'R$ '.number_format($abs / 1_000_000, 1, ',', '.').' mi',
            $abs >= 100_000 => 'R$ '.number_format($abs / 1000, 0, ',', '.').' mil',
            $abs >= 1000 => 'R$ '.number_format($abs / 1000, 1, ',', '.').' mil',
            default => 'R$ '.number_format($abs, 0, ',', '.'),
        };
    }

    /** @return array<string, string> */
    protected function etapas(): array
    {
        return [
            CrmLead::STAGE_NOVO => 'Novo', CrmLead::STAGE_CONTATO_INICIADO => 'Contato iniciado', CrmLead::STAGE_QUALIFICADO => 'Qualificado',
            CrmLead::STAGE_CONVERTIDO => 'Convertido', CrmLead::STAGE_PERDIDO => 'Perdido',
        ];
    }

    /** @return array<string, string> */
    protected function coresDasEtapas(): array
    {
        return [
            CrmLead::STAGE_NOVO => '#64748b', CrmLead::STAGE_CONTATO_INICIADO => '#38bdf8', CrmLead::STAGE_QUALIFICADO => '#f59e0b',
            CrmLead::STAGE_CONVERTIDO => '#16a34a', CrmLead::STAGE_PERDIDO => '#ef4444',
        ];
    }

    /** @return array<string, string> */
    protected function origens(): array
    {
        return ['indicacao' => 'Indicação', 'site' => 'Site', 'contato_frio' => 'Contato frio', 'evento' => 'Evento', 'outro' => 'Outro'];
    }

    /** @return array<string, string> */
    protected function nomesDeUsuarios(iterable $ids): array
    {
        return User::query()->whereIn('id', collect($ids)->filter()->unique()->all())->pluck('name', 'id')->map(fn ($n) => trim(preg_replace('/\(.*?\)/', '', $n)))->all();
    }

    protected function rotuloDaData(Carbon $data): string
    {
        $hoje = Carbon::today();

        return match (true) {
            $data->isSameDay($hoje) => 'Hoje',
            $data->isSameDay($hoje->copy()->addDay()) => 'Amanhã',
            $data->isSameDay($hoje->copy()->subDay()) => 'Ontem',
            default => $data->format('d/m'),
        };
    }
}
