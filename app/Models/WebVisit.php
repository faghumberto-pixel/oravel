<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Visita (sessão) ao site institucional oravel.com.br. Dado de analytics global, sem tenant. */
class WebVisit extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_returning' => 'boolean',
        'page_views' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function pageviews(): HasMany
    {
        return $this->hasMany(WebPageview::class)->orderBy('entered_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebEvent::class)->orderBy('occurred_at');
    }

    /** Origem legível: UTM, senão domínio de referência, senão "Direto". */
    public function sourceLabel(): string
    {
        if ($this->utm_source) {
            return $this->utm_source.($this->utm_medium ? " / {$this->utm_medium}" : '');
        }

        return $this->referrer_host ?: 'Direto';
    }
}
