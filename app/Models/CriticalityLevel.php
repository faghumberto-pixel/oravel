<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class CriticalityLevel extends Model {
    use \App\Traits\BelongsToTenant;
    use HasUuids;
    protected $fillable = ['tenant_id', 'code', 'name', 'color'];
}
