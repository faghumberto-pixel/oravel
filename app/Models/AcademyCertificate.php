<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Certificado de conclusao de um curso da Academia. Do TENANT; a consulta publica por
 * codigo (/certificado/{code}) le sem escopo de tenant.
 */
class AcademyCertificate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'code', 'user_name', 'tenant_name', 'course_title', 'study_minutes', 'issued_at'];

    protected $casts = ['issued_at' => 'datetime'];
}
