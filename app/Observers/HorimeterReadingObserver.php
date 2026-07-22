<?php

namespace App\Observers;

use App\Models\HorimeterReading;
use Illuminate\Validation\ValidationException;

class HorimeterReadingObserver
{
    /**
     * Bloqueia leitura menor que a última (possível reset do horímetro,
     * ex: troca de painel) ou um salto grande demais (possível erro de
     * digitação) sem confirmação explícita -- reset_confirmed vira o
     * "sim, eu sei, é isso mesmo" do usuário.
     */
    public function creating(HorimeterReading $reading): void
    {
        if ($reading->reset_confirmed) {
            return;
        }

        $ultima = HorimeterReading::query()
            ->latestForAsset($reading->asset_id)
            ->value('reading');

        if ($ultima === null) {
            return;
        }

        $ultima = (float) $ultima;
        $atual = (float) $reading->reading;

        if ($atual < $ultima) {
            throw ValidationException::withMessages([
                'reading' => "A leitura informada ({$atual}h) é menor que a última registrada ({$ultima}h). "
                    .'Se o horímetro foi resetado (ex: troca de painel), confirme o reset pra prosseguir.',
            ]);
        }

        $threshold = (float) config('oravel.horimeter_jump_threshold', 500);
        $salto = $atual - $ultima;

        if ($salto > $threshold) {
            throw ValidationException::withMessages([
                'reading' => "O salto entre a última leitura ({$ultima}h) e esta ({$atual}h) é de {$salto}h, "
                    ."acima do limite esperado ({$threshold}h). Confirme se o valor está correto pra prosseguir.",
            ]);
        }
    }

    /**
     * Também mantém Asset.horimetro_atual/last_horimetro em sincronia
     * (mesmo critério "nunca regride" já usado por MaintenanceOrder::saved()
     * e PreventiveMaintenanceExecution::saved()) -- sem isso,
     * MaintenancePlan::dueStatusForAsset() e o resto do painel de
     * criticidade ficariam cegos pros apontamentos feitos só por aqui.
     */
    public function created(HorimeterReading $reading): void
    {
        $asset = $reading->asset;

        if ($asset) {
            $asset->forgetCurrentHorimeterCache();

            if ((float) $reading->reading >= (float) $asset->horimetro_atual) {
                $asset->updateQuietly([
                    'horimetro_atual' => $reading->reading,
                    'last_horimetro' => $reading->reading,
                ]);
            }
        }
    }
}
