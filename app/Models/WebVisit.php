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

    /** Só as visitas de um site (coluna site). */
    public function scopeDoSite(Builder $query, string $site): Builder
    {
        return $query->where($query->getModel()->getTable().'.site', $site);
    }

    /**
     * Filtros do painel (todos opcionais): device (desktop|mobile|tablet), uf, origem (host de referencia, utm_source ou
     * "direto") e retorno (novo|voltou). Chave ausente ou vazia nao filtra nada.
     *
     * @param  array<string, mixed>|null  $f
     */
    public function scopeFiltrado(Builder $query, ?array $f): Builder
    {
        $f ??= [];
        $t = $query->getModel()->getTable();

        return $query
            ->when(filled($f['device'] ?? null), fn ($q) => $q->where("{$t}.device_type", $f['device']))
            ->when(filled($f['uf'] ?? null), fn ($q) => $q->where("{$t}.state", $f['uf']))
            ->when(($f['origem'] ?? null) === 'direto', fn ($q) => $q->whereNull("{$t}.referrer_host")->whereNull("{$t}.utm_source"))
            ->when(filled($f['origem'] ?? null) && $f['origem'] !== 'direto', fn ($q) => $q->where(fn ($w) => $w->where("{$t}.referrer_host", $f['origem'])->orWhere("{$t}.utm_source", $f['origem'])))
            ->when(($f['retorno'] ?? null) === 'novo', fn ($q) => $q->where("{$t}.is_returning", false))
            ->when(($f['retorno'] ?? null) === 'voltou', fn ($q) => $q->where("{$t}.is_returning", true));
    }

    /** Só o site institucional: os painéis de campanha/leads existentes olham apenas para ele. */
    public function scopeInstitucional(Builder $query): Builder
    {
        return $this->scopeDoSite($query, self::SITE_INSTITUCIONAL);
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
