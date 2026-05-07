<?php
// app/Filament/Pages/LocacaoContratosCampo.php

namespace App\Filament\Pages;

use Filament\Pages\Page;
// --- INTERVENÇÃO PARA DEMO LOCADORA: INÍCIO (SEM EXCLUSÃO DE CÓDIGO OPERACIONAL) ---
use Filament\Navigation\NavigationGroup;
use Filament\Support\Colors\Color;

class LocacaoContratosCampo extends Page
{
    // --- Configuração da Navegação Industrial Sóbria ---
    // Ícone que simboliza o acordo de locação
    protected static ?string $navigationIcon = 'heroicon-o-handshake'; 
    
    // Título que aparece no menu
    protected static ?string $navigationLabel = 'Contratos Operacionais (Campo)'; 
    
    // --- O PULO DO GATO DA HIERARQUIA: Agrupa sob 'GESTÃO DE LOCAÇÃO OPERACIONAL' ---
    protected static ?string $navigationGroup = '--- GESTÃO DE LOCAÇÃO OPERACIONAL ---';
    
    // Define a ordem dentro do grupo operacional
    protected static ?int $navigationSort = 1;

    // View Blade customizada que criaremos para mostrar o dossiê operacional (Fachada)
    protected static string $view = 'filament.pages.locacao-contratos-campo';
}