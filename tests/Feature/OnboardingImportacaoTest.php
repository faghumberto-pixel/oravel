<?php

namespace Tests\Feature;

use App\Filament\Pages\PrimeirosPassos;
use App\Models\Client;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientExcelImporter;
use App\Support\ClientImport\ClientImportColumns;
use App\Support\ClientImport\ClientImportRelatorio;
use App\Support\ExcelImport\XlsxReader;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Importação de clientes dos Primeiros passos: validação por linha, limite, exemplo ignorado, arquivo de rejeitadas e isolamento. */
class OnboardingImportacaoTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(): Tenant
    {
        $plano = Pacotes::planoDeVitrine('servicos');
        $plano->update(['is_active' => true]);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function planilha(array $linhas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'onb').'.xlsx';
        $titulos = array_column(ClientImportColumns::all(), 'title');
        $w = new Writer;
        $w->openToFile($caminho);
        $w->getCurrentSheet()->setName('Clientes');
        $w->addRow(Row::fromValues(array_column(ClientImportColumns::all(), 'group')));
        $w->addRow(Row::fromValues($titulos));
        foreach ($linhas as $l) {
            $w->addRow(Row::fromValues(array_map(fn ($t) => $l[$t] ?? null, $titulos)));
        }
        $w->close();

        return $caminho;
    }

    private function importar(Tenant $t, array $linhas, ?int $limite = null): array
    {
        return app(ClientExcelImporter::class)->import($this->planilha($linhas), $t, maxRows: $limite);
    }

    public function test_valid_rows_are_imported_and_the_summary_is_clear(): void
    {
        $t = $this->tenant();
        $r = $this->importar($t, [
            ['Razão Social' => 'Alfa Ltda', 'CNPJ' => '11.444.777/0001-61', 'E-mail Principal' => 'a@alfa.com.br', 'WhatsApp' => '(19) 99999-8888'],
            ['Razão Social' => 'Beta Ltda', 'CNPJ' => '11.222.333/0001-81'],
        ]);

        $this->assertSame(2, $r['created']);
        $this->assertSame('2 importados', ClientImportRelatorio::resumo($r));
        $this->assertSame(2, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_invalid_rows_are_skipped_with_the_reason_and_the_others_are_saved(): void
    {
        $t = $this->tenant();
        $r = $this->importar($t, [
            ['Razão Social' => 'Boa Ltda', 'CNPJ' => '11.444.777/0001-61'],
            ['Razão Social' => 'E-mail ruim', 'E-mail Principal' => 'sem-arroba'],
            ['Razão Social' => 'Documento curto', 'CNPJ' => '123'],
            ['Razão Social' => 'Telefone curto', 'WhatsApp' => '9999'],
            ['CNPJ' => '22.333.444/0001-55'],
        ]);

        $this->assertSame(1, $r['created']);
        $this->assertCount(4, $r['errors']);
        $this->assertSame('1 importado, 4 com erro (linhas 4, 5, 6 e 7)', ClientImportRelatorio::resumo($r));
        $this->assertSame(1, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $motivos = collect($r['rejected'])->pluck('message', 'line');
        $this->assertStringContainsString('e-mail válido', $motivos[4]);
        $this->assertStringContainsString('11 (CPF) nem 14 (CNPJ)', $motivos[5]);
        $this->assertStringContainsString('DDD e número', $motivos[6]);
        $this->assertStringContainsString('Razão Social é obrigatória', $motivos[7]);
    }

    public function test_single_error_summary_and_rejected_csv_has_line_reason_and_original_cells(): void
    {
        $t = $this->tenant();
        $r = $this->importar($t, [['Razão Social' => 'Ruim', 'E-mail Principal' => 'x'], ['Razão Social' => 'Ok']]);

        $this->assertSame('1 importado, 1 com erro (linha 3)', ClientImportRelatorio::resumo($r));

        $csv = ClientImportRelatorio::csvDasRejeitadas($r['rejected']);
        $linhas = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))), array_fill(0, 2, ';'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(['Linha', 'Motivo do erro', 'Razão Social'], array_slice($linhas[0], 0, 3));
        $this->assertSame('3', $linhas[1][0]);
        $this->assertStringContainsString('e-mail válido', $linhas[1][1]);
        $this->assertSame('Ruim', $linhas[1][2]);
        $this->assertCount(2, $linhas, 'só a linha rejeitada vai para o arquivo');
    }

    public function test_duplicates_in_the_sheet_and_existing_clients_are_not_created_twice(): void
    {
        $t = $this->tenant();
        Client::create(['tenant_id' => $t->id, 'name' => 'Já existe', 'document' => '11.444.777/0001-61']);

        $r = $this->importar($t, [
            ['Razão Social' => 'Já existe', 'CNPJ' => '11.444.777/0001-61'],
            ['Razão Social' => 'Nova', 'CNPJ' => '11.222.333/0001-81'],
            ['Razão Social' => 'Nova de novo', 'CNPJ' => '11.222.333/0001-81'],
        ]);

        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertCount(1, $r['errors']);
        $this->assertStringContainsString('repetido na planilha', $r['errors'][0]['message']);
        $this->assertSame(2, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_the_same_document_in_another_company_never_conflicts(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        Client::create(['tenant_id' => $b->id, 'name' => 'Cliente da B', 'document' => '11.444.777/0001-61']);

        $r = $this->importar($a, [['Razão Social' => 'Cliente da A', 'CNPJ' => '11.444.777/0001-61']]);

        $this->assertSame(1, $r['created']);
        $this->assertSame(1, Client::withoutGlobalScopes()->where('tenant_id', $a->id)->count());
        $this->assertSame('Cliente da B', Client::withoutGlobalScopes()->where('tenant_id', $b->id)->value('name'), 'a empresa B não foi tocada');
    }

    public function test_sheet_above_the_limit_is_refused_without_saving_anything(): void
    {
        $t = $this->tenant();
        $linhas = array_map(fn ($i) => ['Razão Social' => "Cliente {$i}"], range(1, 6));

        $r = $this->importar($t, $linhas, limite: 5);

        $this->assertSame(0, $r['created']);
        $this->assertStringContainsString('limite é de 5 por envio', $r['errors'][0]['message']);
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(500, config('onboarding.importacao_max_linhas'));
    }

    public function test_template_has_one_marked_example_row_and_it_is_never_imported(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
        ClientImportColumns::writeTemplate($caminho, linhaDeExemplo: true);

        $linhas = XlsxReader::rows($caminho, 'Clientes');
        $this->assertCount(3, $linhas, 'grupos, títulos e UMA linha de exemplo');
        $this->assertStringStartsWith('(EXEMPLO - apague esta linha)', (string) array_values($linhas)[2][0]);

        $t = $this->tenant();
        $r = app(ClientExcelImporter::class)->import($caminho, $t);
        $this->assertSame(0, $r['created']);
        $this->assertSame([], $r['errors']);
    }

    public function test_the_screen_imports_for_the_logged_company_and_offers_the_rejected_file(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
        $t = $this->tenant();
        $u = User::create(['name' => 'Ana Lima', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($u);

        $arquivo = UploadedFile::fake()->createWithContent('clientes.xlsx', file_get_contents($this->planilha([
            ['Razão Social' => 'Pela Tela'], ['Razão Social' => 'Com erro', 'E-mail Principal' => 'ruim'],
        ])));

        Livewire::test(PrimeirosPassos::class)->callAction('importar', ['arquivo' => $arquivo])->assertHasNoActionErrors()
            ->assertNotified('1 importado, 1 com erro (linha 4)');

        $this->assertSame($t->id, Client::withoutGlobalScopes()->where('name', 'Pela Tela')->value('tenant_id'));
        $this->assertCount(1, Storage::disk('local')->files('onboarding-rejeitadas'));
        $nome = basename(Storage::disk('local')->files('onboarding-rejeitadas')[0]);
        $this->assertStringStartsWith($t->id.'-', $nome);

        // O link é assinado e restrito à empresa que importou.
        $url = URL::temporarySignedRoute('onboarding.rejeitadas', now()->addMinutes(5), ['arquivo' => $nome]);
        $this->get($url)->assertOk()->assertHeader('content-disposition');
        $this->get(route('onboarding.rejeitadas', ['arquivo' => $nome]))->assertForbidden();

        $outra = $this->tenant();
        $v = User::create(['name' => 'Outro', 'email' => 'o-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $outra->id]);
        $v->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->actingAs($v);
        $this->get($url)->assertNotFound();
    }
}
