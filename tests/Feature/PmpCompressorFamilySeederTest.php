<?php

namespace Tests\Feature;

use App\Models\PmpEquipmentFamily;
use Database\Seeders\PmpCompressorFamilySeeder;
use Database\Seeders\PmpEquipmentFamilySeeder;
use Database\Seeders\PmpGeneratorFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmento 'compressores' -- diferente de 'geradores' (1 família única),
 * aqui o documento já separa por tecnologia: 4 famílias, checklist só na
 * Portátil a Diesel (único documento completo fornecido).
 */
class PmpCompressorFamilySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_four_families_with_checklist_only_on_portable_diesel(): void
    {
        $this->seed(PmpCompressorFamilySeeder::class);

        $families = PmpEquipmentFamily::where('segment', 'compressores')->get();
        $this->assertCount(4, $families);

        $portatilDiesel = $families->firstWhere('name', 'Portátil a Parafuso (Motor Diesel)');
        $this->assertGreaterThan(0, $portatilDiesel->checklistItems()->count());

        $outras = $families->reject(fn ($f) => $f->id === $portatilDiesel->id);
        foreach ($outras as $family) {
            $this->assertSame(0, $family->checklistItems()->count(), "Família '{$family->name}' não deveria ter checklist.");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PmpCompressorFamilySeeder::class);
        $countAfterFirst = PmpEquipmentFamily::where('segment', 'compressores')->count();

        $this->seed(PmpCompressorFamilySeeder::class);
        $countAfterSecond = PmpEquipmentFamily::where('segment', 'compressores')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(4, $countAfterSecond);
    }

    public function test_three_segments_coexist_without_collision(): void
    {
        $this->seed(PmpEquipmentFamilySeeder::class);
        $this->seed(PmpGeneratorFamilySeeder::class);
        $this->seed(PmpCompressorFamilySeeder::class);

        $this->assertSame(5, PmpEquipmentFamily::where('segment', 'empilhadeiras')->count());
        $this->assertSame(1, PmpEquipmentFamily::where('segment', 'geradores')->count());
        $this->assertSame(4, PmpEquipmentFamily::where('segment', 'compressores')->count());
    }
}
