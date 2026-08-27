<?php

namespace Tests\Feature;

use App\Models\PmpEquipmentFamily;
use Database\Seeders\PmpEquipmentFamilySeeder;
use Database\Seeders\PmpGeneratorFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmento 'geradores' é separado de 'empilhadeiras' -- confirma que os
 * dois seeders coexistem sem colidir (nomes de item podem se repetir
 * entre segmentos sem duplicar/sobrescrever, já que a chave de
 * idempotência é por family_id, não global).
 */
class PmpGeneratorFamilySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_generators_family_with_items_and_checklist(): void
    {
        $this->seed(PmpGeneratorFamilySeeder::class);

        $family = PmpEquipmentFamily::where('segment', 'geradores')->sole();

        $this->assertSame('Grupos Geradores', $family->name);
        $this->assertGreaterThan(0, $family->templateItems()->count());
        $this->assertGreaterThan(0, $family->checklistItems()->count());
        $this->assertGreaterThan(0, $family->templateItems()->where('is_critical', true)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PmpGeneratorFamilySeeder::class);
        $countAfterFirst = PmpEquipmentFamily::where('segment', 'geradores')->sole()->templateItems()->count();

        $this->seed(PmpGeneratorFamilySeeder::class);
        $countAfterSecond = PmpEquipmentFamily::where('segment', 'geradores')->sole()->templateItems()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_generators_and_empilhadeiras_segments_coexist_without_collision(): void
    {
        $this->seed(PmpEquipmentFamilySeeder::class);
        $this->seed(PmpGeneratorFamilySeeder::class);

        $this->assertSame(5, PmpEquipmentFamily::where('segment', 'empilhadeiras')->count());
        $this->assertSame(1, PmpEquipmentFamily::where('segment', 'geradores')->count());
    }
}
