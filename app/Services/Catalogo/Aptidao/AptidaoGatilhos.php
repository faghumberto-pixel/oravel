<?php

namespace App\Services\Catalogo\Aptidao;

use App\Jobs\Aptidao\RecalcularAptidaoAtivoJob;
use App\Models\AptidaoLiberacao;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\CatalogoCumprimento;
use App\Models\EquipmentMovement;
use App\Models\FrotaChecklist;
use App\Models\FrotaLeituraOdometro;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Nr13Inspection;
use App\Models\SolicitacaoLocacao;
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

        // IMP reprovado (com gera_os) abre OS corretiva; nunca derruba o registro do checklist
        CatalogoCumprimento::created(fn (CatalogoCumprimento $c) => app(OsAutomaticaAptidao::class)->tentar($c));

        AptidaoLiberacao::saved(fn ($l) => self::disparar($l->asset_id));

        self::registrarBloqueios();

        foreach ([Nr13Inspection::class, AssetDocument::class, CatalogoCumprimento::class] as $classe) {
            $classe::saved(fn (Model $m) => self::disparar($m->asset_id));
            $classe::deleted(fn (Model $m) => self::disparar($m->asset_id));
        }
    }

    /**
     * Bloqueio de ALOCACAO/DESPACHO por aptidao (so com a chave do contrato ligada; AptidaoBloqueio sai cedo senao):
     * mobilizacao de equipamento sem OS (despacho de locacao/agenda) e solicitacao de locacao virando contrato fechado.
     * A troca de equipamento (EquipmentReplacement) chama AptidaoBloqueio direto, antes de criar qualquer movimentacao.
     */
    private static function registrarBloqueios(): void
    {
        EquipmentMovement::creating(function (EquipmentMovement $m) {
            // mobilizacao ligada a uma OS de manutencao e exatamente o caso de um ativo Inapto precisar ir ao conserto: nao bloqueia.
            if ($m->type === EquipmentMovement::TYPE_MOBILIZACAO && blank($m->maintenance_order_id) && $m->asset_id) {
                AptidaoBloqueio::exigirPodeAlocar(Asset::withoutGlobalScopes()->find($m->asset_id));
            }
        });

        SolicitacaoLocacao::saving(function (SolicitacaoLocacao $s) {
            if (! ($s->isDirty('status_comercial') && $s->status_comercial === 'contrato_fechado')) {
                return;
            }

            if ($s->asset_id) {
                AptidaoBloqueio::exigirPodeAlocar(Asset::withoutGlobalScopes()->find($s->asset_id));
            }

            if ($s->exists) {
                foreach ($s->assets as $ativo) {
                    AptidaoBloqueio::exigirPodeAlocar($ativo);
                }
            }
        });
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
