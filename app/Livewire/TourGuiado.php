<?php

namespace App\Livewire;

use App\Support\Tour\Tour;
use Livewire\Component;

/**
 * Pop-up do passo a passo DENTRO de cada tela: diz o que a tela representa e por que, quando e como usá-la. Abre sozinho na primeira
 * visita a cada tela, reabre pelo botão "Passo a passo" do topo e leva à tela seguinte/anterior (?tour=abrir abre o pop-up de lá).
 * O painel não usa SPA: cada tela carrega por inteiro, então o componente monta na tela certa e olha a URL real.
 */
class TourGuiado extends Component
{
    public bool $aberto = false;

    public ?string $chave = null;

    public function mount(): void
    {
        $tour = Tour::para(auth()->user());
        $passo = $tour?->passoDaTela(request()->path());
        if (! $tour || ! $passo) {
            return;
        }

        $this->chave = $passo['chave'];
        $pedido = request()->query('tour') === 'abrir';

        if ($pedido || $tour->abreSozinho($this->chave)) {
            $this->aberto = true;
            $tour->marcarVisto($this->chave);
        }
    }

    /** Botão "Passo a passo": explica a tela atual; fora das telas do tour, leva à primeira. */
    public function abrir()
    {
        $tour = Tour::para(auth()->user());
        if (! $tour) {
            return null;
        }
        if ($this->chave) {
            $this->aberto = true;
            $tour->marcarVisto($this->chave);
            $tour->registrarReabertura();

            return null;
        }

        return redirect($tour->passos()[0]['url'].'?tour=abrir');
    }

    public function fechar(): void
    {
        $this->aberto = false;
    }

    /** "Não abrir sozinho de novo": o botão do topo continua disponível. */
    public function naoMostrarMais(): void
    {
        Tour::para(auth()->user())?->dispensar();
        $this->aberto = false;
    }

    public function render()
    {
        $tour = Tour::para(auth()->user());
        $passos = $tour?->passos() ?? [];
        $i = ($tour && $this->chave) ? $tour->indiceDe($this->chave) : 0;

        return view('livewire.tour-guiado', [
            'passo' => $this->aberto && $this->chave ? ($passos[$i] ?? null) : null,
            'posicao' => $i + 1,
            'total' => count($passos),
            'anterior' => $passos[$i - 1] ?? null,
            'proximo' => $passos[$i + 1] ?? null,
            'nota' => ($tour && $this->aberto && isset($passos[$i])) ? $tour->notaTrial($passos[$i]) : null,
        ]);
    }
}
