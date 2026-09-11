<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageLead extends Model
{
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
