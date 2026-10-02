<?php

namespace Tests\Feature;

use App\Console\Commands\BuildAcademyContent;
use App\Filament\Central\Resources\CourseResource as CentralCourseResource;
use App\Filament\Resources\CourseResource as AdminCourseResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LessonHtml;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Academia: acesso, conteúdo versionado (importação), módulos por contrato, super admin e sanitização. */
class AcademyTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(string $name, array $features = ['tabela_courses'], bool $permission = true): User
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Aluno '.$name, 'email' => 'aluno-'.uniqid().'@teste.com', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        if ($permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_curso', 'guard_name' => 'web']));
        }

        return $user->fresh();
    }

    private function course(string $title, bool $published = true, int $lessons = 2): Course
    {
        $course = Course::create(['title' => $title, 'slug' => str($title)->slug().'-'.uniqid(), 'is_published' => $published]);
        foreach (range(1, $lessons) as $i) {
            Lesson::create(['course_id' => $course->id, 'title' => "Aula $i", 'position' => $i]);
        }

        return $course;
    }

    private function superAdmin(?Tenant $tenant = null): User
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant?->id]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super->fresh();
    }

    public function test_module_off_in_the_contract_hides_the_academy(): void
    {
        $this->actingAs($this->tenantUser('Cliente Sem', []));
        $this->assertFalse(AdminCourseResource::canViewAny());

        $this->actingAs($this->tenantUser('Cliente Com'));
        $this->assertTrue(AdminCourseResource::canViewAny());
    }

    public function test_entry_page_is_public_and_study_pages_need_a_registered_user_with_the_module(): void
    {
        $this->get('/academia')->assertOk()->assertSee('Entrar para estudar')->assertSee('Verificar um certificado');
        $this->get('/academia/inicio')->assertRedirect('/admin/login');

        $semModulo = $this->tenantUser('Sem Modulo', []);
        $this->actingAs($semModulo)->get('/academia/inicio')->assertForbidden();
    }

    public function test_any_registered_user_of_a_company_with_the_module_gets_in_without_a_specific_permission(): void
    {
        $semPermissao = $this->tenantUser('Sem Permissao', ['tabela_courses'], permission: false);

        $this->actingAs($semPermissao)->get('/academia/inicio')->assertOk();
        $this->actingAs($semPermissao)->get('/academia')->assertRedirect('/academia/inicio'); // logado pula a vitrine
    }

    public function test_super_admin_sees_every_course_and_lesson_without_any_requirement(): void
    {
        $rascunho = $this->course('Rascunho secreto', published: false);
        Lesson::create(['course_id' => $rascunho->id, 'title' => 'Fechada', 'feature_key' => 'tabela_maintenance_orders', 'position' => 9]);
        $publicado = $this->course('Publicado');

        $cliente = $this->tenantUser('Cliente A');
        $this->actingAs($cliente);
        $this->assertSame(['Publicado'], Course::visibleToUser()->pluck('title')->all());
        $this->assertFalse(Lesson::available()->where('title', 'Fechada')->exists());

        $super = $this->superAdmin($cliente->tenant); // mesmo dentro de uma empresa, nao e' filtrado
        $this->actingAs($super);
        $this->assertEqualsCanonicalizing(['Publicado', 'Rascunho secreto'], Course::visibleToUser()->pluck('title')->all());
        $this->assertTrue(Lesson::available()->where('title', 'Fechada')->exists());
        $this->get('/academia/curso/'.$rascunho->slug)->assertOk()->assertSee('Rascunho secreto');
    }

    public function test_embed_url_only_accepts_youtube_and_vimeo(): void
    {
        $l = new Lesson;
        $l->video_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $l->embedUrl());
        $l->video_url = 'https://vimeo.com/123456789';
        $this->assertSame('https://player.vimeo.com/video/123456789', $l->embedUrl());
        $l->video_url = 'https://evil.example.com/watch?v=dQw4w9WgXcQ';
        $this->assertNull($l->embedUrl());
        $l->video_url = 'javascript:alert(1)';
        $this->assertNull($l->embedUrl());
    }

    public function test_lesson_html_keeps_the_visual_classes_and_local_images_but_drops_anything_dangerous(): void
    {
        $html = LessonHtml::clean('<div class="mk-note tip"><strong>Oi</strong><script>alert(1)</script></div>'
            .'<figure class="mk-fig"><img src="/academy/img/a.jpg" alt="a" onerror="x()"><figcaption>c</figcaption></figure>'
            .'<img src="https://evil.com/x.png"><a href="javascript:x()">x</a><p style="color:red">t</p>');

        $this->assertStringContainsString('class="mk-note tip"', $html);
        $this->assertStringContainsString('src="/academy/img/a.jpg"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('evil.com', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    private function contentDir(): string
    {
        $dir = sys_get_temp_dir().'/acad'.uniqid();
        mkdir($dir.'/lessons', 0777, true);
        file_put_contents($dir.'/courses.json', json_encode([
            ['title' => 'Suprimentos', 'description' => 'Materiais e compras.', 'lessons' => [
                ['title' => 'Materiais e Peças', 'href' => '/modulos/materiais.html', 'slug' => 'modulos__materiais', 'summary' => 'Resumo do catálogo', 'feature' => 'tabela_materials'],
                ['title' => 'Fornecedores', 'href' => '/modulos/fornecedores.html', 'slug' => 'modulos__fornecedores', 'summary' => null, 'feature' => 'tabela_suppliers'],
            ]],
        ]));
        file_put_contents($dir.'/lessons/modulos__materiais.html', '<p>Corpo oficial</p>');
        file_put_contents($dir.'/lessons/modulos__fornecedores.html', '<p>Corpo de fornecedores</p>');
        file_put_contents($dir.'/quiz.json', json_encode([
            ['page' => '/modulos/materiais.html', 'questions' => [
                ['q' => 'Pergunta 1?', 'options' => ['a', 'b', 'c'], 'correct' => 1, 'why' => 'Porque b.'],
            ]],
        ]));

        return $dir;
    }

    public function test_import_reads_the_versioned_files_is_idempotent_and_never_overwrites_manual_edits(): void
    {
        $dir = $this->contentDir();
        $this->artisan('academy:import', ['--dir' => $dir])->assertExitCode(0);

        $course = Course::where('title', 'Suprimentos')->firstOrFail();
        $this->assertFalse($course->is_published);                       // padrão: rascunho
        $this->assertSame('Materiais e compras.', $course->description);
        $this->assertSame(2, $course->lessons()->count());
        $materiais = $course->lessons()->where('title', 'Materiais e Peças')->first();
        $this->assertSame('<p>Corpo oficial</p>', $materiais->body);
        $this->assertSame('tabela_materials', $materiais->feature_key);
        $this->assertSame(1, $materiais->questions()->count());
        $this->assertSame(1, $materiais->questions()->first()->correct_index);

        // edição feita na Central: uma nova importação NÃO sobrescreve nem duplica
        $materiais->update(['body' => '<p>Texto editado na Central</p>', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $course->update(['is_published' => true, 'description' => 'Descrição minha']);
        $this->artisan('academy:import', ['--dir' => $dir])->assertExitCode(0);

        $this->assertSame(1, Course::count());
        $this->assertSame(2, Lesson::count());
        $this->assertSame(1, $materiais->fresh()->questions()->count());
        $this->assertSame('<p>Texto editado na Central</p>', $materiais->fresh()->body);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $materiais->fresh()->video_url);
        $this->assertTrue($course->fresh()->is_published);
        $this->assertSame('Descrição minha', $course->fresh()->description);

        // com --refresh-content reaplica os textos dos arquivos (e só eles)
        $this->artisan('academy:import', ['--dir' => $dir, '--refresh-content' => true])->assertExitCode(0);
        $this->assertSame('<p>Corpo oficial</p>', $materiais->fresh()->body);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $materiais->fresh()->video_url);
    }

    public function test_import_removes_internal_subject_lessons_and_publishes_only_the_chosen_courses(): void
    {
        $dir = $this->contentDir();
        $interno = $this->course('Curso antigo');
        Lesson::create(['course_id' => $interno->id, 'title' => 'Multi-tenant', 'page_url' => 'https://academy.oravel.com.br/multi-tenant.html', 'position' => 5]);

        $this->artisan('academy:import', ['--dir' => $dir, '--publish-only' => 'Suprimentos'])->assertExitCode(0);

        $this->assertFalse(Lesson::where('page_url', 'like', '%multi-tenant%')->exists());
        $this->assertTrue(Course::where('title', 'Suprimentos')->first()->is_published);
    }

    public function test_every_module_mapped_by_the_content_builder_exists_in_the_registry(): void
    {
        $map = (new \ReflectionClassConstant(BuildAcademyContent::class, 'FEATURES'))->getValue();

        $this->assertSame([], array_values(array_diff(array_unique($map), array_keys(Plan::getAvailableFeaturesOptions()))));
    }

    public function test_the_versioned_content_has_no_internal_subject_and_every_image_exists(): void
    {
        $dir = database_path('data/academy');
        $this->assertFileExists($dir.'/courses.json');

        foreach (glob($dir.'/lessons/*.html') as $file) {
            $html = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/tenant|painel central|isolament|munkmaq/i', $html, basename($file));
            $this->assertStringNotContainsString('<a ', $html, basename($file).' tem link');
            preg_match_all('#src="/academy/img/([^"]+)"#', $html, $m);
            foreach ($m[1] as $img) {
                $this->assertFileExists(public_path('academy/img/'.$img), basename($file).' -> '.$img);
            }
        }
        $this->assertDoesNotMatchRegularExpression('/tenant|painel central|isolament/i', file_get_contents($dir.'/quiz.json'));
    }

    public function test_central_lists_courses_with_how_many_clients_studied(): void
    {
        $super = $this->superAdmin();
        $a = $this->tenantUser('Cliente A');
        $b = $this->tenantUser('Cliente B');
        $course = $this->course('Primeiros passos');
        $lesson = $course->lessons()->first();
        LessonProgress::create(['tenant_id' => $a->tenant_id, 'user_id' => $a->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        LessonProgress::create(['tenant_id' => $b->tenant_id, 'user_id' => $b->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(CentralCourseResource\Pages\ListCourses::class)
            ->assertSee('Primeiros passos')
            ->assertTableColumnStateSet('clientes', 2, $course)
            ->assertTableColumnStateSet('usuarios', 2, $course);
    }

    public function test_migration_turns_the_academy_on_for_existing_contracts_without_overriding_choices(): void
    {
        $mk = fn (string $n, array $f) => Plan::create(['name' => $n, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $f]);
        $lista = $mk('Contrato lista', ['tabela_assets']);
        $mapa = $mk('Contrato mapa', ['tabela_assets' => true]);
        $recusou = $mk('Contrato recusou', ['tabela_assets' => true, 'tabela_courses' => false]);

        (require database_path('migrations/2026_10_02_130000_enable_academy_on_existing_plans.php'))->up();

        $this->assertTrue($lista->fresh()->hasFeature('tabela_courses'));
        $this->assertTrue($mapa->fresh()->hasFeature('tabela_courses'));
        $this->assertFalse($recusou->fresh()->hasFeature('tabela_courses'));
    }
}
