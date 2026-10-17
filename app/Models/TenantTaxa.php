<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Taxa de configuração aprovada por um assinante (nome e valor congelados na aprovação). Pagamento único. */
class TenantTaxa extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const APROVADA = 'aprovada';

    public const COBRADA = 'cobrada';

    public const PAGA = 'paga';

    public const CANCELADA = 'cancelada';

    protected $table = 'tenant_taxas';

    protected $fillable = [
        'tenant_id', 'taxa_codigo', 'nome', 'valor', 'origem', 'aprovada_em', 'aprovada_ip', 'status', 'asaas_payment_id', 'invoice_url', 'paid_at', 'observacao',
    ];

    protected $casts = ['valor' => 'decimal:2', 'aprovada_em' => 'datetime', 'paid_at' => 'datetime'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function statusLabels(): array
    {
        return [self::APROVADA => 'Aprovada', self::COBRADA => 'Cobrada', self::PAGA => 'Paga', self::CANCELADA => 'Cancelada'];
    }
}
