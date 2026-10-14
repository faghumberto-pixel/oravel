<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceOrderChecklist extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'maintenance_order_checklists';

    protected $fillable = [
        'maintenance_order_id',
        'category',
        'item_name',
        'instructions',
        'is_completed',
        'status',
        'notes',
        'department_id',
        'code',
        'is_template',
        'checklist_group_id',
        'asset_id',
        'checklist_type',
        'section',
        'tenant_id',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_template' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }

            if ($model->status && ! $model->is_template) {
                $model->is_completed = in_array($model->status, ['conforme', 'nao_aplicavel'], true);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('status') && ! $model->is_template) {
                $model->is_completed = in_array($model->status, ['conforme', 'nao_aplicavel'], true);
            }
        });

        // Item Não Conforme abre uma Pendência na OS (uma por item), que avisa
        // os responsáveis pelo evento "pendencia_os".
        static::saved(function (MaintenanceOrderChecklist $item) {
            if ($item->status === 'nao_conforme' && ($item->wasRecentlyCreated || $item->wasChanged('status'))) {
                $item->openPendencia();
            }
        });
    }

    /**
     * Respondido = o técnico decidiu o item (qualquer status) ou o marcou
     * como feito na tela simples. É o que conta para o progresso do checklist.
     */
    public function isAnswered(): bool
    {
        return filled($this->status) || $this->is_completed;
    }

    /**
     * Abre a Pendência do item Não Conforme, sem duplicar: se o item voltar a
     * Não Conforme depois de corrigido, não abre outra igual.
     */
    public function openPendencia(): void
    {
        if ($this->is_template || ! $this->maintenance_order_id) {
            return;
        }

        $titulo = 'Checklist — item não conforme: '.($this->item_name ?: $this->category);

        $jaExiste = MaintenanceOrderPendencia::withoutGlobalScopes()
            ->where('maintenance_order_id', $this->maintenance_order_id)
            ->where('description', 'like', $titulo.'%')
            ->exists();

        if ($jaExiste) {
            return;
        }

        // "Aberta por" é obrigatório: quem mexeu no item ou, sem usuário logado
        // (ex.: sincronização), o técnico da OS.
        $autor = Auth::id() ?? $this->maintenanceOrder?->technician_id;

        if (! $autor) {
            return;
        }

        MaintenanceOrderPendencia::create([
            'tenant_id' => $this->tenant_id,
            'maintenance_order_id' => $this->maintenance_order_id,
            'description' => $titulo.($this->notes ? ' — '.$this->notes : ''),
            'created_by_user_id' => $autor,
            'status' => MaintenanceOrderPendencia::STATUS_ABERTA,
        ]);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function checklistGroup(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }
}
