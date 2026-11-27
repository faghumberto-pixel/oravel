<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de documento da empresa (orçamento, proposta ou contrato): cabeçalho, seções ou cláusulas, termos e rodapé no formato que o cliente
 * já usa. Pode ser montado à mão ou importado de um arquivo dele (ver DocumentoModeloImportador). Ao imprimir ou enviar um documento, vale o
 * modelo padrão do tipo; sem modelo, vale o padrão do sistema.
 */
class DocumentoModelo extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_documento_modelos';

    protected static ?string $saasPermissionSlug = 'documento_modelo';

    protected static ?string $saasModuleLabel = 'Modelos de Documento';

    public const TIPO_ORCAMENTO = 'orcamento';

    public const TIPO_PROPOSTA = 'proposta';

    public const TIPO_CONTRATO = 'contrato';

    /** @return array<string, string> */
    public static function tipos(): array
    {
        return [self::TIPO_ORCAMENTO => 'Orçamento', self::TIPO_PROPOSTA => 'Proposta', self::TIPO_CONTRATO => 'Contrato'];
    }

    protected $fillable = [
        'tenant_id', 'tipo', 'nome', 'cabecalho', 'rodape', 'termos', 'validade_dias', 'campos', 'logo_path', 'arquivo_referencia', 'is_default', 'is_active',
    ];

    protected $casts = ['campos' => 'array', 'is_default' => 'boolean', 'is_active' => 'boolean', 'validade_dias' => 'integer'];

    protected static function booted(): void
    {
        // Um só padrão por tipo e empresa: marcar este desmarca os outros do mesmo tipo.
        static::saved(function (DocumentoModelo $m) {
            if ($m->is_default) {
                static::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->where('tipo', $m->tipo)->where('id', '!=', $m->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * O modelo que vale para imprimir ou enviar um documento do tipo: o padrão ativo da empresa, ou null (usa o padrão do sistema).
     * Com $tenantId (o dono do documento), busca explicitamente nele: em fila, assinatura ou console não há usuário logado e o escopo
     * por empresa não filtra, então sem isso o modelo de outra empresa poderia ser escolhido.
     */
    public static function padraoDe(string $tipo, ?string $tenantId = null): ?self
    {
        $consulta = $tenantId ? static::withoutGlobalScopes()->where('tenant_id', $tenantId) : static::query();

        return $consulta->ativos()->where('tipo', $tipo)->where('is_default', true)->first();
    }
}
