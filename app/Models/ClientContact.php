<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pessoa de contato de um cliente (espelho de CrmLeadContact). Sem Resource
 * próprio: gerida pelo ClientContactsRelationManager dentro do ClientResource.
 */
class ClientContact extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'name', 'role_title', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    protected static function booted(): void
    {
        // Só um contato principal por cliente: marcar um desmarca os outros.
        static::saved(function (ClientContact $contact) {
            if ($contact->is_primary) {
                static::withoutGlobalScopes()
                    ->where('client_id', $contact->client_id)
                    ->whereKeyNot($contact->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
