<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de uma importação real de planilha do CRM (tabela crm_importacoes). Sem Resource: aparece na própria página de importação. */
class CrmImportacao extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_importacoes';

    protected $fillable = ['tenant_id', 'user_id', 'destino', 'arquivo', 'criados', 'atualizados', 'ignorados', 'erros', 'avisos', 'relatorio'];

    protected $casts = ['relatorio' => 'array'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
