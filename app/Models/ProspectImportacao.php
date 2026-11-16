<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historico de importacoes de prospects feitas pela Central (dado da plataforma, so super admin; sem escopo de cliente). */
class ProspectImportacao extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'prospect_importacoes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['relatorio' => 'array', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }
}
