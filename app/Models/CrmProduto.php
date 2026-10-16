<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Catálogo simples de produtos e serviços do CRM (independente de Materiais/Ativos do ERP). */
class CrmProduto extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const TIPO_PRODUTO = 'produto';

    public const TIPO_SERVICO = 'servico';

    protected $table = 'crm_produtos';

    protected static ?string $saasFeatureKey = 'tabela_crm_produtos';

    protected static ?string $saasPermissionSlug = 'crm_produto';

    protected static ?string $saasModuleLabel = 'CRM - Produtos e Serviços';

    protected $fillable = ['tenant_id', 'codigo', 'nome', 'tipo', 'unidade', 'preco', 'descricao', 'ativo'];

    protected $casts = ['preco' => 'decimal:2', 'ativo' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(fn (CrmProduto $p) => $p->codigo = filled($p->codigo) ? trim($p->codigo) : null);
    }

    public static function tipoLabels(): array
    {
        return [self::TIPO_PRODUTO => 'Produto', self::TIPO_SERVICO => 'Serviço'];
    }

    public static function unidadeLabels(): array
    {
        return ['un' => 'Unidade', 'h' => 'Hora', 'dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês', 'ano' => 'Ano', 'km' => 'Km', 'm2' => 'm²', 'm3' => 'm³', 'kg' => 'Kg', 'cx' => 'Caixa', 'vb' => 'Verba'];
    }
}
