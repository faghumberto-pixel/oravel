<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Leitura de odômetro de um veículo: só se acrescenta (nunca edita nem apaga). Cada leitura atualiza
 * assets.odometro_atual. Leitura menor que a anterior só entra com justificativa.
 */
class FrotaLeituraOdometro extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'frota_leituras_odometro';

    protected $fillable = ['tenant_id', 'ativo_id', 'odometro', 'origem', 'origem_id', 'justificativa', 'registrado_por', 'lido_em'];

    protected $casts = ['lido_em' => 'datetime', 'odometro' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Leituras de odômetro não podem ser alteradas.'));
        static::deleting(fn () => throw new \LogicException('Leituras de odômetro não podem ser apagadas.'));

        static::created(function (self $leitura) {
            Asset::withoutGlobalScopes()->where('id', $leitura->ativo_id)->update(['odometro_atual' => $leitura->odometro]);
        });
    }

    public function ativo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    /**
     * Registra uma leitura. Menor que a anterior exige justificativa (ex.: painel trocado).
     *
     * @throws ValidationException
     */
    public static function registrar(Asset $ativo, int $odometro, string $origem = 'manual', ?string $origemId = null, ?string $justificativa = null, ?string $usuarioId = null): self
    {
        $anterior = (int) floor((float) $ativo->odometro_atual);

        if ($odometro < $anterior && blank($justificativa)) {
            throw ValidationException::withMessages([
                'odometro' => "O odômetro informado ({$odometro} km) é menor que o último registrado ({$anterior} km). Confira o valor ou informe a justificativa.",
            ]);
        }

        $leitura = self::create([
            'tenant_id' => $ativo->tenant_id,
            'ativo_id' => $ativo->id,
            'odometro' => $odometro,
            'origem' => $origem,
            'origem_id' => $origemId,
            'justificativa' => $odometro < $anterior ? $justificativa : null,
            'registrado_por' => $usuarioId ?? auth()->id(),
            'lido_em' => now(),
        ]);

        // Atualiza o ativo em memória sem sujá-lo (o banco já foi atualizado no evento created).
        $ativo->setAttribute('odometro_atual', $odometro);
        $ativo->syncOriginalAttribute('odometro_atual');

        return $leitura;
    }
}
