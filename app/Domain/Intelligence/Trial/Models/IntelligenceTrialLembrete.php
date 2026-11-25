<?php

namespace App\Domain\Intelligence\Trial\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IntelligenceTrialLembrete extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'intelligence_trial_lembretes';

    protected $fillable = ['trial_id', 'marco', 'canal', 'enviado_em'];

    protected $casts = ['enviado_em' => 'datetime'];
}
