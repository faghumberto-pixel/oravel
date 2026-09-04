<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DocumentSignature extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;
    use HasSaaSMetadata;
    use LogsActivity;

    protected static ?string $saasFeatureKey = 'assinatura_eletronica';
    protected static ?string $saasPermissionSlug = 'assinatura';
    protected static ?string $saasModuleLabel = 'Assinatura Eletrônica';

    protected $fillable = [
        'tenant_id',
        'signable_type',
        'signable_id',
        'token',
        'signer_name',
        'signer_document',
        'signer_email',
        'signer_phone',
        'signature_image_path',
        'ip_address',
        'user_agent',
        'geolocation',
        'signed_at',
        'status',
        'expires_at',
        'document_hash',
    ];

    protected $casts = [
        'geolocation' => 'json',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = auth()->user()?->tenant_id;
            }

            // Gera token único de 64 caracteres se não fornecido
            if (empty($model->token)) {
                $model->token = bin2hex(random_bytes(32));
            }

            // Define expiração padrão em 30 dias
            if (empty($model->expires_at)) {
                $model->expires_at = now()->addDays(30);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    // ========== RELACIONAMENTOS ==========

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }

    // ========== ACESSORES E MUTADORES ==========

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsSignedAttribute(): bool
    {
        return $this->status === 'signed';
    }

    public function getCanSignAttribute(): bool
    {
        return $this->is_pending && !$this->is_expired;
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Gera hash SHA-256 do documento PDF para auditoria.
     */
    public function generateDocumentHash(string $pdfContent): string
    {
        return hash('sha256', $pdfContent);
    }

    /**
     * Marca assinatura como assinada.
     */
    public function markAsSigned(): void
    {
        $this->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    /**
     * Marca assinatura como expirada.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Marca assinatura como cancelada.
     */
    public function markAsCanceled(): void
    {
        $this->update(['status' => 'canceled']);
    }
}
