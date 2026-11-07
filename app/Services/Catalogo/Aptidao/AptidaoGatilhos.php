<?php

namespace App\Services\Catalogo\Aptidao;

use App\Jobs\Aptidao\RecalcularAptidaoAtivoJob;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\CatalogoCumprimento;
use App\Models\FrotaChecklist;
use App\Models\FrotaLeituraOdometro;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Nr13Inspection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Gatilhos de recalculo da aptidao: fechar/mudar OS, lancar checklist, ler horimetro/km, documento, NR-13, cumprimento,
 * trocar o grupo do ativo. Nunca quebra a gravacao original (try/catch) e, com a chave desligada, so custa uma consulta.
 */
class AptidaoGatilhos
{
    public static function registrar(): void
    {
        HorimeterReading::created(fn ($m) => self::disparar($m->asset_id));
        FrotaLeituraOdometro::created(fn ($m) => self::disparar($m->ativo_id));

        // ativo novo ja nasce avaliado (sem grupo = cadastro incompleto); wasChanged() e falso na criacao, por isso o created separado
        Asset::created(fn (Asset $a) => self::disparar($a->id));

        Asset::saved(function (Asset $a) {
            if ($a->wasChanged(['asset_category_id', 'horimetro_atual', 'odometro_atual', 'atributos_tecnicos'])) {
                self::disparar($a->id);
            }
        });

        MaintenanceOrder::saved(function (MaintenanceOrder $os) {
            if ($os->asset_id && $os->wasChanged('status')) {
                self::disparar($os->asset_id);
            }
        });

        FrotaChecklist::saved(function (FrotaChecklist $c) {
            if ($c->wasChanged('concluido_em')) {
                self::disparar($c->ativo_id);
            }
        });

        foreach ([Nr13Inspection::class, AssetDocument::class, CatalogoCumprimento::class] as $classe) {
            $classe::saved(fn (Model $m) => self::disparar($m->asset_id));
            $classe::deleted(fn (Model $m) => self::disparar($m->asset_id));
        }
    }

    public static function disparar(?string $assetId): void
    {
        if (! $assetId) {
            return;
        }

        try {
            dispatch(new RecalcularAptidaoAtivoJob($assetId));
        } catch (\Throwable $e) {
            Log::warning('G1 aptidao: falha ao recalcular (ignorada para nao quebrar a gravacao original)', ['asset_id' => $assetId, 'erro' => $e->getMessage()]);
        }
    }
}
