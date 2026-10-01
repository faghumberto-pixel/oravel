<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Curso da Academia Oravel. Conteudo GLOBAL (sem tenant_id), publicado pela
 * Oravel na Central -- igual Announcement/Plan. O progresso de cada usuario fica
 * em LessonProgress (esse sim, por tenant). O modulo e' ligado por contrato.
 */
class Course extends Model
{
    use HasSaaSMetadata, HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_courses';

    protected static ?string $saasPermissionSlug = 'curso';

    protected static ?string $saasModuleLabel = 'Academia';

    protected $fillable = ['title', 'slug', 'description', 'cover_path', 'position', 'is_published'];

    protected $casts = ['is_published' => 'boolean'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /** Curso publicado que ainda tem ao menos uma aula liberada pro contrato do cliente logado. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereHas('lessons', fn (Builder $q) => $q->available());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
