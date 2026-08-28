<?php

namespace Tests\Feature;

use App\Models\PmpEquipmentFamily;
use Database\Seeders\PmpCraneFamilySeeder;
use Database\Seeders\PmpEquipmentFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmento 'guindastes_munck' -- pesquisado 2026-08-28 pro prospect Gêmeos
 * Guindastes (sem documento técnico fornecido pelo usuário, baseado em
 * NR-11/NR-12/NBR 8400). Confirma coexistência com outros segmentos, mesmo
 * padrão de PmpGeneratorFamilySeederTest.
 */
class PmpCraneFamilySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_crane_family_with_items_and_checklist(): void
    {
        $this->seed(PmpCraneFamilySeeder::class);

        $family = PmpEquipmentFamily::where('segment', 'guindastes_munck')->sole();

        $this->assertSame('Guindastes Articulados (Munck)', $family->name);
        $this->assertGreaterThan(0, $family->templateItems()->count());
        $this->assertGreaterThan(0, $family->checklistItems()->count());
        $this->assertGreaterThan(0, $family->templateItems()->where('is_critical', true)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PmpCraneFamilySeeder::class);
        $countAfterFirst = PmpEquipmentFamily::where('segment', 'guindastes_munck')->sole()->templateItems()->count();

        $this->seed(PmpCraneFamilySeeder::class);
        $countAfterSecond = PmpEquipmentFamily::where('segment', 'guindastes_munck')->sole()->templateItems()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_crane_and_empilhadeiras_segments_coexist_without_collision(): void
    {
        $this->seed(PmpEquipmentFamilySeeder::class);
        $this->seed(PmpCraneFamilySeeder::class);

        $this->assertSame(5, PmpEquipmentFamily::where('segment', 'empilhadeiras')->count());
        $this->assertSame(1, PmpEquipmentFamily::where('segment', 'guindastes_munck')->count());
    }
}
