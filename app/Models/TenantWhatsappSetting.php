<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Credenciais do WhatsApp (API oficial da Meta) da empresa; os números ficam em WhatsappNumero. Token e segredo criptografados. */
class TenantWhatsappSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'enabled', 'waba_id', 'access_token', 'app_secret', 'verify_token',
        'template_abertura', 'template_proposta', 'template_language', 'distribuicao', 'atendentes', 'ultimo_atendente_id',
        'last_test_at', 'last_test_ok', 'last_error',
    ];

    protected $hidden = ['access_token', 'app_secret'];

    protected $casts = [
        'enabled' => 'boolean',
        'atendentes' => 'array',
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'last_test_at' => 'datetime',
        'last_test_ok' => 'boolean',
    ];
}
