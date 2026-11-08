<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Secao de um checklist. Nao e modulo SaaS proprio: herda chave e permissao de CkTemplate. */
class CkSection extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ck_sections';

    protected $guarded = [];

    protected $casts = ['ordem' => 'integer'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CkTemplate::class, 'template_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CkItem::class, 'section_id')->orderBy('ordem');
    }
}
