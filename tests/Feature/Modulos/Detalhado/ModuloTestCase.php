<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Models\Tenant;
use App\Support\SaaSRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * Base dos testes detalhados por modulo. Cada modulo e uma subclasse que
 * declara o Model/Resource e como criar um registro; os testes abaixo (perfis,
 * plano, isolamento entre tenants, listagem e formulario) rodam sozinhos em
 * cada uma. A subclasse acrescenta so o que e especifico do modulo.
 *
 * Perfis (mesmo criterio de App\Support\AppProfile):
 *  - administrador: role `admin` do tenant, passa em tudo que o plano inclui;
 *  - tecnico: role `tecnico` + permissao de ler OS, sem permissao no modulo;
 *  - colaborador: sem role nenhuma.
 * Tecnico e colaborador so entram num modulo com a permissao
 * {ler|criar|editar|excluir}_{slug}, concedida aqui direto no usuario.
 *
 * DatabaseTransactions (nunca RefreshDatabase): config/database.php fixa pgsql,
 * entao rode com DB_DATABASE apontando para um banco descartavel.
 */
abstract class ModuloTestCase extends PerfisTestCase
{
    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    /** Cria (e salva) um registro do modulo no tenant informado. */
    abstract protected function novoRegistro(Tenant $tenant): Model;

    /** @return class-string|null Resource do painel admin; null se o modulo nao tem tela propria. */
    protected function resource(): ?string
    {
        return null;
    }

    /**
     * Dados validos para o formulario de criacao do Resource. Vazio = o modulo
     * nao tem pagina de criacao propria e o teste de criacao e pulado.
     *
     * @return array<string, mixed>
     */
    protected function dadosDoFormulario(): array
    {
        return [];
    }

    /** Campos que o formulario exige (devem gerar erro quando vazios). */
    protected function camposObrigatorios(): array
    {
        return [];
    }

    /** Coluna do registro conferida depois de criar pelo formulario. */
    protected function colunaDeBusca(): ?string
    {
        return null;
    }

    protected function slug(): string
    {
        return SaaSRegistry::forModel($this->modelo())['slug'];
    }

    protected function feature(): string
    {
        return SaaSRegistry::forModel($this->modelo())['feature'];
    }

    protected function paginaDoResource(string $chave): ?string
    {
        $resource = $this->resource();
        $page = $resource ? ($resource::getPages()[$chave] ?? null) : null;

        return $page?->getPage();
    }

    // ------------------------------------------------- perfis e permissoes

    public function test_administrador_acessa_tudo_do_modulo(): void
    {
        $registro = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);

        $this->assertTrue($this->a['admin']->can('viewAny', $this->modelo()), 'viewAny');
        $this->assertTrue($this->a['admin']->can('create', $this->modelo()), 'create');
        $this->assertTrue($this->a['admin']->can('update', $registro), 'update');
        $this->assertTrue($this->a['admin']->can('delete', $registro), 'delete');
    }

    public function test_tecnico_e_colaborador_sem_permissao_nao_acessam(): void
    {
        $registro = $this->novoRegistro($this->tenantA);

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $user = $this->a[$perfil];
            $this->actingAs($user);

            $this->assertFalse($user->can('viewAny', $this->modelo()), "{$perfil} viewAny");
            $this->assertFalse($user->can('create', $this->modelo()), "{$perfil} create");
            $this->assertFalse($user->can('update', $registro), "{$perfil} update");
            $this->assertFalse($user->can('delete', $registro), "{$perfil} delete");
        }
    }

    public function test_permissao_de_leitura_libera_so_a_listagem(): void
    {
        $registro = $this->novoRegistro($this->tenantA);

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $user = $this->a[$perfil];
            $this->conceder($user, $this->slug(), ['ler']);
            $this->actingAs($user);

            $this->assertTrue($user->can('viewAny', $this->modelo()), "{$perfil} viewAny");
            $this->assertTrue($user->can('view', $registro), "{$perfil} view");
            $this->assertFalse($user->can('create', $this->modelo()), "{$perfil} create");
            $this->assertFalse($user->can('update', $registro), "{$perfil} update");
            $this->assertFalse($user->can('delete', $registro), "{$perfil} delete");
        }
    }

    public function test_cada_acao_exige_a_sua_permissao(): void
    {
        $registro = $this->novoRegistro($this->tenantA);
        $user = $this->a['colaborador'];
        $this->conceder($user, $this->slug(), ['criar', 'editar']);
        $this->actingAs($user);

        $this->assertTrue($user->can('create', $this->modelo()), 'create');
        $this->assertTrue($user->can('update', $registro), 'update');
        $this->assertFalse($user->can('delete', $registro), 'delete sem excluir_');
        $this->assertFalse($user->can('viewAny', $this->modelo()), 'viewAny sem ler_');
    }

    public function test_modulo_fora_do_plano_nega_ate_o_administrador(): void
    {
        $semModulo = array_values(array_diff(
            array_filter(array_column(SaaSRegistry::modules(), 'feature')),
            [$this->feature()]
        ));
        [, $perfis] = $this->montarTenant($semModulo);
        $this->actingAs($perfis['admin']);

        $this->assertFalse($perfis['admin']->can('viewAny', $this->modelo()));
        $this->assertFalse($perfis['admin']->can('create', $this->modelo()));
    }

    public function test_permissao_de_outro_modulo_nao_vale_para_este(): void
    {
        $user = $this->a['colaborador'];
        $this->conceder($user, 'ordem_servico', ['ler', 'criar', 'editar', 'excluir']);
        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', $this->modelo()));
    }

    // ------------------------------------------------- isolamento entre tenants

    public function test_cada_tenant_ve_so_os_proprios_registros(): void
    {
        $doA = $this->novoRegistro($this->tenantA);
        $doB = $this->novoRegistro($this->tenantB);

        $this->actingAs($this->a['admin']);
        $ids = $this->modelo()::query()->pluck('id')->all();

        $this->assertContains($doA->getKey(), $ids);
        $this->assertNotContains($doB->getKey(), $ids, 'vazou registro de outro tenant');
    }

    /**
     * Duas camadas: o escopo do Eloquent nem carrega o registro do outro tenant,
     * e a policy tambem nega (AbstractPolicy::check() compara o tenant do
     * registro ate para o administrador; antes retornava true antes disso).
     */
    public function test_admin_de_um_tenant_nao_alcanca_registro_de_outro(): void
    {
        $doB = $this->novoRegistro($this->tenantB);
        $this->actingAs($this->a['admin']);

        $this->assertNull($this->modelo()::query()->find($doB->getKey()));
        $this->assertSame(0, $this->modelo()::query()->whereKey($doB->getKey())->count());
    }

    public function test_policy_nega_ao_admin_agir_em_registro_de_outro_tenant(): void
    {
        $doB = $this->novoRegistro($this->tenantB);
        $this->actingAs($this->a['admin']);

        foreach (['view', 'update', 'delete'] as $acao) {
            $this->assertFalse($this->a['admin']->can($acao, $doB), $acao);
        }
        $this->assertTrue($this->b['admin']->can('update', $doB), 'o admin dono continua podendo');
    }

    public function test_registro_novo_herda_o_tenant_de_quem_criou(): void
    {
        $this->actingAs($this->a['admin']);
        $registro = $this->novoRegistro($this->tenantA);

        $this->assertSame((string) $this->tenantA->id, (string) $registro->fresh()->tenant_id);
    }

    // ------------------------------------------------- telas (Filament)

    public function test_listagem_mostra_so_registros_do_tenant_e_so_para_quem_pode(): void
    {
        $pagina = $this->paginaDoResource('index');
        if (! $pagina) {
            $this->markTestSkipped('modulo sem listagem propria');
        }
        $doA = $this->novoRegistro($this->tenantA);
        $doB = $this->novoRegistro($this->tenantB);

        $this->actingAs($this->a['admin']);
        Livewire::test($pagina)->assertSuccessful()
            ->assertCanSeeTableRecords([$doA])
            ->assertCanNotSeeTableRecords([$doB]);

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            Livewire::test($pagina)->assertForbidden();
        }

        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);
        Livewire::test($pagina)->assertSuccessful()->assertCanSeeTableRecords([$doA]);
    }

    public function test_formulario_cria_registro_no_tenant_do_administrador(): void
    {
        $pagina = $this->paginaDoResource('create');
        if (! $pagina || ! $this->dadosDoFormulario()) {
            $this->markTestSkipped('modulo sem formulario de criacao proprio');
        }
        $antes = $this->modelo()::withoutGlobalScopes()->count();

        $this->actingAs($this->a['admin']);
        Livewire::test($pagina)
            ->fillForm($this->dadosDoFormulario())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($antes + 1, $this->modelo()::withoutGlobalScopes()->count());
        $criado = $this->modelo()::withoutGlobalScopes()->latest('created_at')->first();
        $this->assertSame((string) $this->tenantA->id, (string) $criado->tenant_id);
    }

    public function test_formulario_exige_os_campos_obrigatorios(): void
    {
        $pagina = $this->paginaDoResource('create');
        if (! $pagina || ! $this->camposObrigatorios()) {
            $this->markTestSkipped('modulo sem formulario de criacao proprio');
        }

        $this->actingAs($this->a['admin']);
        Livewire::test($pagina)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(array_fill_keys($this->camposObrigatorios(), 'required'));
    }

    public function test_formulario_de_criacao_e_barrado_para_quem_nao_pode(): void
    {
        $pagina = $this->paginaDoResource('create');
        if (! $pagina) {
            $this->markTestSkipped('modulo sem formulario de criacao proprio');
        }

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            Livewire::test($pagina)->assertForbidden();
        }

        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);
        Livewire::test($pagina)->assertForbidden();
    }

    public function test_edicao_e_barrada_para_outro_tenant_e_para_quem_so_le(): void
    {
        $pagina = $this->paginaDoResource('edit');
        if (! $pagina) {
            $this->markTestSkipped('modulo sem edicao em pagina propria');
        }
        $doA = $this->novoRegistro($this->tenantA);
        $doB = $this->novoRegistro($this->tenantB);

        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);
        Livewire::test($pagina, ['record' => $doA->getKey()])->assertForbidden();

        $this->actingAs($this->a['admin']);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test($pagina, ['record' => $doB->getKey()]);
    }
}
