<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLogEntry;
use App\Models\Asset;
use App\Models\Tenant;
use App\Support\SaaSRegistry;
use Livewire\Livewire;

/** Auditoria de Alteracoes: so leitura e so para o administrador do tenant. */
class ActivityLogEntryTest extends PerfisTestCase
{
    private function ativo(Tenant $tenant): Asset
    {
        return $this->criar(Asset::class, ['tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => 'available']);
    }

    private function registroDeAuditoria(Asset $ativo, string $evento = 'updated'): ActivityLogEntry
    {
        return $this->criar(ActivityLogEntry::class, [
            'log_name' => 'default', 'description' => $evento, 'event' => $evento,
            'subject_type' => Asset::class, 'subject_id' => (string) $ativo->id,
        ]);
    }

    public function test_so_o_administrador_do_tenant_enxerga_a_auditoria(): void
    {
        $this->actingAs($this->a['admin']);
        $this->assertTrue($this->a['admin']->can('viewAny', ActivityLogEntry::class));

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $this->assertFalse($this->a[$perfil]->can('viewAny', ActivityLogEntry::class), $perfil);
        }
    }

    public function test_permissao_de_leitura_nao_abre_a_auditoria_para_tecnico(): void
    {
        $this->conceder($this->a['tecnico'], 'auditoria_alteracao', ['ler']);
        $this->actingAs($this->a['tecnico']);

        $this->assertFalse($this->a['tecnico']->can('viewAny', ActivityLogEntry::class));
    }

    public function test_ninguem_cria_edita_ou_exclui_registro_de_auditoria(): void
    {
        $log = $this->registroDeAuditoria($this->ativo($this->tenantA));
        $this->actingAs($this->a['admin']);

        $this->assertFalse($this->a['admin']->can('create', ActivityLogEntry::class));
        $this->assertFalse($this->a['admin']->can('update', $log));
        $this->assertFalse($this->a['admin']->can('delete', $log));
        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($log));
        $this->assertFalse(ActivityLogResource::canDelete($log));
    }

    public function test_listagem_mostra_so_alteracoes_de_registros_do_proprio_tenant(): void
    {
        $logA = $this->registroDeAuditoria($this->ativo($this->tenantA));
        $logB = $this->registroDeAuditoria($this->ativo($this->tenantB));
        $pagina = ActivityLogResource::getPages()['index']->getPage();

        $this->actingAs($this->a['admin']);
        Livewire::test($pagina)->assertSuccessful()
            ->assertCanSeeTableRecords([$logA])
            ->assertCanNotSeeTableRecords([$logB]);

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            Livewire::test($pagina)->assertForbidden();
        }
    }

    public function test_so_lista_tipos_de_registro_auditaveis(): void
    {
        $naoAuditavel = $this->criar(ActivityLogEntry::class, [
            'log_name' => 'default', 'description' => 'updated', 'event' => 'updated',
            'subject_type' => Tenant::class, 'subject_id' => (string) $this->tenantA->id,
        ]);
        $this->actingAs($this->a['admin']);

        Livewire::test(ActivityLogResource::getPages()['index']->getPage())->assertCanNotSeeTableRecords([$naoAuditavel]);
    }

    public function test_modulo_fora_do_plano_nega_ate_o_administrador(): void
    {
        $semModulo = array_values(array_diff(
            array_filter(array_column(SaaSRegistry::modules(), 'feature')),
            ['tabela_activity_log_entries']
        ));
        [, $perfis] = $this->montarTenant($semModulo);
        $this->actingAs($perfis['admin']);

        $this->lacunaConhecida(
            'ActivityLogEntryPolicy e escrita a mao e nao consulta o plano (Tenant::hasFeature); o modulo aparece para quem nao contratou',
            fn () => $this->assertFalse($perfis['admin']->can('viewAny', ActivityLogEntry::class))
        );
    }
}
