<?php

namespace App\Domain\Intelligence\Trial\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Eventos do Asaas já tratados (idempotência: o Asaas reenvia). */
class IntelligenceWebhookEvento extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'intelligence_webhook_eventos';

    protected $fillable = ['chave', 'evento', 'trial_id'];
}
