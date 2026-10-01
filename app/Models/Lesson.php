<?php

namespace App\Models;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aula de um Course. Global, como o curso (sem tenant_id).
 */
class Lesson extends Model
{
    use HasUuids;

    protected $fillable = ['course_id', 'title', 'summary', 'body', 'page_url', 'video_url', 'attachment_path', 'feature_key', 'position'];

    /**
     * So' as aulas que o cliente logado pode ver: sem modulo amarrado (vale pra todos) ou
     * cujo modulo esta no contrato dele. Sem tenant (super admin sem tenant atuante) ve tudo.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return $query;
        }

        $allowed = collect(array_keys(Plan::getAvailableFeaturesOptions()))
            ->filter(fn (string $key) => $tenant->hasFeature($key))
            ->values()
            ->all();

        return $query->where(fn (Builder $q) => $q->whereNull('feature_key')->orWhereIn('feature_key', $allowed));
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * URL pronta pra <iframe> a partir de um link do YouTube/Vimeo; null se nao reconhecer
     * (so esses dois hosts sao aceitos, nunca um iframe com URL arbitraria).
     */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;

        if (preg_match('~(?:youtube\.com/watch\?(?:.*&)?v=|youtu\.be/|youtube\.com/embed/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }
}
