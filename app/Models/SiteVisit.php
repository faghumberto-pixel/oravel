<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sessao de visita capturada pelo TrackSiteVisit -- de proposito SEM
 * BelongsToTenant: e' consultada de forma cross-tenant no painel central
 * (ver SiteVisitResource), e tenant_id/user_id sao preenchidos pela
 * resolucao propria do middleware (SiteVisitTenantResolver), nao pelo
 * "tenant atuante" do super admin.
 */
class SiteVisit extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'visitor_token',
        'session_token',
        'ip_address',
        'user_agent',
        'device_type',
        'referrer_url',
        'referrer_host',
        'landing_path',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'entry_panel',
        'page_views',
        'started_at',
        'last_activity_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeInPeriod(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }
}
