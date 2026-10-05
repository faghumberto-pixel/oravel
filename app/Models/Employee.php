<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_employees';

    protected static ?string $saasPermissionSlug = 'colaborador';

    protected static ?string $saasModuleLabel = 'Departamento Pessoal';

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_AFASTADO = 'afastado';

    public const STATUS_DESLIGADO = 'desligado';

    // Employee criado por backfill (tenant:backfill-employees) sem CPF real
    // disponivel -- cpf recebe um placeholder obviamente falso (prefixo
    // 00000) ate alguem do RH completar via UserResource.
    public const STATUS_INCOMPLETO = 'incompleto';

    public const CPF_PLACEHOLDER_PREFIX = '00000';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'department_id',
        'name',
        'cpf',
        'role_title',
        'job_role_id',
        'status',
        'admission_date',
        'daily_work_hours',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'daily_work_hours' => 'decimal:2',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ATIVO => 'Ativo',
            self::STATUS_AFASTADO => 'Afastado',
            self::STATUS_DESLIGADO => 'Desligado',
            self::STATUS_INCOMPLETO => 'Incompleto (CPF pendente)',
        ];
    }

    public function hasPlaceholderCpf(): bool
    {
        return str_starts_with($this->cpf, self::CPF_PLACEHOLDER_PREFIX);
    }

    /**
     * Próximo CPF placeholder livre do tenant (prefixo 00000 + sequência),
     * mesmo padrão usado por tenant:backfill-employees -- evita colidir
     * com o unique(tenant_id, cpf) quando várias sequências placeholder
     * já existem (backfill + toggle "Tornar Colaborador" sem CPF).
     */
    public static function nextPlaceholderCpf(string $tenantId): string
    {
        $lastSequence = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('cpf', 'like', self::CPF_PLACEHOLDER_PREFIX.'%')
            ->get(['cpf'])
            ->map(fn (self $e) => (int) substr($e->cpf, strlen(self::CPF_PLACEHOLDER_PREFIX)))
            ->max() ?? 0;

        return self::CPF_PLACEHOLDER_PREFIX.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    public function timeClocks(): HasMany
    {
        return $this->hasMany(TimeClock::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * Já tem registro de ponto, falta, certificação, alocação ou EPI? Nesse caso o
     * colaborador NÃO pode ser excluído (o banco apagaria o histórico em cascata) --
     * o caminho é o status "Desligado".
     */
    public function hasHistory(): bool
    {
        return $this->timeClocks()->exists()
            || $this->absences()->exists()
            || $this->certifications()->exists()
            || $this->allocations()->exists()
            || $this->epiDeliveries()->exists();
    }

    protected static function booted(): void
    {
        // Rede de segurança (UI, tinker, jobs): excluir com histórico é cancelado.
        static::deleting(fn (self $employee) => $employee->hasHistory() ? false : null);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
    }

    /**
     * Ficha de EPI (compliance NR-6) -- historico completo de entregas/
     * emprestimos, ver App\Models\EpiDelivery.
     */
    public function epiDeliveries(): HasMany
    {
        return $this->hasMany(EpiDelivery::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class);
    }

    public function fleetDriver(): HasOne
    {
        return $this->hasOne(FleetDriver::class);
    }
}
