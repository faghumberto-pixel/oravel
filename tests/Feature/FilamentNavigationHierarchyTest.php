<?php

namespace Tests\Feature;

use App\Filament\Pages\Almoxarifado;
use App\Filament\Pages\AnaliseEstoque;
use App\Filament\Pages\Inventario;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\PartResource;
use App\Filament\Resources\MaterialLocationStockResource;
use App\Filament\Resources\PartCategoryResource;
use PHPUnit\Framework\TestCase;

class FilamentNavigationHierarchyTest extends TestCase
{
    /**
     * Test that Almoxarifado page has proper navigation configuration.
     */
    public function test_almoxarifado_page_has_navigation_group()
    {
        $this->assertEquals('Ativos e Materiais', Almoxarifado::getNavigationGroup());
    }

    public function test_almoxarifado_page_should_register_navigation()
    {
        $this->assertTrue(Almoxarifado::shouldRegisterNavigation());
    }

    /**
     * Test that child resources/pages have navigationParentItem and navigationGroup set.
     */
    public function test_material_resource_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', MaterialResource::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', MaterialResource::getNavigationGroup());
    }

    public function test_part_resource_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', PartResource::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', PartResource::getNavigationGroup());
    }

    public function test_material_location_stock_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', MaterialLocationStockResource::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', MaterialLocationStockResource::getNavigationGroup());
    }

    public function test_part_category_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', PartCategoryResource::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', PartCategoryResource::getNavigationGroup());
    }

    public function test_analise_estoque_page_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', AnaliseEstoque::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', AnaliseEstoque::getNavigationGroup());
    }

    public function test_inventario_page_has_parent_and_group()
    {
        $this->assertEquals('Almoxarifado', Inventario::getNavigationParentItem());
        $this->assertEquals('Ativos e Materiais', Inventario::getNavigationGroup());
    }
}
