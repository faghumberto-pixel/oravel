<?php

namespace App\Relacionamento\Models;

use App\Models\Prospect;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pessoa identificada no ecossistema Oravel. Tabela GLOBAL (sem tenant_id), so' super admin na Central.
 * Fica fora de App\Models de proposito: nao e' tenant-owned e nao deve aparecer em tenant:audit-models.
 */
class RelContato extends Model
{
    use HasUuids;

    protected $table = 'rel_contatos';

    protected $guarded = [];

    protected $casts = [
        'visitor_tokens' => 'array',
        'consentimento_em' => 'datetime',
        'descadastrado_em' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class);
    }

    public function interacoes(): HasMany
    {
        return $this->hasMany(RelInteracao::class, 'contato_id');
    }
}
