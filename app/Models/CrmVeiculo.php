<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Veículo usado pelos vendedores nas visitas (cadastro simples; vínculo opcional com a Frota do ERP). */
class CrmVeiculo extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'crm_veiculos';

    protected static ?string $saasFeatureKey = 'tabela_crm_veiculos';

    protected static ?string $saasPermissionSlug = 'crm_veiculo';

    protected static ?string $saasModuleLabel = 'CRM - Veículos';

    protected $fillable = [
        'tenant_id', 'placa', 'descricao', 'marca', 'modelo', 'ano', 'km_atual', 'responsavel_user_id',
        'vencimento_licenciamento', 'vencimento_seguro', 'proxima_revisao_km', 'ativo', 'observacoes', 'fleet_vehicle_id',
    ];

    protected $casts = [
        'km_atual' => 'integer', 'ano' => 'integer', 'proxima_revisao_km' => 'integer', 'ativo' => 'boolean',
        'vencimento_licenciamento' => 'date', 'vencimento_seguro' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(fn (CrmVeiculo $v) => $v->placa = self::normalizarPlaca((string) $v->placa));
    }

    public static function normalizarPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(CrmVisita::class, 'crm_veiculo_id');
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(CrmVisitaDespesa::class, 'crm_veiculo_id');
    }

    public function rotulo(): string
    {
        return trim($this->placa.' — '.($this->descricao ?: trim($this->marca.' '.$this->modelo)), ' —');
    }

    /** 'vencido' | 'a_vencer' (até 30 dias) | null — olha licenciamento e seguro. */
    public function situacaoVencimentos(): ?string
    {
        $datas = array_filter([$this->vencimento_licenciamento, $this->vencimento_seguro]);

        if (! $datas) {
            return null;
        }

        $primeira = collect($datas)->min();

        return match (true) {
            $primeira->isPast() && ! $primeira->isToday() => 'vencido',
            $primeira->lte(now()->addDays(30)->endOfDay()) => 'a_vencer',
            default => null,
        };
    }

    /** Visita em andamento de OUTRO vendedor usando este veículo? (só para avisar) */
    public function emUsoPorOutro(?string $userId): ?CrmVisita
    {
        return $this->visitas()->where('status', CrmVisita::STATUS_EM_ANDAMENTO)->when($userId, fn ($q) => $q->where('user_id', '!=', $userId))->first();
    }
}
