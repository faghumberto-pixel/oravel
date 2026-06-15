<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSaaSMetadata;

class Material extends Model
{
    use HasFactory;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_materials";
    protected $guarded = [];
}
