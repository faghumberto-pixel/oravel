<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\DocumentsRelationManager;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Tests\TestCase;

/**
 * Cadastro do ativo: até 3 fotos do equipamento (a 1ª é a principal) + documentos (laudos, notas
 * fiscais...) em disco PRIVADO, que só abrem por link assinado (06/10/2026).
 */
class AssetPhotosAndDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithAdmin(string $name): array
    {
        $plan = Plan::create([
            'name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => $name.' '.uniqid(), 'slug' => Str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin '.$name, 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function asset(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira X', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function jpg(string $name): string
    {
        $path = sys_get_temp_dir().'/'.$name;
        $img = imagecreatetruecolor(800, 600);
        imagejpeg($img, $path, 80);

        return $path;
    }

    public function test_asset_accepts_photos_only_as_images_and_generates_a_thumbnail(): void
    {
        [$tenant] = $this->tenantWithAdmin('A');
        $asset = $this->asset($tenant);

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $f) {
            $asset->addMedia($this->jpg($f))->toMediaCollection('fotos');
        }

        $this->assertSame(3, $asset->getMedia('fotos')->count());
        $this->assertNotEmpty($asset->getFirstMediaUrl('fotos', 'thumb'));
        $this->assertFileExists($asset->getFirstMedia('fotos')->getPath('thumb'));

        $this->expectException(FileUnacceptableForCollection::class);
        $pdf = sys_get_temp_dir().'/x.pdf';
        file_put_contents($pdf, '%PDF-1.4 teste');
        $asset->addMedia($pdf)->toMediaCollection('fotos');
    }

    public function test_the_asset_form_limits_photos_to_three(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin('B');
        $asset = $this->asset($tenant);
        $this->actingAs($admin);

        $component = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])->instance();
        $field = $component->form->getFlatFields()['fotos'] ?? null;

        $this->assertNotNull($field, 'Campo de fotos não está no formulário do ativo.');
        $this->assertSame(Asset::MAX_PHOTOS, $field->getMaxFiles());
        $this->assertSame(3, Asset::MAX_PHOTOS);
    }

    public function test_documents_are_private_and_open_only_through_a_signed_temporary_link(): void
    {
        [$tenant] = $this->tenantWithAdmin('C');
        $asset = $this->asset($tenant);
        $pdf = sys_get_temp_dir().'/laudo.pdf';
        file_put_contents($pdf, "%PDF-1.4\nlaudo de teste");

        $doc = AssetDocument::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => AssetDocument::TIPO_LAUDO, 'titulo' => 'Laudo anual']);
        $media = $doc->addMedia($pdf)->toMediaCollection('arquivo');

        $this->assertSame('media_private', $media->disk);
        $this->assertFileExists($media->getPath());
        $this->assertStringContainsString('media-library/private', $media->getPath());

        $signed = $media->getTemporaryUrl(now()->addMinutes(10));
        // Prefixo próprio: não pode disputar /storage/ com o disco 'local' (rota do Livewire/Filament).
        $this->assertStringContainsString('/arquivos-privados/', $signed);
        $this->assertSame('/storage/{path}', collect(app('router')->getRoutes()->getRoutesByName())['storage.local']->uri() === 'storage/{path}' ? '/storage/{path}' : 'x');
        $this->get($signed)->assertOk();

        // Sem a assinatura, o mesmo endereço não abre.
        $this->assertNotSame(200, $this->get(strtok($signed, '?'))->status());
    }

    public function test_documents_tab_lists_only_the_tenants_own_documents(): void
    {
        [$tenantA, $adminA] = $this->tenantWithAdmin('D');
        [$tenantB] = $this->tenantWithAdmin('E');
        $assetA = $this->asset($tenantA);
        $assetB = $this->asset($tenantB);
        $docA = AssetDocument::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'tipo' => AssetDocument::TIPO_NOTA_FISCAL, 'titulo' => 'NF de compra', 'numero' => '1234', 'valor' => 150000]);
        $docB = AssetDocument::create(['tenant_id' => $tenantB->id, 'asset_id' => $assetB->id, 'tipo' => AssetDocument::TIPO_LAUDO, 'titulo' => 'Laudo do outro cliente']);

        $this->actingAs($adminA);
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $assetA, 'pageClass' => EditAsset::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$docA])
            ->assertSee('NF de compra');

        $this->assertSame(1, AssetDocument::count());
        $this->assertNull(AssetDocument::find($docB->id));
    }

    public function test_the_mobile_dossier_shows_the_main_photo(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin('F');
        $asset = $this->asset($tenant);
        $asset->addMedia($this->jpg('dossie.jpg'))->toMediaCollection('fotos');
        $this->actingAs($admin);

        $this->get(route('assets.dossier.mobile', ['assetId' => $asset->id]))
            ->assertOk()
            ->assertSee('Foto de '.$asset->name, false);
    }
}
