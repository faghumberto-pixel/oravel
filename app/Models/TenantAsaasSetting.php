<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Conta Asaas da PRÓPRIA empresa cliente (cobrança automática dos clientes dela). A chave fica criptografada e
 * nunca é serializada. Não confundir com config('services.asaas'), que é a conta da Oravel para cobrar as empresas.
 */
class TenantAsaasSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const FEATURE = 'modulo_cobranca_automatica';

    protected $fillable = [
        'tenant_id', 'enabled', 'ambiente', 'api_key', 'webhook_token', 'forma_padrao', 'multa_percentual', 'juros_mensal', 'gerar_recorrentes', 'cobrar_automaticamente', 'dias_antecedencia',
        'last_test_at', 'last_test_ok', 'last_error',
    ];

    protected $hidden = ['api_key', 'webhook_token'];

    protected $casts = [
        'enabled' => 'boolean',
        'gerar_recorrentes' => 'boolean',
        'cobrar_automaticamente' => 'boolean',
        'dias_antecedencia' => 'integer',
        'api_key' => 'encrypted',
        'multa_percentual' => 'decimal:2',
        'juros_mensal' => 'decimal:2',
        'last_test_at' => 'datetime',
        'last_test_ok' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->webhook_token ??= Str::random(48);
        });
    }

    /** O complemento "Cobrança automática" está no contrato da empresa? */
    public static function moduloLiberado(?string $tenantId): bool
    {
        if (blank($tenantId)) {
            return false;
        }

        return (bool) Tenant::withoutGlobalScopes()->with('plan')->find($tenantId)?->plan?->hasFeature(self::FEATURE);
    }

    /** Configuração da empresa (sem escopo de usuário: serve a webhook, comandos e telas). */
    public static function daEmpresa(?string $tenantId): ?self
    {
        return blank($tenantId) ? null : static::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
    }

    /** Pronta para cobrar: ligada, com chave e com o complemento no contrato. */
    public function pronta(): bool
    {
        return $this->enabled && filled($this->api_key) && static::moduloLiberado($this->tenant_id);
    }

    public function urlDoWebhook(): string
    {
        return url('/api/webhooks/asaas-empresa/'.$this->tenant_id);
    }
}
