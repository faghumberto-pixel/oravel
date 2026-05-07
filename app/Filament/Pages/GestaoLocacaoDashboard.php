<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
// --- INTERVENÇÃO TÉCNICA PARA BLINDAGEM DA DEMO: INÍCIO (PURAMENTE ADITIVO) ---
// Importação do Model necessária para o dossiê OPERACIONAL AUDITÁVEL (Cena 2)
use App\Models\MaintenanceOrder;
// --- INTERVENÇÃO TÉCNICA PARA BLINDAGEM DA DEMO: FIM ---

class GestaoLocacaoDashboard extends Page
{
    // --- CORREÇÃO CIRÚRGICA: INÍCIO (Issue #TypedPropertyNotInitialized na Demo) ---
    
    // 1. Inicialização Estática Missing: Define o ícone nativo do Filament
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    // 2. CORREÇÃO DO ERRO ATUAL: Inicialização Estática da View (Foco da Issue)
    // Inicializamos a propriedade estática $view para apontar para o Blade correto.
    // Isso impede que o erro 'must not be accessed before initialization' quebre a demo.
    protected static string $view = 'filament.pages.gestao-locacao-dashboard';

    // 3. Ajuste de Navegação Aditivo para a Demo Locadora (Foco Comercial)
    // Agrupa e ordena sob 'Gestão de Locação Operacional' no menu nativo do Filament
    protected static ?string $navigationGroup = 'Gestão de Locação Operacional';
    protected static ?string $navigationLabel = 'Dashboard de Locação';
    protected static ?int $navigationSort = 1;

    // --- CORREÇÃO CIRÚRGICA: FIM ---

    // Propriedade para armazenar a OS que vamos exibir na Demo
    public ?MaintenanceOrder $orderWithEvidences = null;

    public function mount()
    {
        // ... sua lógica mount genérica original INTACTA, 
        // onde simulamos o dossiê OPERACIONAL AUDITÁVEL DUMMY (Cena 2).
        // Nenhuma linha original foi excluída.
    }
}