<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Caixa de e-mail PRÓPRIA da empresa (tenant). Quando ativa, os e-mails
 * da empresa saem por ela, com o endereço dela, e não pela caixa da Oravel.
 * A senha é guardada criptografada e nunca volta para a tela.
 */
class TenantMailSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'enabled', 'host', 'port', 'security', 'username', 'password',
        'from_address', 'from_name', 'last_test_at', 'last_test_ok', 'last_error',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'password' => 'encrypted',
        'last_test_at' => 'datetime',
        'last_test_ok' => 'boolean',
    ];
}
