<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CriticalityHistory extends Model {
    protected $fillable = ['asset_id', 'tenant_id', 'old_level', 'new_level', 'origin'];
}
