<?php

namespace Tests\Feature;

use App\Console\Commands\IntelligenceSincronizarPlanoTeste;
use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use App\Filament\Pages\PainelGestao;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\CourseResource;
use App\Filament\Resources\IntelligenceImportResource;
use App\Filament\Resources\UserResource;
use App\Models\Plan;
use App\Support\AppProfile;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Acessos do Intelligence: sem Início, Academia Oravel, painéis nem os grupos Configurações e Equipe. */
class IntelligenceMenuEnxutoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pacote_intelligence_nao_leva_inicio_academia_paineis_nem_configuracoes_mas_o_erp_continua_com_tudo(): void
    {
        $intel = Pacotes::features('intelligence');
        foreach (['modulo_dashboard', 'tabela_courses', 'modulo_paineis_funcao'] as $chave) {
            $this->assertNotContains($chave, $intel, $chave.' não pode estar no Intelligence');
        }
        $this->assertSame([], Pacotes::featuresDoGrupo('intelligence', 'Configurações'));
        $this->assertSame([], Pacotes::featuresDoGrupo('intelligence', 'Equipe'), 'sem o menu Equipe (usuários e perfis)');
        $this->assertNotSame([], Pacotes::featuresDoGrupo('intelligence', 'Intelligence'), 'as telas do produto continuam');

        $erp = Pacotes::features('locadora');
        foreach (['modulo_dashboard', 'tabela_courses', 'modulo_paineis_funcao'] as $chave) {
            $this->assertContains($chave, $erp, 'o ERP completo não muda');
        }
        $this->assertNotSame([], Pacotes::featuresDoGrupo('locadora', 'Configurações'));
    }

    private function contaDeTeste()
    {
        return app(TrialCadastro::class)->cadastrar([
            'nome' => 'Ana', 'empresa' => 'Locadora Ana', 'whatsapp' => '+5519977776666', 'email' => 'ana@locadora-ana.test',
        ])['trial'];
    }

    public function test_cliente_do_teste_nao_ve_inicio_academia_painel_nem_configuracoes_e_cai_na_importacao(): void
    {
        $trial = $this->contaDeTeste();
        $this->actingAs($trial->user);

        $this->assertFalse(PainelGestao::canAccess(), 'sem Início/painel');
        $this->assertFalse(CourseResource::canViewAny(), 'sem Academia Oravel');
        $this->assertFalse(CompanyResource::canViewAny(), 'sem o grupo Configurações');
        $this->assertFalse(UserResource::canViewAny(), 'sem o grupo Equipe');
        $this->assertTrue(IntelligenceImportResource::canViewAny(), 'o produto continua');
        $this->assertSame(IntelligenceImportResource::getUrl('index'), AppProfile::homeUrl($trial->user, AppProfile::ADMIN));
    }

    public function test_comando_realinha_o_contrato_de_teste_antigo_com_o_pacote_atual(): void
    {
        $trial = $this->contaDeTeste();
        $plano = Plan::withoutGlobalScopes()->findOrFail($trial->tenant->plan_id);
        $plano->update(['features' => array_merge((array) $plano->features, ['modulo_dashboard' => true, 'tabela_courses' => true])]); // como era antes
        $this->assertTrue($trial->tenant->fresh()->hasFeature('modulo_dashboard'));

        $this->assertSame(0, Artisan::call((new IntelligenceSincronizarPlanoTeste)->getName()));

        $depois = Plan::withoutGlobalScopes()->findOrFail($plano->id);
        $this->assertNotTrue($depois->features['modulo_dashboard'] ?? false);
        $this->assertNotTrue($depois->features['tabela_courses'] ?? false);
    }
}
