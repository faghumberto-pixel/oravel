<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentoModeloResource;
use App\Filament\Resources\DocumentoModeloResource\Pages\CreateDocumentoModelo;
use App\Filament\Resources\DocumentoModeloResource\Pages\ListDocumentoModelos;
use App\Models\DocumentoModelo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentoModeloImportador;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/** Modelos de documento (orçamento, proposta, contrato): cadastro, padrão único por tipo, isolamento e leitura do arquivo do cliente. */
class DocumentoModeloTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['services.anthropic.key' => 'chave-de-teste']);
    }

    private function empresa(bool $comModulo = true): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $comModulo ? ['tabela_documento_modelos' => true] : ['tabela_clients' => true]]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'dm-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function modelo(Tenant $t, array $extra = []): DocumentoModelo
    {
        return DocumentoModelo::withoutGlobalScopes()->create($extra + ['tenant_id' => $t->id, 'tipo' => 'orcamento', 'nome' => 'Modelo '.uniqid()]);
    }

    private function docx(string $texto): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'dx');
        $zip = new ZipArchive;
        $zip->open($arquivo, ZipArchive::OVERWRITE);
        $paragrafos = implode('', array_map(fn ($l) => '<w:p><w:r><w:t>'.htmlspecialchars($l, ENT_XML1).'</w:t></w:r></w:p>', explode("\n", $texto)));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>'.$paragrafos.'</w:body></w:document>');
        $zip->close();
        $conteudo = file_get_contents($arquivo);
        unlink($arquivo);

        return $conteudo;
    }

    public function test_so_um_padrao_por_tipo_e_empresa(): void
    {
        [$a] = $this->empresa();
        [$b] = $this->empresa();
        $primeiro = $this->modelo($a, ['is_default' => true]);
        $outroTipo = $this->modelo($a, ['tipo' => 'contrato', 'is_default' => true]);
        $deB = $this->modelo($b, ['is_default' => true]);

        $segundo = $this->modelo($a, ['is_default' => true]);

        $this->assertFalse($primeiro->fresh()->is_default, 'o novo padrão do tipo desmarca o anterior');
        $this->assertTrue($segundo->fresh()->is_default);
        $this->assertTrue($outroTipo->fresh()->is_default, 'outro tipo não é afetado');
        $this->assertTrue($deB->fresh()->is_default, 'outra empresa não é afetada');
    }

    public function test_padrao_de_devolve_o_modelo_ativo_da_propria_empresa(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $this->modelo($a, ['nome' => 'Da A', 'is_default' => true]);
        $this->modelo($b, ['nome' => 'Da B', 'is_default' => true]);
        $inativo = $this->modelo($a, ['tipo' => 'contrato', 'nome' => 'Inativo', 'is_default' => true, 'is_active' => false]);

        $this->actingAs($adminA);

        $this->assertSame('Da A', DocumentoModelo::padraoDe('orcamento')->nome);
        $this->assertNull(DocumentoModelo::padraoDe('contrato'), 'modelo inativo não vale');
        $this->assertNull(DocumentoModelo::padraoDe('proposta'), 'sem modelo: usa o padrão do sistema');
        $this->assertNotNull($inativo);
    }

    public function test_a_lista_mostra_so_os_modelos_da_propria_empresa(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $meu = $this->modelo($a, ['nome' => 'Meu orçamento']);
        $alheio = $this->modelo($b, ['nome' => 'Orçamento alheio']);
        $this->actingAs($adminA);

        Livewire::test(ListDocumentoModelos::class)->assertCanSeeTableRecords([$meu])->assertCanNotSeeTableRecords([$alheio]);
    }

    public function test_cria_um_modelo_pela_tela_e_o_tipo_e_obrigatorio(): void
    {
        [$a, $admin] = $this->empresa();
        $this->actingAs($admin);

        Livewire::test(CreateDocumentoModelo::class)->fillForm(['nome' => 'Sem tipo'])->call('create')->assertHasFormErrors(['tipo' => 'required']);

        Livewire::test(CreateDocumentoModelo::class)
            ->fillForm(['tipo' => 'contrato', 'nome' => 'Contrato de locação', 'cabecalho' => 'Locadora X', 'campos' => [['titulo' => 'CLÁUSULA 1 – OBJETO', 'texto' => 'Locação de {{cliente}}.']]])
            ->call('create')->assertHasNoFormErrors();

        $m = DocumentoModelo::withoutGlobalScopes()->where('tenant_id', $a->id)->firstOrFail();
        $this->assertSame('contrato', $m->tipo);
        $this->assertSame('CLÁUSULA 1 – OBJETO', $m->campos[0]['titulo']);
    }

    public function test_sem_o_modulo_no_contrato_o_recurso_fica_negado(): void
    {
        [, $admin] = $this->empresa(false);
        $this->actingAs($admin);

        $this->assertFalse(DocumentoModeloResource::canViewAny());
    }

    public function test_importador_le_o_word_do_cliente_e_normaliza_a_estrutura(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'nome' => 'Contrato padrão', 'cabecalho' => "Locadora X\nCNPJ 00.000.000/0001-00", 'rodape' => 'Foro de Campinas', 'termos' => null, 'validade_dias' => 15,
            'campos' => [['titulo' => 'CLÁUSULA 1 – OBJETO', 'texto' => 'Locação para {{cliente}}.'], ['titulo' => '', 'texto' => 'sem título é descartado']],
        ])]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10], 'stop_reason' => 'end_turn'], 200)]);

        $r = app(DocumentoModeloImportador::class)->analisar($this->docx("Locadora X\nCLÁUSULA 1 – OBJETO\nLocação para o cliente."), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'contrato');

        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertSame('Contrato padrão', $r['data']['nome']);
        $this->assertSame(15, $r['data']['validade_dias']);
        $this->assertCount(1, $r['data']['campos'], 'seção sem título é descartada');
        $this->assertNull($r['data']['termos']);

        // O texto do Word foi enviado à IA (e não o binário).
        Http::assertSent(fn ($req) => str_contains(json_encode($req->data(), JSON_UNESCAPED_UNICODE), 'Locação para o cliente'));
    }

    public function test_extrai_o_texto_do_docx_e_recusa_formato_desconhecido(): void
    {
        $imp = app(DocumentoModeloImportador::class);

        $this->assertSame("Linha um\nLinha dois & três", $imp->textoDoDocx($this->docx("Linha um\nLinha dois & três")));
        $this->assertSame('', $imp->textoDoDocx('isto não é um zip'));

        $r = $imp->analisar('x', 'application/zip', 'orcamento');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('imagem', $r['error']);

        $this->assertFalse($imp->analisar('x', 'image/png', 'tipo-inventado')['ok']);
    }
}
