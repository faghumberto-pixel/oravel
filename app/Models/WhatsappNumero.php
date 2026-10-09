<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Número de WhatsApp (API oficial da Meta) de um usuário da empresa; sem usuário = número da empresa. */
class WhatsappNumero extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'whatsapp_numeros';

    protected $fillable = [
        'tenant_id', 'user_id', 'phone_number_id', 'display_phone', 'rotulo', 'access_token', 'enabled',
        'last_test_at', 'last_test_ok', 'last_error',
    ];

    protected $hidden = ['access_token'];

    protected $casts = ['enabled' => 'boolean', 'access_token' => 'encrypted', 'last_test_at' => 'datetime', 'last_test_ok' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nome(): string
    {
        return $this->user?->name ?? $this->rotulo ?? 'Número da empresa';
    }
}
