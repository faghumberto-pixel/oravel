<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;
    
    protected $fillable = ['tenant_id', 'technician_id', 'assunto', 'descricao', 'urgente', 'completed', 'scheduled_at'];
    protected $casts = ['scheduled_at' => 'datetime', 'urgente' => 'boolean', 'completed' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
