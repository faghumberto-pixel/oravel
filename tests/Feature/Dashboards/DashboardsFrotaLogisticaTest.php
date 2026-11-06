<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Frota as B;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralLogisticaFrota;
use App\Models\User;
use App\Services\Demo\LocadoraDemoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** "Visão geral — Logística da Frota" (menu Gestão de Frota): quantos veículos, com quem, onde, km, custo e corretivas x preventivas. */
class DashboardsFrotaLogisticaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());
    }

    public function test_quantidade_com_quem_e_onde(): void
    {
        $total = (new B\TotalDeVeiculos)->data([]);
        $this->assertSame(6, $total['value']);
        $this->assertStringContainsString('3 na rua · 3 no pátio', $total['delta']);
        $this->assertStringContainsString('1 sem titular', $total['delta']);
        $this->assertSame(3, (new B\VeiculosNaRua)->data([])['value']);

        $linhas = (new B\OndeEstaoOsVeiculos)->data([])['rows'];
        $this->assertCount(6, $linhas);
        $comQuem = array_column($linhas, 2);
        $this->assertNotContains('—', $comQuem);
        $situacoes = collect($linhas)->pluck(4)->map(fn ($p) => $p['text'] ?? $p['label'] ?? json_encode($p))->implode('|');
        $this->assertStringContainsString('Na rua', $situacoes);
        $this->assertStringContainsString('No pátio', $situacoes);

        $mapa = (new B\MapaDaFrota)->data([]);
        $this->assertSame('map', $mapa['type']);
        $this->assertCount(6, $mapa['points']);
    }

    public function test_km_custo_combustivel_e_corretivas_preventivas(): void
    {
        $this->assertGreaterThan(0, count(array_filter((new B\KmPorSemana)->data([])['values'])));
        $this->assertNotSame('0', (new B\KmNoMes)->data([])['value']);

        $custo = (new B\CustoDeManutencaoDaFrota)->data([]);
        $this->assertGreaterThan(0, array_sum($custo['a']));
        $this->assertGreaterThan(0, array_sum((new B\CombustivelPorPeriodo)->data([])['a']));

        $cp = (new B\CorretivasXPreventivas)->data([]);
        $this->assertGreaterThan(0, array_sum($cp['a']), 'corretivas');
        $this->assertGreaterThan(0, array_sum($cp['b']), 'preventivas');
        $this->assertNotSame('—', (new B\IndicePreventiva)->data([])['value']);

        $this->assertNotEmpty((new B\NaRuaAgora)->data([])['events']);
        $this->assertNotEmpty((new B\UltimasMovimentacoesDaFrota)->data([])['events']);
        $this->assertNotEmpty((new B\QuemMaisDirige)->data([])['rows']);
        $this->assertNotEmpty((new B\DestinosMaisVisitados)->data([])['rows']);
        $this->assertNotEmpty((new B\SaidasPorFinalidade)->data([])['slices']);
    }

    public function test_a_pagina_fica_no_menu_gestao_de_frota_e_renderiza(): void
    {
        $this->assertSame('Gestão de Frota', VisaoGeralLogisticaFrota::getNavigationGroup());
        $this->assertTrue(VisaoGeralLogisticaFrota::canAccess());
        $this->assertGreaterThan(15, count(DashboardRenderer::blocks('frota_logistica', auth()->user())));

        Livewire::test(VisaoGeralLogisticaFrota::class)->assertSee('Onde estão os veículos e com quem')->assertSee('Corretivas x preventivas');
    }
}
