<?php

namespace Tests\Feature;

use App\Filament\Pages\ApontamentoHorimetro;
use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages\CreatePreventiveMaintenanceExecution;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reproduzido no PROD 2026-07-29: tirar foto na vistoria da O.S. falhava com
 * "The data.checklists.record-X.photos... failed to upload" -- recusa por
 * tamanho, porque foto de celular (3-8MB) passava dos dois tetos de upload que
 * o PROD tinha: client_max_body_size do nginx (nem configurado, valia o default
 * de 1MB) e upload_max_filesize=2M do PHP. Os dois foram levantados no
 * servidor; o resize no navegador e' a outra metade da correcao, a que nao
 * depende da config de cada ambiente.
 *
 * A correcao e' client-side (FilePond redimensiona antes de enviar), entao nao
 * da' pra exercitar o upload de verdade num teste PHP. O que estes testes
 * travam e' a CONFIGURACAO que faz o resize acontecer: se alguem tirar o
 * imageResizeTargetWidth/Height de um campo de foto, o `shouldTransformImage`
 * do Filament volta pra false, o FilePond volta a enviar o arquivo original e
 * o bug volta silenciosamente -- sem nenhum outro teste falhando.
 */
class PhotoUploadResizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(array $features): User
    {
        $plan = Plan::create([
            'name' => 'Plano Fotos '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Fotos '.uniqid(), 'slug' => 'tenant-fotos-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-fotos-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user;
    }

    /**
     * Localiza o campo de upload pelo nome dentro do form montado, inclusive
     * quando ele esta' aninhado num Repeater/Tab (getFlatComponents recursa
     * pelos child containers).
     */
    private function findUpload(object $livewireInstance, string $name): BaseFileUpload
    {
        $field = collect($livewireInstance->getForm('form')->getFlatComponents(withHidden: true))
            ->first(fn ($component) => $component instanceof BaseFileUpload && $component->getName() === $name);

        $this->assertInstanceOf(
            BaseFileUpload::class,
            $field,
            "Campo de upload '{$name}' nao encontrado no form -- se ele foi renomeado/removido, este teste precisa acompanhar."
        );

        return $field;
    }

    private function assertResizesInBrowser(BaseFileUpload $field, string $label): void
    {
        // shouldTransformImage do Filament (file-upload.blade.php) liga o
        // allowImageTransform do FilePond quando ha' target width/height --
        // sem isso o arquivo original e' enviado como estava.
        $this->assertSame('1600', $field->getImageResizeTargetWidth(), "{$label}: sem largura alvo, o FilePond nao redimensiona");
        $this->assertSame('1600', $field->getImageResizeTargetHeight(), "{$label}: sem altura alvo, o FilePond nao redimensiona");
        // 'contain' cabe na caixa preservando proporcao; 'cover' (default do
        // Filament) CORTA a foto pra preencher exatamente 1600x1600, o que
        // perderia parte da evidencia da vistoria.
        $this->assertSame('contain', $field->getImageResizeMode(), "{$label}: modo diferente de contain corta a foto");
        $this->assertFalse($field->getImageResizeUpscale(), "{$label}: upscale infla foto pequena sem ganho nenhum");

        // maxSize pequeno aqui seria um tiro no pe': o FilePond valida tamanho
        // no arquivo ORIGINAL, antes do resize, e rejeitaria justamente a foto
        // grande que o resize existe pra salvar.
        $maxSize = $field->getMaxSize();
        $this->assertTrue(
            $maxSize === null || $maxSize > 2048,
            "{$label}: maxSize de {$maxSize}KB rejeita a foto original antes do resize rodar"
        );
    }

    public function test_maintenance_order_checklist_photo_is_resized_in_the_browser(): void
    {
        $user = $this->makeTenantAdmin(['tabela_maintenance_orders']);
        $this->actingAs($user);

        $asset = Asset::create(['tenant_id' => $user->tenant_id, 'name' => 'Ativo Vistoria', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $user->tenant_id, 'asset_id' => $asset->id, 'description' => 'OS vistoria',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'em_manutencao',
        ]);

        // O repeater 'checklists' e' ->relationship() com criacao desabilitada:
        // sem nenhum item existente ele nao tem child container e o campo de
        // foto nem chega a ser instanciado.
        MaintenanceOrderChecklist::create([
            'tenant_id' => $user->tenant_id, 'maintenance_order_id' => $order->id,
            'item_name' => 'Nivel de oleo', 'status' => 'conforme',
        ]);

        $component = Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id]);

        $this->assertResizesInBrowser(
            $this->findUpload($component->instance(), 'photos'),
            'Foto da vistoria (O.S.)'
        );
    }

    public function test_preventive_execution_photos_are_resized_in_the_browser(): void
    {
        $user = $this->makeTenantAdmin(['tabela_preventive_maintenance_executions']);
        $this->actingAs($user);

        $component = Livewire::test(CreatePreventiveMaintenanceExecution::class);

        $this->assertResizesInBrowser(
            $this->findUpload($component->instance(), 'photos'),
            'Fotos da execucao de preventiva'
        );
    }

    public function test_horimeter_panel_photo_is_resized_in_the_browser(): void
    {
        $user = $this->makeTenantAdmin(['tabela_horimeter_readings']);
        $this->actingAs($user);

        $component = Livewire::test(ApontamentoHorimetro::class);

        $this->assertResizesInBrowser(
            $this->findUpload($component->instance(), 'photo_path'),
            'Foto do painel (horimetro)'
        );
    }
}
