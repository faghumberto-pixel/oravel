<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;

/**
 * Biblioteca de itens reutilizaveis (Extintor, Freios, CRLV, Cinto...). Cada grupo referencia o item via
 * CatalogoRequisito e pode sobrescrever o efeito (IMP num grupo, ALR em outro) sem duplicar o item.
 * E a "raiz" SaaS do catalogo: a chave abaixo fica DESLIGADA por padrao nos contratos (saas:sync-modules
 * grava false para chaves novas) e a permissao editar_catalogo_equipamentos = "gerenciar catalogo".
 */
class CatalogoItem extends CatalogoModel
{
    use BelongsToTenant; // declarado aqui tambem: tenant:audit-models so enxerga trait direto (o escopo vem de CatalogoModel)
    use HasSaaSMetadata;

    public const FEATURE_KEY = 'tabela_catalogo_equipamentos';

    protected static ?string $saasFeatureKey = self::FEATURE_KEY;

    protected static ?string $saasPermissionSlug = 'catalogo_equipamentos';

    protected static ?string $saasModuleLabel = 'Catálogo de Equipamentos';

    protected $table = 'catalogo_itens';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['parametros' => 'array']);
    }
}
