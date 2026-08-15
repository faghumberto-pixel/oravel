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
        'status',
        'admission_date',
    ];

    protected $casts = [
        'admission_date' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
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
