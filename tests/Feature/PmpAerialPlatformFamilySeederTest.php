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
 * de inspeção não foi fornecido ainda (mensagem do usuário cortou antes
 * do conteúdo) -- confirma isso explicitamente, pra não passar
 * despercebido quando for adicionado depois.
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

    /**
     * Documento de checklist ainda não foi enviado -- este teste é
     * intencional: vira um lembrete claro (falha) quando alguém adicionar
     * checklistItems sem também remover/atualizar este assert, ou serve
     * de sinalizador de que o checklist já foi adicionado corretamente.
     */
    public function test_checklist_is_still_pending_for_all_three_families(): void
    {
        $this->seed(PmpAerialPlatformFamilySeeder::class);

        $totalChecklist = PmpEquipmentFamily::where('segment', 'plataformas_elevatorias')
            ->withCount('checklistItems')
            ->get()
            ->sum('checklist_items_count');

        $this->assertSame(0, $totalChecklist, 'Checklist de plataformas elevatórias foi adicionado -- atualize este teste.');
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
