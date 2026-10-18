<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Cadastro fiscal da empresa cliente para emitir NFS-e (complemento "Nota fiscal de serviço"). Os tokens do emissor ficam
 * criptografados e nunca são serializados. O certificado A1 e a senha NÃO são guardados em lugar nenhum: o arquivo vai direto
 * ao emissor e só a validade fica aqui.
 */
class TenantFiscalSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const FEATURE = 'modulo_fiscal_nfse';

    protected $fillable = [
        'tenant_id', 'enabled', 'ambiente', 'padrao', 'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual', 'regime_tributario',
        'codigo_municipio', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf', 'email', 'telefone',
        'item_lista_servico', 'codigo_tributario_municipio', 'aliquota_iss', 'iss_retido', 'descricao_padrao', 'emissao_automatica', 'enviar_email_ao_autorizar',
        'provider_company_id', 'token_homologacao', 'token_producao', 'certificado_validade', 'certificado_enviado_em',
        'last_test_at', 'last_test_ok', 'last_error',
    ];

    protected $hidden = ['token_homologacao', 'token_producao', 'webhook_token'];

    protected $casts = [
        'enabled' => 'boolean',
        'iss_retido' => 'boolean',
        'emissao_automatica' => 'boolean',
        'enviar_email_ao_autorizar' => 'boolean',
        'aliquota_iss' => 'decimal:2',
        'token_homologacao' => 'encrypted',
        'token_producao' => 'encrypted',
        'certificado_validade' => 'date',
        'certificado_enviado_em' => 'datetime',
        'last_test_at' => 'datetime',
        'last_test_ok' => 'boolean',
    ];

    /** Campos que o emissor exige para emitir (rótulo por campo). */
    public const OBRIGATORIOS = [
        'cnpj' => 'CNPJ', 'razao_social' => 'Razão social', 'inscricao_municipal' => 'Inscrição municipal', 'regime_tributario' => 'Regime tributário',
        'codigo_municipio' => 'Código IBGE do município', 'cep' => 'CEP', 'logradouro' => 'Logradouro', 'numero' => 'Número', 'bairro' => 'Bairro',
        'municipio' => 'Município', 'uf' => 'UF', 'email' => 'E-mail', 'item_lista_servico' => 'Item da lista de serviço', 'aliquota_iss' => 'Alíquota do ISS',
        'descricao_padrao' => 'Descrição padrão do serviço',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->webhook_token ??= Str::random(48);
        });
    }

    /** O complemento "Nota fiscal de serviço" está no contrato da empresa? */
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

    /** Rótulos dos campos obrigatórios que ainda estão vazios. @return list<string> */
    public function faltando(): array
    {
        $falta = [];

        foreach (self::OBRIGATORIOS as $campo => $rotulo) {
            if (blank($this->{$campo})) {
                $falta[] = $rotulo;
            }
        }

        return $falta;
    }

    /** Token da empresa no emissor para o ambiente escolhido. */
    public function token(): ?string
    {
        return $this->ambiente === 'producao' ? $this->token_producao : $this->token_homologacao;
    }

    /** O certificado A1 enviado já passou da validade? (sem data conhecida, não bloqueia) */
    public function certificadoVencido(): bool
    {
        return $this->certificado_validade !== null && $this->certificado_validade->lt(today());
    }

    /** Pronta para emitir: ligada, cadastro completo, empresa no emissor e complemento no contrato. */
    public function pronta(): bool
    {
        return $this->enabled && filled($this->token()) && ! $this->certificadoVencido() && $this->faltando() === [] && static::moduloLiberado($this->tenant_id);
    }

    public function urlDoWebhook(): string
    {
        return url('/api/webhooks/fiscal/'.$this->tenant_id);
    }
}
