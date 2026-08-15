<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
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
}
