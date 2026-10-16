<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Support\Cnpj;
use App\Support\CrmCamposCustom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Conta = cliente do CRM (empresa ou pessoa), com vida própria: não depende
 * do cadastro de Clientes do ERP. `client_id` é só o vínculo opcional quando
 * a conta é enviada ao ERP (ver enviarParaErp()).
 */
class CrmConta extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    protected $table = 'crm_contas';

    protected static ?string $saasFeatureKey = 'tabela_crm_contas';

    protected static ?string $saasPermissionSlug = 'crm_conta';

    protected static ?string $saasModuleLabel = 'CRM - Contas';

    protected $fillable = [
        'tenant_id', 'nome', 'nome_fantasia', 'tipo_pessoa', 'documento', 'email', 'telefone', 'whatsapp', 'segmento',
        'cep', 'endereco', 'cidade', 'uf', 'latitude', 'longitude', 'responsavel_user_id', 'origem', 'observacoes',
        'ativa', 'custom', 'client_id',
    ];

    protected $casts = [
        'ativa' => 'boolean',
        'custom' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::saving(function (CrmConta $conta) {
            $digitos = Cnpj::limpar($conta->documento);
            $conta->documento_digitos = in_array(strlen($digitos), [11, 14], true) ? $digitos : null;
            $conta->tipo_pessoa = strlen($digitos) === 11 ? 'pf' : ($conta->tipo_pessoa ?: 'pj');

            if ($conta->isDirty('custom') || ! $conta->exists) {
                $conta->custom = CrmCamposCustom::normalizar('conta', $conta->custom, $conta->tenant_id);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(CrmContaContato::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'crm_conta_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Conta do tenant com este CNPJ/CPF (só dígitos), ignorando o escopo de usuário. */
    public static function porDocumento(string $tenantId, ?string $documento): ?self
    {
        $digitos = Cnpj::limpar($documento);

        return $digitos === '' ? null : static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('documento_digitos', $digitos)->first();
    }

    /**
     * Envia a conta para o cadastro de Clientes do ERP (opcional, só para quem tem o módulo): cria o Cliente
     * com contatos e campos personalizados de mesma chave e tipo, e grava o vínculo. Idempotente.
     */
    public function enviarParaErp(): Client
    {
        if ($this->client_id && ($existente = Client::withoutGlobalScopes()->find($this->client_id))) {
            return $existente;
        }

        $principal = $this->contatos()->first();

        $client = new Client([
            'name' => $this->nome,
            'fantasy_name' => $this->nome_fantasia,
            'document' => $this->documento,
            'cpf_cnpj' => $this->documento,
            'contact_name' => $principal?->name,
            'email' => $this->email,
            'phone' => $this->telefone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->endereco,
            'city' => $this->cidade,
            'uf' => $this->uf,
            'state' => $this->uf,
            'cep' => $this->cep,
            'zip_code' => $this->cep,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'custom' => $this->customParaCliente(),
        ]);
        $client->tenant_id = $this->tenant_id;
        $client->save();

        foreach ($this->contatos as $contato) {
            ClientContact::create([
                'tenant_id' => $this->tenant_id, 'client_id' => $client->id, 'name' => $contato->name,
                'role_title' => $contato->role_title, 'phone' => $contato->phone, 'email' => $contato->email,
                'is_primary' => $contato->is_primary,
            ]);
        }

        $this->forceFill(['client_id' => $client->id])->saveQuietly();

        return $client;
    }

    /** Valores `custom` da conta que têm campo de cliente (ERP) com a mesma chave e o mesmo tipo. */
    private function customParaCliente(): array
    {
        $doCliente = CrmCamposCustom::campos('cliente', false, (string) $this->tenant_id)->keyBy('chave');
        $daConta = CrmCamposCustom::campos('conta', false, (string) $this->tenant_id)->keyBy('chave');

        return collect((array) $this->custom)
            ->filter(fn ($v, $chave) => $doCliente->has($chave) && $daConta->has($chave) && $doCliente[$chave]->tipo === $daConta[$chave]->tipo)
            ->all();
    }
}
