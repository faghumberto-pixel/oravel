<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Bateria da frota: instalação, idade, garantia e tensão testada. */
class FrotaBateria extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_baterias';

    protected static ?string $saasPermissionSlug = 'bateria_frota';

    protected static ?string $saasModuleLabel = 'Baterias da Frota';

    protected $table = 'frota_baterias';

    public const ESTOQUE = 'estoque';

    public const MONTADA = 'montada';

    public const SUCATEADA = 'sucateada';

    /** Limites dos alertas (constantes simples por enquanto). */
    public const IDADE_ATENCAO_MESES = 24;

    public const IDADE_CRITICA_MESES = 36;

    public const GARANTIA_AVISO_DIAS = 30;

    public const TENSAO_ATENCAO_V = 12.4;

    public const TENSAO_CRITICA_V = 12.0;

    protected $attributes = ['situacao' => self::ESTOQUE];

    protected $fillable = [
        'peca_id', 'almoxarifado_id', 'tenant_id', 'marca', 'modelo', 'amperagem_ah', 'cca', 'numero_serie', 'comprada_em', 'garantia_ate', 'custo', 'situacao'];

    protected $casts = ['comprada_em' => 'date', 'garantia_ate' => 'date', 'custo' => 'decimal:2'];

    /** @return array<string, string> */
    public static function situacaoLabels(): array
    {
        return [self::ESTOQUE => 'Em estoque', self::MONTADA => 'Montada', self::SUCATEADA => 'Sucateada'];
    }

    /** @return array<string, string> motivos de remoção que fazem sentido para bateria */
    public static function motivoRemocaoLabels(): array
    {
        return ['desgaste' => 'Fim da vida útil', 'descarte' => 'Descarte (sucata)', 'garantia' => 'Troca por garantia', 'outro' => 'Outro (volta ao estoque)'];
    }

    public function rotulo(): string
    {
        return trim(($this->marca ?? '').' '.($this->modelo ?? '').($this->amperagem_ah ? ' · '.$this->amperagem_ah.' Ah' : '')) ?: 'Bateria';
    }

    public function instalacoes(): MorphMany
    {
        return $this->morphMany(FrotaInstalacaoComponente::class, 'componente')->orderByDesc('instalado_em');
    }

    public function instalacaoAberta(): MorphOne
    {
        return $this->morphOne(FrotaInstalacaoComponente::class, 'componente')->whereNull('removido_em');
    }

    public function testes(): HasMany
    {
        return $this->hasMany(FrotaTesteBateria::class, 'bateria_id')->orderByDesc('testado_em');
    }

    public function ultimoTeste(): ?FrotaTesteBateria
    {
        return $this->relationLoaded('testes') ? $this->testes->first() : $this->testes()->first();
    }

    public function idadeMeses(): ?int
    {
        return $this->comprada_em ? (int) $this->comprada_em->diffInMonths(now()) : null;
    }

    /** Km rodado em todas as montagens (a aberta vai até o odômetro atual do veículo). */
    public function kmRodado(): int
    {
        $instalacoes = $this->relationLoaded('instalacoes') ? $this->instalacoes : $this->instalacoes()->with('ativo:id,odometro_atual')->get();

        return (int) $instalacoes->sum(function (FrotaInstalacaoComponente $i) {
            $fim = $i->removido_em ? $i->odometro_remocao : (int) floor((float) $i->ativo?->odometro_atual);

            return max(0, (int) $fim - (int) $i->odometro_instalacao);
        });
    }

    /**
     * Alertas calculados ao vivo (sem tabela).
     *
     * @return array<int, array{tipo: string, gravidade: string, mensagem: string}>
     */
    public function alertas(): array
    {
        if ($this->situacao === self::SUCATEADA) {
            return [];
        }

        $alertas = [];
        $meses = $this->idadeMeses();

        if ($meses !== null && $meses >= self::IDADE_CRITICA_MESES) {
            $alertas[] = ['tipo' => 'idade', 'gravidade' => 'critica', 'mensagem' => "Bateria com {$meses} meses (limite ".self::IDADE_CRITICA_MESES.'): trocar'];
        } elseif ($meses !== null && $meses >= self::IDADE_ATENCAO_MESES) {
            $alertas[] = ['tipo' => 'idade', 'gravidade' => 'atencao', 'mensagem' => "Bateria com {$meses} meses: acompanhar"];
        }

        if ($this->garantia_ate) {
            $dias = (int) now()->startOfDay()->diffInDays($this->garantia_ate->copy()->startOfDay(), false);
            if ($dias >= 0 && $dias <= self::GARANTIA_AVISO_DIAS) {
                $alertas[] = ['tipo' => 'garantia', 'gravidade' => 'atencao', 'mensagem' => $dias === 0 ? 'Garantia vence hoje' : "Garantia vence em {$dias} dia(s)"];
            }
        }

        $teste = $this->ultimoTeste();
        if ($teste) {
            $v = (float) $teste->tensao;
            if ($v < self::TENSAO_CRITICA_V) {
                $alertas[] = ['tipo' => 'tensao', 'gravidade' => 'critica', 'mensagem' => "Tensão {$v} V (abaixo de ".self::TENSAO_CRITICA_V.' V)'];
            } elseif ($v < self::TENSAO_ATENCAO_V) {
                $alertas[] = ['tipo' => 'tensao', 'gravidade' => 'atencao', 'mensagem' => "Tensão {$v} V (abaixo de ".self::TENSAO_ATENCAO_V.' V)'];
            }
        }

        return $alertas;
    }
}
