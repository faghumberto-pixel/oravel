<?php

namespace App\Livewire\Academy;

use App\Services\AcademyPoints;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ranking de pontos DENTRO da empresa do usuario (o tenant vem do usuario logado).
 */
#[Layout('academy.layout')]
class RankingPage extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.academy.ranking', [
            'rows' => $user->tenant_id ? app(AcademyPoints::class)->ranking($user, 50) : collect(),
            'me' => $user->id,
        ]);
    }
}
