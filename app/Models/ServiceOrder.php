<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSaaSMetadata;

class ServiceOrder extends Model
{
    use HasFactory;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_service_orders";
    protected $guarded = [];
}
