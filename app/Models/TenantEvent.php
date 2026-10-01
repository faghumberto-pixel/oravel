<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fato da relação entre um cliente (Tenant) e a Oravel, mostrado na linha do tempo da Central. */
class TenantEvent extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const CLIENTE_CADASTRADO = 'cliente_cadastrado';

    public const CONTRATO_ALTERADO = 'contrato_alterado';

    public const CONTRATO_LINK = 'contrato_link_gerado';

    public const CONTRATO_ASSINADO = 'contrato_assinado';

    public const CHECKOUT_CRIADO = 'checkout_criado';

    public const CHECKOUT_PAGO = 'checkout_pago';

    public const CHECKOUT_CANCELADO = 'checkout_cancelado';

    public const MENSALIDADE_PAGA = 'mensalidade_paga';

    public const MENSALIDADE_ATRASADA = 'mensalidade_atrasada';

    public const PAGAMENTO_CANCELADO = 'pagamento_cancelado';

    public const IMPLANTACAO_COBRADA = 'implantacao_cobrada';

    public const IMPLANTACAO_PAGA = 'implantacao_paga';

    public const IMPLANTACAO_ATRASADA = 'implantacao_atrasada';

    public const IMPLANTACAO_CANCELADA = 'implantacao_cancelada';

    public const ACESSO_LIBERADO = 'acesso_liberado';

    public const ASSINATURA_CRIADA = 'assinatura_criada';

    public const ANOTACAO = 'anotacao';

    protected $fillable = [
        'tenant_id', 'event_type', 'title', 'description', 'properties', 'dedupe_key', 'actor_user_id', 'occurred_at',
    ];

    protected $casts = ['properties' => 'array', 'occurred_at' => 'datetime'];

    /** @return array<string, array{label: string, color: string}> */
    public static function types(): array
    {
        return [
            self::CLIENTE_CADASTRADO => ['label' => 'Cliente cadastrado', 'color' => 'gray'],
            self::CONTRATO_ALTERADO => ['label' => 'Contrato alterado', 'color' => 'gray'],
            self::CONTRATO_LINK => ['label' => 'Link do contrato gerado', 'color' => 'info'],
            self::CONTRATO_ASSINADO => ['label' => 'Contrato assinado', 'color' => 'success'],
            self::CHECKOUT_CRIADO => ['label' => 'Checkout criado', 'color' => 'info'],
            self::CHECKOUT_PAGO => ['label' => 'Checkout pago', 'color' => 'success'],
            self::CHECKOUT_CANCELADO => ['label' => 'Checkout cancelado/expirado', 'color' => 'danger'],
            self::MENSALIDADE_PAGA => ['label' => 'Mensalidade paga', 'color' => 'success'],
            self::MENSALIDADE_ATRASADA => ['label' => 'Mensalidade atrasada', 'color' => 'danger'],
            self::PAGAMENTO_CANCELADO => ['label' => 'Pagamento cancelado', 'color' => 'danger'],
            self::IMPLANTACAO_COBRADA => ['label' => 'Implantação cobrada', 'color' => 'info'],
            self::IMPLANTACAO_PAGA => ['label' => 'Implantação paga', 'color' => 'success'],
            self::IMPLANTACAO_ATRASADA => ['label' => 'Implantação atrasada', 'color' => 'danger'],
            self::IMPLANTACAO_CANCELADA => ['label' => 'Implantação cancelada', 'color' => 'gray'],
            self::ACESSO_LIBERADO => ['label' => 'Acesso liberado', 'color' => 'success'],
            self::ASSINATURA_CRIADA => ['label' => 'Assinatura mensal criada', 'color' => 'info'],
            self::ANOTACAO => ['label' => 'Anotação', 'color' => 'warning'],
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
