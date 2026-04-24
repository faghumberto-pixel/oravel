<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * Indica que os IDs não são auto-incremento.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * O tipo da chave primária.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome'
    ];

    // Um Tenant não pertence a outro Tenant, então ele não usa nosso Trait BelongsToTenant.
}