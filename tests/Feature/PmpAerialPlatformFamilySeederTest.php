<?php

namespace Tests\Feature;

use App\Models\PmpEquipmentFamily;
use Database\Seeders\PmpAerialPlatformFamilySeeder;
use Database\Seeders\PmpCompressorFamilySeeder;
use Database\Seeders\PmpEquipmentFamilySeeder;
use Database\Seeders\PmpGeneratorFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmento 'plataformas_elevatorias' -- 3 famílias por categoria (Tesoura
 * Elétrica, Articulada/Lança, Sistema Eletrônico de Segurança). Checklist
 * de inspeção (2026-08-27, enviado em mensagem separada, "Tesoura e
 * Lança") aplica só às 2 famílias operacionais -- Sistema Eletrônico de
 * Segurança não é um tipo de equipamento físico, fica sem checklist.
 */
class PmpAerialPlatformFamilySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_families_with_plan_items(): void
    {
        $this->seed(PmpAerialPlatformFamilySeeder::class);

        $families = PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')->get();
        $this->assertCount(3, $families);

        foreach ($families as $family) {
            $this->assertGreaterThan(0, $family->templateItems()->count(), "Família '{$family->name}' deveria ter itens de plano.");
        }
    }

    public function test_checklist_applies_only_to_the_two_operational_families(): void
    {
        $this->seed(PmpAerialPlatformFamilySeeder::class);

        $families = PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')
            ->withCount('checklistItems')
            ->get()
            ->keyBy('name');

        $this->assertGreaterThan(0, $families['Tesoura Elétrica (Scissor)']->checklist_items_count);
        $this->assertGreaterThan(0, $families['Articulada / Lança (A Combustão / Híbrida)']->checklist_items_count);
        $this->assertSame(0, $families['Sistema Eletrônico de Segurança']->checklist_items_count);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PmpAerialPlatformFamilySeeder::class);
        $countAfterFirst = PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')->count();

        $this->seed(PmpAerialPlatformFamilySeeder::class);
        $countAfterSecond = PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(3, $countAfterSecond);
    }

    public function test_four_segments_coexist_without_collision(): void
    {
        $this->seed(PmpEquipmentFamilySeeder::class);
        $this->seed(PmpGeneratorFamilySeeder::class);
        $this->seed(PmpCompressorFamilySeeder::class);
        $this->seed(PmpAerialPlatformFamilySeeder::class);

        $this->assertSame(5, PmpEquipmentFamily::where('segment', 'empilhadeiras')->count());
        $this->assertSame(1, PmpEquipmentFamily::where('segment', 'geradores')->count());
        $this->assertSame(4, PmpEquipmentFamily::where('segment', 'compressores')->count());
        $this->assertSame(3, PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')->count());
    }
}
