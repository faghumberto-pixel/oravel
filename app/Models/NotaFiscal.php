<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NFS-e emitida (ou em emissão) a partir de uma conta a receber. `ref` é a chave de idempotência no emissor. */
class NotaFiscal extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'notas_fiscais';

    public const RASCUNHO = 'rascunho';

    public const PROCESSANDO = 'processando';

    public const AUTORIZADA = 'autorizada';

    public const ERRO = 'erro';

    public const CANCELADA = 'cancelada';

    protected $fillable = [
        'tenant_id', 'account_receivable_id', 'client_id', 'contract_id', 'user_id', 'ref', 'ambiente', 'status', 'valor', 'tomador', 'discriminacao',
        'numero', 'serie', 'codigo_verificacao', 'pdf_url', 'xml_url', 'erro', 'emitida_em', 'cancelada_em', 'cancelamento_motivo', 'enviada_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'tomador' => 'array',
        'emitida_em' => 'datetime',
        'cancelada_em' => 'datetime',
        'enviada_em' => 'datetime',
    ];

    public function contaAReceber(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class, 'account_receivable_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function estaViva(): bool
    {
        return in_array($this->status, [self::RASCUNHO, self::PROCESSANDO, self::AUTORIZADA], true);
    }
}
