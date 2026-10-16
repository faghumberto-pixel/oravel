<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Support\CrmCamposCustom;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Definição de um campo personalizado (tabela crm_campos_custom). O valor mora em `custom` do lead/cliente. */
class CrmCampoCustom extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const ENTIDADE_LEAD = 'lead';

    public const ENTIDADE_CLIENTE = 'cliente';

    public const ENTIDADE_CONTA = 'conta';

    public const TIPO_TEXTO = 'texto';

    public const TIPO_TEXTO_LONGO = 'texto_longo';

    public const TIPO_NUMERO = 'numero';

    public const TIPO_MOEDA = 'moeda';

    public const TIPO_DATA = 'data';

    public const TIPO_SIM_NAO = 'sim_nao';

    public const TIPO_LISTA = 'lista';

    public const TIPO_LISTA_MULTIPLA = 'lista_multipla';

    protected $table = 'crm_campos_custom';

    protected static ?string $saasFeatureKey = 'tabela_crm_campos_custom';

    protected static ?string $saasPermissionSlug = 'crm_campo_custom';

    protected static ?string $saasModuleLabel = 'CRM - Campos Personalizados';

    protected $fillable = ['tenant_id', 'entidade', 'nome', 'chave', 'tipo', 'opcoes', 'obrigatorio', 'ordem', 'ativo'];

    protected $casts = ['opcoes' => 'array', 'obrigatorio' => 'boolean', 'ativo' => 'boolean', 'ordem' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (CrmCampoCustom $campo) {
            if (blank($campo->chave)) {
                $campo->chave = $campo->chaveUnica();
            }
        });

        // A lista de campos é memorizada por request: qualquer mudança a invalida.
        static::saved(fn () => CrmCamposCustom::esquecer());
        static::deleted(fn () => CrmCamposCustom::esquecer());
    }

    public static function entidadeLabels(): array
    {
        return [self::ENTIDADE_LEAD => 'Leads', self::ENTIDADE_CONTA => 'Contas (clientes do CRM)', self::ENTIDADE_CLIENTE => 'Clientes do ERP'];
    }

    /** Entidades que o tenant pode personalizar: Leads e Contas sempre; Clientes do ERP só com o módulo. */
    public static function entidadesDisponiveis(): array
    {
        $todas = self::entidadeLabels();

        if (! Tenancy::current()?->hasFeature('tabela_clients')) {
            unset($todas[self::ENTIDADE_CLIENTE]);
        }

        return $todas;
    }

    public static function tipoLabels(): array
    {
        return [
            self::TIPO_TEXTO => 'Texto curto',
            self::TIPO_TEXTO_LONGO => 'Texto longo',
            self::TIPO_NUMERO => 'Número',
            self::TIPO_MOEDA => 'Valor em R$',
            self::TIPO_DATA => 'Data',
            self::TIPO_SIM_NAO => 'Sim / Não',
            self::TIPO_LISTA => 'Lista (uma opção)',
            self::TIPO_LISTA_MULTIPLA => 'Lista (várias opções)',
        ];
    }

    public function isLista(): bool
    {
        return in_array($this->tipo, [self::TIPO_LISTA, self::TIPO_LISTA_MULTIPLA], true);
    }

    /** @return array<string, string> valor => rótulo (as opções são texto livre, o valor guardado é o próprio texto). */
    public function opcoesLabels(): array
    {
        $opcoes = array_values(array_filter(array_map('trim', (array) $this->opcoes), 'strlen'));

        return array_combine($opcoes, $opcoes) ?: [];
    }

    public function scopeParaEntidade(Builder $query, string $entidade): Builder
    {
        return $query->where('entidade', $entidade)->orderBy('ordem')->orderBy('nome');
    }

    /** Slug do nome, com sufixo numérico se o tenant já tem essa chave nessa entidade. */
    private function chaveUnica(): string
    {
        $base = Str::slug((string) $this->nome, '_') ?: 'campo';
        $chave = $base;
        $i = 2;

        while (static::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->where('entidade', $this->entidade)->where('chave', $chave)->exists()) {
            $chave = $base.'_'.$i++;
        }

        return $chave;
    }

    /** Quantos registros (leads ou clientes) já têm esse campo preenchido. */
    public function emUso(): int
    {
        $model = match ($this->entidade) {
            self::ENTIDADE_LEAD => CrmLead::class,
            self::ENTIDADE_CONTA => CrmConta::class,
            default => Client::class,
        };

        return $model::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->whereNotNull("custom->{$this->chave}")
            ->whereRaw("custom->>'{$this->chave}' <> ''")
            ->count();
    }
}
