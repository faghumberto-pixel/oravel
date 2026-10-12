<?php

namespace Tests\Feature;

use App\Filament\Resources\FleetDriverResource;
use App\Filament\Resources\FleetMaintenancePlanResource;
use App\Filament\Resources\FleetStatusResource;
use App\Filament\Resources\FleetVehicleResource;
use App\Filament\Resources\FleetVehicleResource\RelationManagers as RM;
use Tests\TestCase;

/** Módulo de veículos/frota: nada de "Fleet Vehicle", "Fuel record" etc. nos títulos, botões e breadcrumbs. */
class VeiculosEmPortuguesTest extends TestCase
{
    public function test_resources_de_frota_tem_nome_em_portugues(): void
    {
        $this->assertSame('Veículo da Frota', FleetVehicleResource::getModelLabel());
        $this->assertSame('Veículos da Frota', FleetVehicleResource::getPluralModelLabel());
        $this->assertSame('Motorista', FleetDriverResource::getModelLabel());
        $this->assertSame('Plano de Manutenção de Veículo', FleetMaintenancePlanResource::getModelLabel());
        $this->assertSame('Status da Frota', FleetStatusResource::getPluralModelLabel());
    }

    public function test_abas_do_veiculo_tem_nome_em_portugues(): void
    {
        $esperado = [
            RM\DocumentsRelationManager::class => ['Documento', 'Documentos'],
            RM\FuelRecordsRelationManager::class => ['Abastecimento', 'Abastecimentos'],
            RM\MaintenanceHistoryRelationManager::class => ['Manutenção Realizada', 'Manutenções Realizadas'],
            RM\MaintenancePlansRelationManager::class => ['Plano de Preventiva', 'Planos de Preventiva'],
            RM\TollRecordsRelationManager::class => ['Pedágio', 'Pedágios'],
        ];

        foreach ($esperado as $classe => [$singular, $plural]) {
            $this->assertSame($singular, (new \ReflectionMethod($classe, 'getModelLabel'))->invoke(null), $classe);
            $this->assertSame($plural, (new \ReflectionMethod($classe, 'getPluralModelLabel'))->invoke(null), $classe);
        }
    }
}
