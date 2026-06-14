<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Filament\Widgets\RadarOperacional;

class LivewireServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registra o componente manualmente para evitar erros de descoberta
        Livewire::component('radar-operacional', RadarOperacional::class);
    }
}
