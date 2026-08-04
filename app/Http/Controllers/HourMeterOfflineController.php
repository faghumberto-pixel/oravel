<?php

namespace App\Http\Controllers;

class HourMeterOfflineController extends Controller
{
    /**
     * Tela mobile de registro de horímetro, offline-first de verdade: a
     * captura/fila de sincronização roda inteiramente em JS (localStorage),
     * sem depender de round-trip Livewire (que exige o servidor responder a
     * cada interação -- inviável sem rede). Ver
     * resources/js/hour-meter-offline.js.
     */
    public function show()
    {
        return view('hour-meter-offline', [
            'technicianName' => auth()->user()->name,
        ]);
    }
}
