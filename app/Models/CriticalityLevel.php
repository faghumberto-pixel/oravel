<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class CriticalityLevel extends Model {
    use HasUuids;
    protected $fillable = ['tenant_id', 'code', 'name', 'color'];
}
