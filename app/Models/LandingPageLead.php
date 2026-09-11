<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;

class LandingPageLead extends Model
{
    use HasSaaSMetadata;

    protected static string $saasFeatureKey = 'landing_page_leads';
    protected static string $saasPermissionSlug = 'lead';
    protected static string $saasModuleLabel = 'Leads da Landing Page';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'segment',
        'product',
        'status',
        'notes',
        'contacted_at',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];
}
