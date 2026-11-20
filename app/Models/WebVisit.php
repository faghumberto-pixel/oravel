<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Visita (sessão) a um site da Oravel (oravel.com.br ou oravel.org, ver coluna site). Dado de analytics global, sem tenant. */
class WebVisit extends Model
{
    use HasUuids;

    public const SITE_INSTITUCIONAL = 'oravel.com.br';

    public const SITE_ORG = 'oravel.org';

    /** @return array<string, string> */
    public static function sites(): array
    {
        return [self::SITE_INSTITUCIONAL => 'oravel.com.br (institucional)', self::SITE_ORG => 'oravel.org (conteúdo)'];
    }

    /** Só o site institucional: os painéis de campanha/leads existentes olham apenas para ele. */
    public function scopeInstitucional(Builder $query): Builder
    {
        return $query->where($query->getModel()->getTable().'.site', self::SITE_INSTITUCIONAL);
    }

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
