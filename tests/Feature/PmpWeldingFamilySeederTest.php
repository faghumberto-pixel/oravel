<?php

namespace Tests\Feature;

use App\Models\PmpEquipmentFamily;
use Database\Seeders\PmpAerialPlatformFamilySeeder;
use Database\Seeders\PmpCompressorFamilySeeder;
use Database\Seeders\PmpEquipmentFamilySeeder;
use Database\Seeders\PmpGeneratorFamilySeeder;
use Database\Seeders\PmpWeldingFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmento 'solda_corte' -- 4 famílias por tecnologia (Inversores/Fontes
 * de Solda, Corte Plasma, Mecanização & Automação, Motossoldadoras &
 * Geradores). Checklist de inspeção ainda não foi fornecido -- confirma
 * isso explicitamente, mesmo padrão usado em Plataformas Elevatórias
 * antes do checklist chegar.
 */
class PmpWeldingFamilySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_four_families_with_plan_items(): void
    {
        $this->seed(PmpWeldingFamilySeeder::class);

        $families = PmpEquipmentFamily::where('segment', 'solda_corte')->get();
        $this->assertCount(4, $families);

        foreach ($families as $family) {
            $this->assertGreaterThan(0, $family->templateItems()->count(), "Família '{$family->name}' deveria ter itens de plano.");
        }
    }

    /**
     * Documento de checklist ainda não foi enviado -- este teste é
     * intencional: vira um lembrete claro (falha) quando alguém adicionar
     * checklistItems sem também atualizar este assert.
     */
    public function test_checklist_is_still_pending_for_all_four_families(): void
    {
        $this->seed(PmpWeldingFamilySeeder::class);

        $totalChecklist = PmpEquipmentFamily::where('segment', 'solda_corte')
            ->withCount('checklistItems')
            ->get()
            ->sum('checklist_items_count');

        $this->assertSame(0, $totalChecklist, 'Checklist de solda/corte foi adicionado -- atualize este teste.');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PmpWeldingFamilySeeder::class);
        $countAfterFirst = PmpEquipmentFamily::where('segment', 'solda_corte')->count();

        $this->seed(PmpWeldingFamilySeeder::class);
        $countAfterSecond = PmpEquipmentFamily::where('segment', 'solda_corte')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(4, $countAfterSecond);
    }

    public function test_five_segments_coexist_without_collision(): void
    {
        $this->seed(PmpEquipmentFamilySeeder::class);
        $this->seed(PmpGeneratorFamilySeeder::class);
        $this->seed(PmpCompressorFamilySeeder::class);
        $this->seed(PmpAerialPlatformFamilySeeder::class);
        $this->seed(PmpWeldingFamilySeeder::class);

        $this->assertSame(5, PmpEquipmentFamily::where('segment', 'empilhadeiras')->count());
        $this->assertSame(1, PmpEquipmentFamily::where('segment', 'geradores')->count());
        $this->assertSame(4, PmpEquipmentFamily::where('segment', 'compressores')->count());
        $this->assertSame(3, PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')->count());
        $this->assertSame(4, PmpEquipmentFamily::where('segment', 'solda_corte')->count());
    }
}
