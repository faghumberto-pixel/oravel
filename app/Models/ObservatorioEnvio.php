<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Outbox de eventos para o Observatorio (tabela global, so' super admin). */
class ObservatorioEnvio extends Model
{
    use HasUuids;

    public const STATUS = [
        'pendente' => 'Pendente', 'enviado' => 'Enviado', 'falhou' => 'Falhou (esgotou tentativas)',
        'rejeitado' => 'Rejeitado pelo Observatório', 'versao_desatualizada' => 'Pesquisa mudou (refazer)',
    ];

    protected $table = 'observatorio_envios';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'proxima_tentativa_em' => 'datetime', 'enviado_em' => 'datetime'];

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }
}
