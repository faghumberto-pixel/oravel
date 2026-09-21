<?php

namespace Tests\Feature;

use App\Listeners\DownscaleNonEvidenceMedia;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ClientMessage;
use App\Models\EmailMessage;
use App\Models\EmployeeCertification;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\EquipmentPatioArrival;
use App\Models\EquipmentPatioArrivalItem;
use App\Models\FleetDriverDocument;
use App\Models\FleetVehicleDocument;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\PreventiveMaintenanceExecution;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Só o que NÃO é evidência é reduzido (config/uploads.php, lista de permissão).
 *
 * DatabaseTransactions e NÃO RefreshDatabase: config/database.php fixa 'default' => 'pgsql', então
 * RefreshDatabase rodaria migrate:fresh no banco real do ambiente.
 */
class DownscaleNonEvidenceMediaTest extends TestCase
{
    use DatabaseTransactions;

    private ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public', 'uploads.downscale.enabled' => true]);

        $plan = Plan::create([
            'name' => 'Plano Downscale '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['modulo_chat'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ds-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create([
            'name' => 'U', 'email' => 'ds-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $room = ChatRoom::create(['tenant_id' => $tenant->id, 'type' => 'maintenance']);
        $this->message = ChatMessage::create([
            'chat_room_id' => $room->id, 'user_id' => $user->id, 'message' => 'foto', 'tenant_id' => $tenant->id,
        ]);
    }

    private function bigJpeg(int $w = 3000, int $h = 2000): string
    {
        mt_srand(7);
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 2500; $i++) {
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(20, 300), mt_rand(20, 300),
                imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        $path = sys_get_temp_dir().'/ds-'.uniqid().'.jpg';
        imagejpeg($img, $path, 95);

        return $path;
    }

    private function addTo(ChatMessage $model, string $file, string $collection): Media
    {
        return $model->addMedia($file)->toMediaCollection($collection);
    }

    public function test_foto_do_chat_e_reduzida_e_o_registro_reflete_o_novo_tamanho(): void
    {
        $file = $this->bigJpeg();
        $original = filesize($file);

        $media = $this->addTo($this->message, $file, 'chat_attachments')->fresh();

        $path = $media->getPath();
        [$w, $h] = getimagesize($path);
        $this->assertSame(1600, max($w, $h));
        $this->assertLessThan($original, filesize($path));
        $this->assertSame(filesize($path), (int) $media->size);
        $this->assertSame('3000x2000', $media->getCustomProperty('downscaled')['from']);
    }

    public function test_e_idempotente_uma_segunda_passada_nao_recomprime(): void
    {
        // min_bytes baixo: sem a marca 'downscaled', a 2ª passada REENCODARIA a foto já reduzida
        // (com o padrão de 300 KB ela seria pulada por ser pequena e o teste não provaria nada).
        config(['uploads.downscale.min_bytes' => 1]);

        $media = $this->addTo($this->message, $this->bigJpeg(), 'chat_attachments')->fresh();
        $hash = md5_file($media->getPath());

        (new DownscaleNonEvidenceMedia)->handle(new MediaHasBeenAddedEvent($media));

        $this->assertSame($hash, md5_file($media->getPath()));
    }

    public function test_colecao_que_nao_esta_na_lista_de_permissao_fica_intacta(): void
    {
        // Mesmo model (ChatMessage), coleção fora da lista => não é tocada.
        $file = $this->bigJpeg();
        $hash = md5_file($file);

        $media = $this->addTo($this->message, $file, 'qualquer_outra')->fresh();

        $this->assertSame($hash, md5_file($media->getPath()));
        $this->assertNull($media->getCustomProperty('downscaled'));
    }

    public function test_evidencia_nunca_esta_na_lista_de_permissao(): void
    {
        // Não precisa criar o model (EquipmentDamage exige muitas FKs): a regra é sobre model+coleção.
        foreach ([
            [EquipmentDamage::class, 'photos'],
            [EquipmentMovement::class, 'vistoria_geral'],
            [EquipmentMovementItem::class, 'photos'],
            [EquipmentPatioArrival::class, 'initial_condition_photos'],
            [EquipmentPatioArrivalItem::class, 'photos'],
            [MaintenanceOrderChecklist::class, 'photos'],
            [PreventiveMaintenanceExecution::class, 'photos'],
            [FleetDriverDocument::class, 'arquivo'],
            [FleetVehicleDocument::class, 'arquivo'],
            [EmployeeCertification::class, 'arquivo'],
        ] as [$class, $collection]) {
            $media = new Media(['model_type' => $class, 'collection_name' => $collection]);
            $this->assertFalse(DownscaleNonEvidenceMedia::isNonEvidence($media), "$class::$collection não pode ser reduzida");
        }

        foreach ([[ChatMessage::class, 'chat_attachments'], [ClientMessage::class, 'anexos'], [EmailMessage::class, 'anexos']] as [$class, $collection]) {
            $this->assertTrue(DownscaleNonEvidenceMedia::isNonEvidence(new Media(['model_type' => $class, 'collection_name' => $collection])));
        }
    }

    public function test_audio_e_documento_no_chat_ficam_como_enviados(): void
    {
        $pdf = sys_get_temp_dir().'/ds-'.uniqid().'.pdf';
        file_put_contents($pdf, "%PDF-1.4\n".str_repeat('x', 600000));
        $hash = md5_file($pdf);

        $media = $this->addTo($this->message, $pdf, 'chat_attachments')->fresh();

        $this->assertSame($hash, md5_file($media->getPath()));
    }

    public function test_desligado_por_config_nao_toca_em_nada(): void
    {
        config(['uploads.downscale.enabled' => false]);
        $file = $this->bigJpeg();
        $hash = md5_file($file);

        $media = $this->addTo($this->message, $file, 'chat_attachments')->fresh();

        $this->assertSame($hash, md5_file($media->getPath()));
    }
}
