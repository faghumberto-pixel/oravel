<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Percurso: trajeto entre dois locais (A ↔ B) que recebe o km real das viagens (trechos de FrotaSaidaVeiculo), nos dois sentidos.
 * Ex.: "Empresa ↔ Cliente Topmixx" recebe tanto o trecho empresa → cliente quanto o cliente → empresa.
 */
class FrotaPercurso extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const TIPOS = ['empresa' => 'Empresa', 'cliente' => 'Cliente', 'outro' => 'Outro local'];

    protected static ?string $saasFeatureKey = 'tabela_frota_percursos';

    protected static ?string $saasPermissionSlug = 'percurso_frota';

    protected static ?string $saasModuleLabel = 'Percursos da Frota';

    protected $table = 'frota_percursos';

    protected $attributes = ['ativo' => true, 'local_a_tipo' => 'empresa', 'local_b_tipo' => 'cliente'];

    protected $fillable = [
        'tenant_id', 'nome', 'local_a_tipo', 'local_a_cliente_id', 'local_a_texto', 'local_b_tipo', 'local_b_cliente_id', 'local_b_texto',
        'km_previsto', 'ativo', 'observacoes',
    ];

    protected $casts = ['ativo' => 'boolean', 'km_previsto' => 'decimal:1'];

    public function clienteA(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'local_a_cliente_id');
    }

    public function clienteB(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'local_b_cliente_id');
    }

    public function viagens(): HasMany
    {
        return $this->hasMany(FrotaSaidaVeiculo::class, 'percurso_id');
    }

    public function rotuloA(): string
    {
        return self::rotuloLocal($this->local_a_tipo, $this->clienteA?->name, $this->local_a_texto);
    }

    public function rotuloB(): string
    {
        return self::rotuloLocal($this->local_b_tipo, $this->clienteB?->name, $this->local_b_texto);
    }

    public function trajeto(): string
    {
        return $this->rotuloA().' ↔ '.$this->rotuloB();
    }

    public static function rotuloLocal(?string $tipo, ?string $cliente, ?string $texto): string
    {
        return match ($tipo) {
            'empresa' => 'Empresa',
            'cliente' => $cliente ?: 'Cliente',
            default => $texto ?: 'Outro local',
        };
    }

    /** Dois locais são o mesmo? Empresa = empresa; cliente = mesmo cliente; outro = mesmo texto (sem caixa). */
    public static function mesmoLocal(?string $tipoX, ?string $clienteX, ?string $textoX, ?string $tipoY, ?string $clienteY, ?string $textoY): bool
    {
        if ($tipoX !== $tipoY) {
            return false;
        }

        return match ($tipoX) {
            'empresa' => true,
            'cliente' => filled($clienteX) && $clienteX === $clienteY,
            default => filled($textoX) && mb_strtolower(trim((string) $textoX)) === mb_strtolower(trim((string) $textoY)),
        };
    }

    /** Este percurso liga a origem ao destino (em qualquer sentido)? */
    public function liga(?string $origemTipo, ?string $origemCliente, ?string $origemTexto, ?string $destinoTipo, ?string $destinoCliente, ?string $destinoTexto): bool
    {
        $a = [$this->local_a_tipo, $this->local_a_cliente_id, $this->local_a_texto];
        $b = [$this->local_b_tipo, $this->local_b_cliente_id, $this->local_b_texto];
        $o = [$origemTipo, $origemCliente, $origemTexto];
        $d = [$destinoTipo, $destinoCliente, $destinoTexto];

        return (self::mesmoLocal(...$a, ...$o) && self::mesmoLocal(...$b, ...$d))
            || (self::mesmoLocal(...$b, ...$o) && self::mesmoLocal(...$a, ...$d));
    }
}
