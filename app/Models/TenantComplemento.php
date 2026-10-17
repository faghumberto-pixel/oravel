<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Complemento contratado por uma empresa: valor mensal na assinatura recorrente, com fidelidade de 12 meses. */
class TenantComplemento extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const ATIVO = 'ativo';

    public const ENCERRANDO = 'encerrando';

    public const ENCERRADO = 'encerrado';

    protected $fillable = [
        'tenant_id', 'feature', 'valor_mensal', 'contratado_em', 'fidelidade_meses', 'fidelidade_ate', 'status', 'encerra_em',
        'encerrado_em', 'encerrado_por', 'motivo_encerramento', 'encerramento_antecipado', 'aviso_fidelidade_em',
    ];

    protected $casts = [
        'valor_mensal' => 'decimal:2',
        'contratado_em' => 'date',
        'fidelidade_ate' => 'date',
        'encerra_em' => 'date',
        'encerrado_em' => 'datetime',
        'encerramento_antecipado' => 'boolean',
        'aviso_fidelidade_em' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function nome(): string
    {
        return config('complementos.catalogo.'.$this->feature, $this->feature);
    }

    /** Conta na mensalidade e mantém o módulo ligado (ativo ou com cancelamento agendado). */
    public function vigente(): bool
    {
        return in_array($this->status, [self::ATIVO, self::ENCERRANDO], true);
    }

    public function emFidelidade(?\DateTimeInterface $hoje = null): bool
    {
        return now()->startOfDay()->lt($this->fidelidade_ate);
    }
}
