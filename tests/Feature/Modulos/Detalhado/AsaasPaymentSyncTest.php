<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\AsaasPaymentSync;
use App\Support\SaaSRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * Sincronizacao Asaas: hoje e so um marcador de modulo (sem tabela e sem tela)
 * para o Contrato poder liga-lo/desliga-lo. O teste trava esse estado: se
 * alguem criar tabela ou tela, este arquivo precisa virar um teste de verdade.
 */
class AsaasPaymentSyncTest extends PerfisTestCase
{
    public function test_modulo_esta_registrado_para_plano_e_permissoes(): void
    {
        $meta = SaaSRegistry::forModel(AsaasPaymentSync::class);

        $this->assertSame('tabela_asaas_sync', $meta['feature']);
        $this->assertSame('asaas_sync', $meta['slug']);
        $this->assertNotEmpty($meta['label']);
    }

    public function test_contrato_oferece_o_modulo_no_grupo_financeiro(): void
    {
        $fonte = file_get_contents((new \ReflectionClass(ContratoResource::class))->getFileName());

        $this->assertStringContainsString("'tabela_asaas_sync' => 'Financeiro'", $fonte);
    }

    public function test_e_um_marcador_sem_tabela_e_sem_tela_propria(): void
    {
        $this->assertFalse(Schema::hasTable((new AsaasPaymentSync)->getTable()), 'agora tem tabela: escreva testes de CRUD para este modulo');
        $this->assertFileDoesNotExist(app_path('Filament/Resources/AsaasPaymentSyncResource.php'), 'agora tem tela: escreva testes de tela para este modulo');
    }
}
