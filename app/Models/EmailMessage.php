<?php

namespace App\Models;

use App\Mail\GenericPdfMail;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Caixa de e-mail (Enviados/Recebidos/Rascunhos). to_external e' a lista
 * crua de enderecos externos (cliente ou nao); destinatario interno (outro
 * usuario do mesmo tenant) fica em recipients() via pivot
 * email_message_recipients, que guarda leitura por pessoa. related
 * (polimorfico) liga a um Client/CrmLead quando disparado a partir da
 * ficha de um deles -- fica nulo em e-mail solto.
 *
 * Privacidade: um usuario so' enxerga e-mail onde ele e' remetente,
 * destinatario interno, ou dono do rascunho -- sem bypass de admin (e-mail
 * e' correspondencia). Isso e' reforcado nas queries de
 * App\Filament\Pages\CaixaDeEmail, nao aqui no model.
 */
class EmailMessage extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ENVIADO = 'enviado';

    public const STATUS_FALHOU = 'falhou';

    protected static ?string $saasFeatureKey = 'caixa_email';

    protected static ?string $saasPermissionSlug = 'email_message';

    protected static ?string $saasModuleLabel = 'Caixa de E-mail';

    protected $attributes = [
        'status' => self::STATUS_RASCUNHO,
    ];

    protected $fillable = [
        'tenant_id',
        'from_user_id',
        'to_external',
        'subject',
        'body',
        'status',
        'sent_at',
        'error',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'to_external' => 'array',
        'sent_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('anexos');
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADO => 'Enviado',
            self::STATUS_FALHOU => 'Falhou',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'email_message_recipients')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * POP unico do model: valida que ha' pelo menos 1 destinatario (interno
     * ou externo) e assunto preenchido, manda de verdade pro(s) endereco(s)
     * externo(s) (destinatario interno nao usa SMTP nesta v1, so' fica
     * registrado via recipients()) e guarda o resultado.
     */
    public function send(): void
    {
        if ($this->status === self::STATUS_ENVIADO) {
            throw new \RuntimeException('Este e-mail já foi enviado.');
        }

        if (trim((string) $this->subject) === '') {
            throw new \RuntimeException('Informe o assunto antes de enviar.');
        }

        $hasExternal = ! empty($this->to_external);
        $hasInternal = $this->recipients()->exists();

        if (! $hasExternal && ! $hasInternal) {
            throw new \RuntimeException('Adicione pelo menos um destinatário (interno ou externo) antes de enviar.');
        }

        if (! $hasExternal) {
            $this->update(['status' => self::STATUS_ENVIADO, 'sent_at' => now(), 'error' => null]);

            return;
        }

        $attachments = $this->getMedia('anexos')->map(fn ($media) => [
            'content' => file_get_contents($media->getPath()),
            'filename' => $media->file_name,
            'mime' => $media->mime_type,
        ])->all();

        try {
            Mail::to($this->to_external)->send(new GenericPdfMail(
                subjectLine: $this->subject,
                greeting: 'Olá!',
                bodyText: (string) $this->body,
                senderDisplayName: $this->fromUser?->tenant?->name,
                replyToAddress: $this->fromUser?->email,
                extraAttachments: $attachments,
            ));

            $this->update(['status' => self::STATUS_ENVIADO, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $this->update(['status' => self::STATUS_FALHOU, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function markReadFor(User $user): void
    {
        $alreadyRead = $this->recipients()
            ->wherePivot('user_id', $user->id)
            ->first()?->pivot?->read_at;

        if ($alreadyRead === null) {
            $this->recipients()->updateExistingPivot($user->id, ['read_at' => now()]);
        }
    }
}
