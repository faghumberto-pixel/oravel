<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Lista editável por tenant (tabela crm_origens). O lead guarda só o slug, em texto. */
class CrmOrigem extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'crm_origens';

    protected static ?string $saasFeatureKey = 'tabela_crm_origens';

    protected static ?string $saasPermissionSlug = 'crm_origem';

    protected static ?string $saasModuleLabel = 'CRM - Origens de Lead';

    protected $fillable = ['tenant_id', 'nome', 'slug', 'ordem', 'ativo'];

    protected $casts = ['ativo' => 'boolean', 'ordem' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function () {
            if (blank($m->slug)) {
                $m->slug = Str::slug($m->nome, '_');
            }
        });
    }

    /** @return array<string, string> slug => nome, só os ativos, na ordem. */
    public static function opcoes(): array
    {
        return static::query()->where('ativo', true)->orderBy('ordem')->orderBy('nome')->pluck('nome', 'slug')->all();
    }
}
