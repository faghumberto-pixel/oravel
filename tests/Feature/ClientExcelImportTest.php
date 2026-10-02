<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientExcelImporter;
use App\Support\ClientImport\ClientImportColumns;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ClientExcelImportTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $name = 'Locadora'): Tenant
    {
        $plan = Plan::create(['name' => 'P '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function sheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clientes').'.xlsx';
        $titles = array_column(ClientImportColumns::all(), 'title');
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('Clientes');
        $w->addRow(Row::fromValues(array_column(ClientImportColumns::all(), 'group')));
        $w->addRow(Row::fromValues($titles));
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues(array_map(fn ($t) => $r[$t] ?? null, $titles)));
        }
        $w->close();

        return $path;
    }

    public function test_importa_cliente_com_todos_os_blocos(): void
    {
        $t = $this->tenant();
        $r = app(ClientExcelImporter::class)->import($this->sheet([[
            'Razão Social' => 'Construtora Exemplo Ltda', 'Nome Fantasia' => 'Exemplo', 'CNPJ' => '12.345.678/0001-90', 'Nicho' => 'construção civil',
            'Cidade' => 'Campinas', 'UF' => 'sp', 'CEP' => '13010-000', 'E-mail Principal' => 'CONTATO@exemplo.com.br',
            'Responsável na Obra' => 'João', 'Representante Legal' => 'Maria',
        ]]), $t);

        $this->assertSame([], $r['errors']);
        $c = Client::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('Construtora Exemplo Ltda', $c->name);
        $this->assertSame('construcao_civil', $c->activity_type);
        $this->assertSame('SP', $c->state);
        $this->assertSame('contato@exemplo.com.br', $c->email);
        $this->assertSame('13010-000', $c->zip_code);
        $this->assertSame('João', $c->site_manager);
        $this->assertNull($c->portal_access_enabled_at);
    }

    public function test_simulacao_nao_grava_e_duplicado_por_cnpj_e_ignorado_ou_atualizado(): void
    {
        $t = $this->tenant();
        $imp = app(ClientExcelImporter::class);
        $dry = $imp->import($this->sheet([['Razão Social' => 'A', 'CNPJ' => '11222333000181']]), $t, false, true);
        $this->assertSame(1, $dry['created']);
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $imp->import($this->sheet([['Razão Social' => 'A', 'CNPJ' => '11.222.333/0001-81', 'Cidade' => 'Campinas']]), $t);
        $skip = $imp->import($this->sheet([['Razão Social' => 'A novo nome', 'CNPJ' => '11222333000181', 'Cidade' => 'Outra']]), $t);
        $this->assertSame(1, $skip['skipped']);
        $this->assertSame('Campinas', Client::withoutGlobalScopes()->where('tenant_id', $t->id)->value('city'));

        $upd = $imp->import($this->sheet([['Razão Social' => 'A novo nome', 'CNPJ' => '11222333000181']]), $t, true);
        $this->assertSame(1, $upd['updated']);
        $c = Client::withoutGlobalScopes()->where('tenant_id', $t->id)->first();
        $this->assertSame('A novo nome', $c->name);
        $this->assertSame('Campinas', $c->city); // em branco não apaga
    }

    public function test_erros_sao_reportados_e_linhas_boas_gravadas(): void
    {
        $t = $this->tenant();
        $r = app(ClientExcelImporter::class)->import($this->sheet([
            ['Razão Social' => 'Ok'],
            ['Razão Social' => 'Doc ruim', 'CNPJ' => '123'],
            ['Razão Social' => null, 'Cidade' => 'X'],
            ['Razão Social' => 'Email ruim', 'E-mail Principal' => 'sem-arroba'],
            ['Razão Social' => 'UF ruim', 'UF' => 'Sao Paulo'],
            ['Razão Social' => 'Nicho ruim', 'Nicho' => 'Espacial'],
            ['Razão Social' => 'ok'],
        ]), $t);

        $this->assertSame(1, $r['created']);
        $this->assertCount(6, $r['errors']);
        $this->assertSame(['Ok'], Client::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('name')->all());
    }

    public function test_nao_mistura_clientes_entre_empresas(): void
    {
        $a = $this->tenant('Empresa A');
        $b = $this->tenant('Empresa B');
        app(ClientExcelImporter::class)->import($this->sheet([['Razão Social' => 'Mesmo Cliente', 'CNPJ' => '11222333000181']]), $a);
        $r = app(ClientExcelImporter::class)->import($this->sheet([['Razão Social' => 'Mesmo Cliente', 'CNPJ' => '11222333000181']]), $b);

        $this->assertSame(1, $r['created']);
        $this->assertSame(1, Client::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
    }

    public function test_modelo_e_reconhecido_e_acao_da_tela_importa(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
        ClientImportColumns::writeTemplate($path);
        $t = $this->tenant('Topmixx teste');
        $empty = app(ClientExcelImporter::class)->import($path, $t);
        $this->assertSame([], $empty['errors']);
        $this->assertSame(0, $empty['created']);

        $t->plan->update(['features' => [Client::saasFeatureKey()]]);
        $u = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $file = UploadedFile::fake()->createWithContent('clientes.xlsx', file_get_contents($this->sheet([['Razão Social' => 'Pela Tela']])));
        Livewire::test(ListClients::class)
            ->callAction('importarExcel', ['arquivo' => $file, 'simular' => false, 'atualizar' => false])
            ->assertHasNoActionErrors();

        $this->assertSame($t->id, Client::withoutGlobalScopes()->where('name', 'Pela Tela')->value('tenant_id'));
    }
}
