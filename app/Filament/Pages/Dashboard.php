<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Support\Tenancy;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Nao havia Dashboard nenhum registrado antes disso (nenhuma Page com
 * routePath '/' -- confirmado via route:list, sem
 * filament.admin.pages.dashboard) -- RedirectToHomeController::__invoke()
 * so cai no primeiro item de navegacao quando nao ha home explicita
 * (Panel::getUrl()), entao visitar /admin ia parar em qualquer coisa que
 * fosse primeiro na ordenacao. Esta classe assume o '/' de proposito.
 *
 * Um dashboard por segmento (Eventos/Construcao Civil/Industrial-
 * Hospitalar/Generico), pedido explicito do usuario: cada segmento ve um
 * conjunto de widgets exclusivo, nao um dashboard generico com tudo
 * misturado. As 4 classes-filhas (DashboardEventos etc) tambem existem
 * como paginas proprias e navegaveis (uteis pro super admin comparar);
 * esta classe so decide qual lista de widgets usar automaticamente pro
 * tenant atual, sem precisar de redirect.
 */
class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return match (Tenancy::current()?->segment) {
            Client::NICHE_EVENTOS => DashboardEventos::widgetList(),
            Client::NICHE_CONSTRUCAO_CIVIL => DashboardConstrucaoCivil::widgetList(),
            Client::NICHE_INDUSTRIAL_HOSPITALAR => DashboardIndustrialHospitalar::widgetList(),
            default => DashboardGenerico::widgetList(),
        };
    }
}
