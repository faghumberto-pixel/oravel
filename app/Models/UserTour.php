<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Quais telas do passo a passo um usuário já viu. Pertence ao usuário, não à empresa: cada pessoa vê o pop-up uma vez por tela. */
class UserTour extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'tour', 'passos_vistos', 'dispensado_em', 'reaberturas'];

    protected $casts = ['passos_vistos' => 'array', 'dispensado_em' => 'datetime', 'reaberturas' => 'integer'];

    public function jaViu(string $chave): bool
    {
        return in_array($chave, (array) $this->passos_vistos, true);
    }
}
