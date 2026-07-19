<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadSegmentSourceEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_edit_segment_and_source_via_the_edit_form(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Teste Segmento',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(\App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead::class, ['record' => $lead->getKey()])
            ->fillForm([
                'segment' => 'construcao_civil',
                'source' => SalesLead::SOURCE_INDICACAO,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame('construcao_civil', $lead->segment);
        $this->assertSame(SalesLead::SOURCE_INDICACAO, $lead->source);
    }
}
