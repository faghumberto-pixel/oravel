<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\WebVisitResource\Pages\ListWebVisits;
use App\Models\User;
use App\Models\WebVisit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class WebVisitExportTest extends TestCase
{
    use DatabaseTransactions;

    private function visita(): WebVisit
    {
        $v = WebVisit::create([
            'visitor_token' => 'vt-'.uniqid(), 'session_token' => 'st-'.uniqid(), 'ip_address' => '1.2.3.4',
            'city' => 'Campinas', 'state' => 'SP', 'device_type' => 'mobile', 'landing_path' => '/',
            'utm_source' => 'google', 'page_views' => 2, 'duration_seconds' => 75, 'started_at' => now(),
        ]);
        $v->pageviews()->create(['page_token' => 'pt-'.uniqid(), 'path' => '/frotas', 'active_seconds' => 30, 'max_scroll' => 80, 'entered_at' => now()]);

        return $v->fresh();
    }

    public function test_xlsx_and_pdf_content_has_every_field(): void
    {
        $v = $this->visita();
        $row = \App\Support\WebVisitExport::row($v->load(['pageviews', 'events']));

        $this->assertCount(count(\App\Support\WebVisitExport::headings()), $row);
        $this->assertContains('Campinas', $row);
        $this->assertContains('1:15', $row);
        $this->assertStringContainsString('/frotas', implode(' ', $row));

        $path = \App\Support\WebVisitExport::xlsx(WebVisit::query()->whereKey($v->id));
        $this->assertFileExists($path);
        $this->assertGreaterThan(1000, filesize($path));
        @unlink($path);

        [$visitas, $total] = \App\Support\WebVisitExport::pdfData(WebVisit::query()->whereKey($v->id));
        $html = view('pdf.visitas-site', compact('visitas', 'total'))->render();
        $this->assertStringContainsString('Campinas', $html);
        $this->assertStringContainsString($v->session_token, $html);
    }

    public function test_actions_run_on_central_page(): void
    {
        $this->visita();
        $super = User::create(['name' => 'S', 'email' => (config('oravel.super_admins')[0] ?? 'faghumberto@gmail.com'),
            'password' => bcrypt('x'), 'is_approved' => true]);
        $this->actingAs($super);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('central'));

        Livewire::test(ListWebVisits::class)
            ->callAction('exportarExcel')->assertHasNoActionErrors()
            ->callAction('exportarPdf')->assertHasNoActionErrors();
    }
}
