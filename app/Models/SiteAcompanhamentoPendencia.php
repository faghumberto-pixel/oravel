<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Acompanhamento da remodelagem do site institucional (painel Central). Tabela GLOBAL: controle interno
 * da Oravel, sem tenant_id, so' acessivel a super admin pelo painel Central.
 */
class SiteAcompanhamentoPendencia extends Model
{
    use HasUuids;

    protected $table = 'site_acompanhamento_pendencias';

    protected $guarded = [];

    protected $casts = ['prazo' => 'date'];
}
