<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademiaDoPlano;
use App\Support\MenuModules;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Fase 8G: a Academia vai em todos os pacotes, mas cada cliente só vê o conteúdo do pacote contratado. */
class AcademiaPorPacoteTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<array<string, mixed>> */
    private function cursosJson(): array
    {
        return json_decode(file_get_contents(database_path('data/academy/courses.json')), true);
    }

    private function carregarCursos(): void
    {
        foreach ($this->cursosJson() as $i => $c) {
            $curso = Course::withoutGlobalScopes()->create(['title' => $c['title'], 'slug' => Str::slug($c['title']).'-'.$i, 'description' => $c['description'], 'position' => $i + 1, 'is_published' => true]);

            foreach ($c['lessons'] as $j => $l) {
                Lesson::withoutGlobalScopes()->create(['course_id' => $curso->id, 'title' => $l['title'], 'summary' => $l['summary'], 'body' => '<p>x</p>', 'feature_key' => $l['feature'], 'position' => $j + 1]);
            }
        }
    }

    /** @return array<string, int> título do curso => aulas liberadas no pacote */
    private function visivel(string $pacote): array
    {
        return AcademiaDoPlano::resumo(array_fill_keys(Pacotes::features($pacote), true))['lista'];
    }

    public function test_every_package_includes_the_academy_in_its_base(): void
    {
        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertContains('tabela_courses', Pacotes::features($codigo), $codigo);
        }
        $this->assertContains('tabela_courses', config('pacotes.base.features'));
    }

    public function test_content_files_are_consistent_with_the_catalog(): void
    {
        $catalogo = collect(ContratoResource::groupedFeatureOptions())->flatMap(fn ($o) => array_keys($o))->merge(array_keys(MenuModules::all()))->unique()->all();
        $hrefs = [];

        foreach ($this->cursosJson() as $curso) {
            foreach ($curso['lessons'] as $aula) {
                $this->assertFileExists(database_path("data/academy/lessons/{$aula['slug']}.html"), $aula['slug']);
                $hrefs[] = $aula['href'];

                if ($aula['feature'] !== null) {
                    $this->assertContains($aula['feature'], $catalogo, "{$aula['slug']}: módulo inexistente ({$aula['feature']})");
                }
            }
        }

        $quiz = json_decode(file_get_contents(database_path('data/academy/quiz.json')), true);

        foreach ($quiz as $pagina) {
            $this->assertContains($pagina['page'], $hrefs, "prova sem aula: {$pagina['page']}");

            foreach ($pagina['questions'] as $q) {
                $this->assertCount(4, $q['options'], $pagina['page']);
                $this->assertArrayHasKey($q['correct'], $q['options'], $pagina['page']);
                $this->assertNotEmpty($q['why']);
            }
        }

        foreach (['whatsapp', 'email-empresa', 'cobranca-automatica', 'migracao-dados'] as $aula) {
            $this->assertContains("/configuracoes/{$aula}.html", array_column($quiz, 'page'), "aula {$aula} sem prova");
        }
    }

    public function test_each_package_sees_only_its_own_courses(): void
    {
        $this->carregarCursos();

        $manutencao = $this->visivel('manutencao');
        $this->assertArrayHasKey('Comece Aqui', $manutencao);
        $this->assertArrayHasKey('Manutenção', $manutencao);
        $this->assertArrayHasKey('Processo de Manutenção', $manutencao);
        $this->assertArrayHasKey('Configurações da Empresa', $manutencao);
        foreach (['Suprimentos', 'Processo de Suprimentos', 'Logística', 'Processo Logístico', 'Processo Comercial'] as $fora) {
            $this->assertArrayNotHasKey($fora, $manutencao, "Manutenção não vê {$fora}");
        }

        $estoque = $this->visivel('estoque');
        $this->assertArrayHasKey('Suprimentos', $estoque);
        $this->assertArrayHasKey('Processo de Suprimentos', $estoque);
        foreach (['Manutenção', 'Logística', 'Comercial', 'Processo Comercial'] as $fora) {
            $this->assertArrayNotHasKey($fora, $estoque, "Estoque não vê {$fora}");
        }

        $logistica = $this->visivel('logistica');
        $this->assertArrayHasKey('Logística', $logistica);
        $this->assertArrayHasKey('Processo Logístico', $logistica);
        foreach (['Suprimentos', 'Comercial', 'Processo Comercial'] as $fora) {
            $this->assertArrayNotHasKey($fora, $logistica, "Logística não vê {$fora}");
        }

        $vendas = $this->visivel('vendas');
        $this->assertSame(4, $vendas['Comercial'], 'Vendas: leads, funil, agenda e mapa comercial');
        foreach (['Manutenção', 'Suprimentos', 'Logística', 'Processo de Manutenção'] as $fora) {
            $this->assertArrayNotHasKey($fora, $vendas, "Vendas não vê {$fora}");
        }

        $servicos = $this->visivel('servicos');
        $this->assertArrayHasKey('Manutenção', $servicos);
        $this->assertSame(4, $servicos['Comercial'], 'Serviços vê contratos, clientes, propostas e orçamentos');
        $this->assertArrayHasKey('Processo Comercial', $servicos);
        $this->assertArrayNotHasKey('Processo Comercial', $vendas);
        $this->assertArrayNotHasKey('Suprimentos', $servicos);
    }

    public function test_locadora_sees_everything_and_complement_lesson_needs_the_complement(): void
    {
        $this->carregarCursos();
        $total = Lesson::withoutGlobalScopes()->count();

        $locadora = AcademiaDoPlano::resumo(array_fill_keys(Pacotes::features('locadora'), true));
        $this->assertSame($total - 1, $locadora['aulas'], 'tudo, menos a aula do complemento de cobrança automática (não faz parte de nenhum pacote)');

        $com = AcademiaDoPlano::resumo(array_fill_keys(array_merge(Pacotes::features('manutencao'), ['modulo_cobranca_automatica']), true));
        $sem = AcademiaDoPlano::resumo(array_fill_keys(Pacotes::features('manutencao'), true));
        $this->assertSame($sem['aulas'] + 1, $com['aulas']);
    }

    public function test_setup_course_reaches_every_package_except_the_complement_lesson(): void
    {
        $this->carregarCursos();

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $aulas = collect($this->visivel($codigo))['Configurações da Empresa'] ?? 0;
            $this->assertSame(3, $aulas, "{$codigo}: WhatsApp, e-mail e migração de dados");
        }
    }

    public function test_a_logged_client_only_sees_its_package_in_the_academy(): void
    {
        $this->carregarCursos();
        $plano = Plan::create(['name' => 'P'.uniqid(), 'pacote' => 'estoque', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => array_fill_keys(Pacotes::features('estoque'), true)]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@x.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);

        $titulos = Course::visibleToUser()->pluck('title')->all();

        $this->assertContains('Suprimentos', $titulos);
        $this->assertContains('Configurações da Empresa', $titulos);
        $this->assertNotContains('Logística', $titulos);
        $this->assertNotContains('Processo Comercial', $titulos);
    }

    public function test_migration_adds_the_academy_to_the_showcase_plans_only(): void
    {
        $vitrine = Pacotes::planoDeVitrine('servicos');
        $vitrine->update(['features' => ['tabela_clients' => true]]);
        $cliente = Plan::create(['name' => 'Cliente', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);

        $m = require base_path('database/migrations/2026_11_29_100000_liberar_academia_nos_planos_de_vitrine.php');
        $m->up();
        $m->up();

        $this->assertTrue($vitrine->fresh()->features['tabela_courses']);
        $this->assertArrayNotHasKey('tabela_courses', $cliente->fresh()->features, 'contrato de cliente não é alterado');
        $this->assertSame(1, DB::table('plans')->where('id', $vitrine->id)->count());
    }

    public function test_central_texts_describe_the_academy_of_the_plan(): void
    {
        $this->carregarCursos();
        $plano = Plan::create(['name' => 'P', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => array_fill_keys(Pacotes::features('manutencao'), true)]);

        $texto = AcademiaDoPlano::texto($plano);
        $this->assertStringContainsString('Manutenção (', $texto);
        $this->assertStringNotContainsString('Suprimentos', $texto);

        $sem = Plan::create(['name' => 'S', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
        $this->assertSame('Academia não liberada neste plano.', AcademiaDoPlano::texto($sem));
    }
}
