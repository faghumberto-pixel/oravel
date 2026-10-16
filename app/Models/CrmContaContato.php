<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pessoa de contato de uma conta do CRM (espelho de ClientContact). Gerida pelo relation manager da conta. */
class CrmContaContato extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_conta_contatos';

    protected $fillable = ['tenant_id', 'crm_conta_id', 'name', 'role_title', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    protected static function booted(): void
    {
        // Só um contato principal por conta: marcar um desmarca os outros.
        static::saved(function (CrmContaContato $contato) {
            if ($contato->is_primary) {
                static::withoutGlobalScopes()
                    ->where('crm_conta_id', $contato->crm_conta_id)
                    ->whereKeyNot($contato->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CrmConta::class, 'crm_conta_id');
    }
}
