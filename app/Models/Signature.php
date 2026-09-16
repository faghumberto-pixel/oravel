<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $fillable = [
        'company',
        'email',
        'name',
        'signed_at',
        'hash',
        'ip_origin',
        'user_agent',
        'metadata',
        'email_sent',
        'email_sent_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'metadata' => 'json',
        'email_sent' => 'boolean',
    ];
}
