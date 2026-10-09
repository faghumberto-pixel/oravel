<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quem recebe cada tipo de aviso numa empresa (tenant). A empresa escolhe
 * pessoas, não departamentos: nem todo cliente tem Comercial, Suprimentos etc.
 * Ver App\Services\DestinatariosAvisos.
 */
class AvisoResponsavel extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'aviso_responsaveis';

    protected $fillable = ['tenant_id', 'evento', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
