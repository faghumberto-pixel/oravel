<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\EpiSpecification;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Exemplos para os Paineis de Controle por area (RH, Administrativo, Compras, Planejamento): colaboradores, certificacoes, ponto de hoje, EPI,
 * assinaturas, pedidos de compra e planos de preventiva. Insere direto nas tabelas (sem observers: sem e-mail, notificacao nem log) e anota o id
 * de cada linha em storage/app/demo-paineis-ids.json, entao --remover apaga exatamente o que este comando criou. Sem Faker: roda em PROD.
 */
class DemoPopularPaineis extends Command
{
    protected $signature = 'demo:popular-paineis {--tenant=Locadora Demo : nome da empresa} {--remover : Apaga o que este comando criou}';

    protected $description = 'Popula os Paineis de Controle por area com exemplos (reversivel com --remover)';

    /** @var array<string, list<string>> */
    private array $criados = [];

    /** @var array<string, array<int, string>> */
    private array $colunas = [];

    private string $tid;

    private ?string $adminId = null;

    public function handle(): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('name', $this->option('tenant'))->first();

        if (! $tenant) {
            $this->error('Empresa "'.$this->option('tenant').'" não encontrada.');

            return self::FAILURE;
        }

        $this->tid = (string) $tenant->id;

        if ($this->option('remover')) {
            return $this->remover();
        }

        if (File::exists($this->arquivo())) {
            $this->warn('Os exemplos já existem. Use --remover antes para recriar.');

            return self::FAILURE;
        }

        $this->adminId = DB::table('users')->where('tenant_id', $this->tid)->orderBy('created_at')->value('id');

        DB::transaction(function () {
            $funcionarios = $this->colaboradores();
            $this->certificacoes($funcionarios);
            $this->pontoDeHoje($funcionarios);
            $this->epi($funcionarios);
            $this->assinaturas();
            $this->pedidosDeCompra();
            $this->planosDePreventiva();
            File::put($this->arquivo(), json_encode(['tenant' => $this->tid, 'criados' => $this->criados], JSON_PRETTY_PRINT));
        });

        foreach ($this->criados as $tabela => $ids) {
            $this->line(sprintf('  %-24s %d', $tabela, count($ids)));
        }

        $this->info('Exemplos criados.');

        return self::SUCCESS;
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-paineis-ids.json');
    }

    /** Insere uma linha, descartando colunas que a tabela nao tem, e anota o id. */
    private function mk(string $tabela, array $linha): string
    {
        $this->colunas[$tabela] ??= Schema::getColumnListing($tabela);
        $id = (string) Str::uuid();
        $linha = ['id' => $id, 'tenant_id' => $this->tid, 'created_at' => now(), 'updated_at' => now()] + $linha;
        DB::table($tabela)->insert(array_intersect_key($linha, array_flip($this->colunas[$tabela])));
        $this->criados[$tabela][] = $id;

        return $id;
    }

    /** @return list<array{id: string, status: string}> */
    private function colaboradores(): array
    {
        $deptos = DB::table('departments')->where('tenant_id', $this->tid)->pluck('id')->all();
        $nomes = [
            ['Carlos Eduardo Silva', 'Mecânico', 'ativo', 26], ['Marcos Vinicius Souza', 'Eletricista', 'ativo', 20], ['Ana Paula Ribeiro', 'Analista administrativa', 'ativo', 14],
            ['João Pedro Almeida', 'Operador de munk', 'ativo', 9], ['Fernanda Lima', 'Compradora', 'ativo', 7], ['Ricardo Gomes', 'Técnico de segurança', 'ativo', 5],
            ['Paulo Henrique Dias', 'Motorista', 'ativo', 3], ['Luciana Castro', 'Assistente financeira', 'ativo', 2], ['Roberto Nunes', 'Mecânico', 'afastado', 30],
            ['Sérgio Barbosa', 'Soldador', 'ativo', 12], ['Juliana Prado', 'Planejadora de manutenção', 'ativo', 1], ['Thiago Martins', 'Auxiliar de pátio', 'desligado', 22],
        ];
        $saida = [];

        foreach ($nomes as $i => [$nome, $cargo, $status, $mesesAtras]) {
            $id = $this->mk('employees', [
                'name' => $nome, 'cpf' => '9990000'.str_pad((string) ($i + 10), 4, '0', STR_PAD_LEFT), 'role_title' => $cargo, 'status' => $status,
                'admission_date' => now()->subMonths($mesesAtras)->toDateString(), 'department_id' => $deptos ? $deptos[$i % count($deptos)] : null, 'daily_work_hours' => 8,
            ]);
            $saida[] = ['id' => $id, 'status' => $status];
        }

        return $saida;
    }

    private function certificacoes(array $funcionarios): void
    {
        $normas = ['NR-10', 'NR-11', 'NR-12', 'NR-18', 'NR-35'];
        $deslocamentos = [-25, -4, 9, 18, 33, 52, 120, 240, 400]; // dias: vencidas, a vencer e em dia

        foreach ($funcionarios as $i => $f) {
            if ($f['status'] === 'desligado') {
                continue;
            }

            foreach ([0, 1, 2] as $k) {
                $dias = $deslocamentos[($i * 3 + $k) % count($deslocamentos)];
                $this->mk('employee_certifications', [
                    'employee_id' => $f['id'], 'norma' => $normas[($i + $k) % count($normas)], 'data_emissao' => now()->addDays($dias)->subYear()->toDateString(),
                    'data_validade' => now()->addDays($dias)->toDateString(),
                ]);
            }
        }
    }

    private function pontoDeHoje(array $funcionarios): void
    {
        foreach ($funcionarios as $i => $f) {
            if ($f['status'] !== 'ativo' || $i % 4 === 3) { // alguns ainda sem ponto
                continue;
            }

            $hora = now()->setTime(7, 0)->addMinutes(($i * 7) % 50);
            $this->mk('time_clocks', ['employee_id' => $f['id'], 'client_uuid' => (string) Str::uuid(), 'tipo' => 'entrada', 'recorded_at' => $hora, 'device_recorded_at' => $hora, 'sync_status' => 'synced', 'synced_at' => $hora]);
        }
    }

    private function epi(array $funcionarios): void
    {
        $materiais = DB::table('materials')->where('tenant_id', $this->tid)->pluck('id')->all();
        $unidade = DB::table('internal_units')->where('tenant_id', $this->tid)->value('id');

        if (! $materiais) {
            return;
        }

        $prazos = [-12, -2, 6, 20, 45];

        foreach ($funcionarios as $i => $f) {
            if ($f['status'] !== 'ativo') {
                continue;
            }

            $this->mk('epi_deliveries', [
                'employee_id' => $f['id'], 'material_id' => $materiais[$i % count($materiais)], 'internal_unit_id' => $unidade, 'quantity' => 1, 'reason' => 'Entrega de EPI — exemplo',
                'ownership_mode' => EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO, 'status' => 'ativo', 'delivered_at' => now()->subDays(60), 'delivered_by_user_id' => $this->adminId,
                'expected_return_at' => now()->addDays($prazos[$i % count($prazos)])->toDateString(), 'blocked' => false,
            ]);
        }
    }

    private function assinaturas(): void
    {
        $contratos = DB::table('contracts')->where('tenant_id', $this->tid)->limit(8)->pluck('id')->all();
        $situacoes = [['pending', 5], ['pending', 12], ['pending', -3], ['signed', 20], ['signed', 20], ['expired', -10], ['canceled', 5], ['pending', 2]];

        foreach ($contratos as $i => $contrato) {
            [$status, $dias] = $situacoes[$i % count($situacoes)];
            $this->mk('document_signatures', [
                'signable_type' => 'App\\Models\\Contract', 'signable_id' => $contrato, 'token' => Str::random(40), 'signer_name' => '[Exemplo] Representante do cliente '.($i + 1),
                'signer_email' => 'exemplo'.($i + 1).'@exemplo.com.br', 'status' => $status, 'expires_at' => now()->addDays($dias), 'signed_at' => $status === 'signed' ? now()->subDays(2) : null,
            ]);
        }
    }

    private function pedidosDeCompra(): void
    {
        $fornecedores = DB::table('suppliers')->where('tenant_id', $this->tid)->pluck('id')->all();

        if (! $fornecedores || ! $this->adminId) {
            return;
        }

        $casos = [['aberta', -9, 4200], ['aberta', -3, 1850.5], ['aberta', 5, 9300], ['parcialmente_recebida', -1, 2750], ['aberta', 12, 640.9], ['recebida', -20, 5100], ['aberta', -15, 3320], ['parcialmente_recebida', 8, 1980]];

        foreach ($casos as $i => [$status, $dias, $valor]) {
            $criado = now()->subDays(abs($dias) + 10 + $i * 6);
            $id = $this->mk('purchase_orders', [
                'supplier_id' => $fornecedores[$i % count($fornecedores)], 'status' => $status, 'total_value' => $valor, 'expected_delivery_date' => now()->addDays($dias)->toDateString(),
                'created_by_user_id' => $this->adminId,
            ]);
            DB::table('purchase_orders')->where('id', $id)->update(['created_at' => $criado, 'updated_at' => $criado]);
        }
    }

    private function planosDePreventiva(): void
    {
        $ativos = Asset::withoutGlobalScopes()->where('tenant_id', $this->tid)->where('horimetro_atual', '>=', 150)->orderByDesc('horimetro_atual')->limit(14)->get();
        $nomes = ['Troca de óleo e filtros', 'Revisão geral', 'Lubrificação', 'Inspeção de segurança'];

        foreach ($ativos as $i => $a) {
            $h = (int) $a->horimetro_atual;
            [$intervalo, $ultima] = match ($i % 3) {
                0 => [100, max(0, $h - 130)],   // vencida
                1 => [100, max(0, $h - 70)],    // vence em ~30 h
                default => [250, max(0, $h - 60)], // em dia
            };
            $this->mk('maintenance_plans', [
                'asset_id' => $a->id, 'name' => '[Exemplo] '.$nomes[$i % count($nomes)], 'interval_hours' => $intervalo, 'last_service_hours' => $ultima, 'is_active' => true,
                'auto_create_order' => false, 'is_critical' => $i % 4 === 0, 'source' => 'manual',
            ]);
        }
    }

    private function remover(): int
    {
        if (! File::exists($this->arquivo())) {
            $this->warn('Nada para remover (arquivo de ids não existe).');

            return self::SUCCESS;
        }

        $dados = json_decode(File::get($this->arquivo()), true);

        if (($dados['tenant'] ?? null) !== $this->tid) {
            $this->error('O arquivo de ids é de outra empresa. Nada foi apagado.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($dados) {
            // Ordem inversa de criacao: quem depende de outra tabela sai primeiro.
            foreach (array_reverse($dados['criados'], true) as $tabela => $ids) {
                DB::table($tabela)->where('tenant_id', $this->tid)->whereIn('id', $ids)->delete();
            }
        });

        File::delete($this->arquivo());
        $this->info('Exemplos removidos.');

        return self::SUCCESS;
    }
}
