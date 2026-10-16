<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Meta mensal de vendas de um vendedor (ou da empresa/equipe, quando `user_id` é nulo). */
class CrmMeta extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'crm_metas';

    protected static ?string $saasFeatureKey = 'tabela_crm_metas';

    protected static ?string $saasPermissionSlug = 'crm_meta';

    protected static ?string $saasModuleLabel = 'CRM - Metas';

    protected $fillable = ['tenant_id', 'user_id', 'mes', 'meta_valor', 'meta_ganhos', 'meta_visitas', 'observacoes'];

    protected $casts = ['mes' => 'date', 'meta_valor' => 'decimal:2', 'meta_ganhos' => 'integer', 'meta_visitas' => 'integer'];

    protected static function booted(): void
    {
        // A meta é sempre do mês inteiro: guarda o dia 1.
        static::saving(fn (CrmMeta $m) => $m->mes = Carbon::parse($m->mes)->startOfMonth());
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nomeAlvo(): string
    {
        return $this->vendedor?->name ?? 'Empresa / equipe';
    }
}
