<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Contracts\View\View;

/**
 * Tela mobile de ponto eletrônico, offline-first: mesmo padrão de
 * HourMeterOfflineController -- captura/fila de sincronização roda
 * inteiramente em JS (localStorage), sem round-trip Livewire. Ver
 * resources/js/time-clock-offline.js.
 */
class TimeClockOfflineController extends Controller
{
    public function show(): View
    {
        $employee = Employee::where('user_id', auth()->id())->first();

        if (! $employee) {
            return view('time-clock-sem-ficha');
        }

        return view('time-clock-offline', [
            'employee' => $employee,
        ]);
    }
}
