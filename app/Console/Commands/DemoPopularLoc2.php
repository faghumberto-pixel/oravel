<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dados de demonstração para a empresa "Loc2 demo" (locadora de equipamentos): clientes, ativos, contratos e
 * medições, manutenção, comercial, estoque, compras, financeiro e RH.
 *
 * Insere direto nas tabelas (sem observers: não manda e-mail, notificação nem grava log de atividade) e só
 * mexe na empresa Loc2 demo. Cada linha criada tem o id anotado em storage/app/demo-loc2-ids.json, então
 * `--remover` apaga exatamente o que este comando criou, sem tocar no que o usuário cadastrou.
 * Sem Faker de propósito: roda em PROD, que instala sem as dependências de desenvolvimento.
 */
class DemoPopularLoc2 extends Command
{
    protected $signature = 'demo:popular-loc2 {--remover : Apaga o que este comando criou} {--force : Cria mesmo se a demonstração já existir}';

    protected $description = 'Popula a empresa Loc2 demo com dados de demonstração (reversível com --remover)';

    private string $tenantId;

    private string $adminId;

    /** @var array<string, string[]> tabela => ids criados */
    private array $criados = [];

    /** @var array<string, true> colunas pedidas que a tabela não tem (ficam de fora e são listadas no fim) */
    private array $descartadas = [];

    /** @var array<string, string[]> apelido => ids (para ligar as tabelas entre si) */
    private array $ref = [];

    public function handle(): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('name', 'Loc2 demo')->first();
        if (! $tenant) {
            $this->error('Empresa "Loc2 demo" não encontrada.');

            return self::FAILURE;
        }
        $this->tenantId = $tenant->id;

        if ($this->option('remover')) {
            return $this->remover();
        }

        if (File::exists($this->arquivo()) && ! $this->option('force')) {
            $this->warn('A demonstração já foi criada. Use --remover antes, ou --force para criar de novo.');

            return self::FAILURE;
        }

        $admin = DB::table('users')->where('tenant_id', $this->tenantId)->orderBy('created_at')->first();
        if (! $admin) {
            $this->error('A empresa não tem usuário administrador.');

            return self::FAILURE;
        }
        $this->adminId = $admin->id;

        foreach (['departamentos', 'cargosEFuncionarios', 'clientes', 'categoriasEAtivos', 'contratos', 'medicoes',
            'comercial', 'unidadesEEstoque', 'compras', 'financeiro', 'frota', 'manutencao', 'relacionamento'] as $etapa) {
            try {
                DB::transaction(fn () => $this->{$etapa}());
                $this->info("✔ $etapa");
            } catch (\Throwable $e) {
                $this->error("✘ $etapa: ".Str::limit($e->getMessage(), 300));
            }
        }

        File::put($this->arquivo(), json_encode($this->criados));
        if ($this->descartadas) {
            $this->warn('Colunas que não existem e foram ignoradas: '.implode(', ', array_keys($this->descartadas)));
        }
        $this->info('Linhas criadas: '.collect($this->criados)->flatten()->count());

        return self::SUCCESS;
    }

    // ───────────────────────── etapas ─────────────────────────

    private function departamentos(): void
    {
        foreach (['Operações', 'Comercial', 'Manutenção', 'Financeiro', 'Logística', 'Almoxarifado'] as $nome) {
            if (! DB::table('departments')->where('tenant_id', $this->tenantId)->where('name', $nome)->exists()) {
                $this->ref['dep'][$nome] = $this->mk('departments', ['name' => $nome]);
            } else {
                $this->ref['dep'][$nome] = DB::table('departments')->where('tenant_id', $this->tenantId)->where('name', $nome)->value('id');
            }
        }
    }

    private function cargosEFuncionarios(): void
    {
        $cargos = [];
        foreach (['Técnico de Manutenção', 'Operador de Máquinas', 'Motorista', 'Vendedor', 'Almoxarife', 'Analista Financeiro'] as $c) {
            $cargos[$c] = $this->mk('job_roles', ['name' => $c]);
        }

        $pessoas = [
            ['Carlos Eduardo Ramos', '31245890710', 'Técnico de Manutenção', 'Manutenção'],
            ['Josiane Pereira Lima', '29811365402', 'Vendedor', 'Comercial'],
            ['Anderson Souza Prado', '40577123688', 'Motorista', 'Logística'],
            ['Marcos Vinícius Teixeira', '11790648255', 'Operador de Máquinas', 'Operações'],
            ['Patrícia Gomes Duarte', '23468011947', 'Almoxarife', 'Almoxarifado'],
            ['Fernanda Alves Moreira', '35102788461', 'Analista Financeiro', 'Financeiro'],
        ];
        foreach ($pessoas as [$nome, $cpf, $cargo, $dep]) {
            $this->ref['func'][$cargo] = $this->mk('employees', [
                'name' => $nome, 'cpf' => $cpf, 'role_title' => $cargo, 'status' => 'active',
                'admission_date' => now()->subMonths(rand(4, 40))->toDateString(), 'job_role_id' => $cargos[$cargo],
                'department_id' => $this->ref['dep'][$dep] ?? null, 'daily_work_hours' => 8,
            ]);
        }

        $this->ref['motorista'] = $this->mk('fleet_drivers', [
            'name' => 'Anderson Souza Prado', 'cpf' => '40577123688', 'cnh_number' => '04518836901', 'employment_type' => 'clt',
            'cnh_category' => 'D', 'cnh_expiry_date' => now()->addYears(2)->toDateString(), 'phone' => '(19) 99812-4470', 'active' => true,
        ]);
    }

    private function clientes(): void
    {
        $dados = [
            ['Construtora Horizonte Ltda', '12.345.678/0001-90', 'Campinas', 'SP', 'Rua Barão de Jaguara, 1250', 'Eng. Roberto Nunes'],
            ['MRV Engenharia Obras Sul', '08.343.492/0001-20', 'Sumaré', 'SP', 'Av. Rebouças, 480', 'Mestre Cláudio Freitas'],
            ['Indústria Metalúrgica Paulista S/A', '45.987.321/0001-55', 'Hortolândia', 'SP', 'Rod. Anhanguera km 108', 'Marcela Duarte'],
            ['Logística Rápida Transportes', '27.654.118/0001-03', 'Paulínia', 'SP', 'Av. José Paulino, 900', 'Anderson Bastos'],
            ['Terraplenagem Santa Clara', '33.210.765/0001-77', 'Valinhos', 'SP', 'Estrada da Capela, 77', 'Sr. Antônio Clara'],
            ['Shopping Center Plaza Norte', '19.876.543/0001-61', 'Campinas', 'SP', 'Av. John Boyd Dunlop, 3900', 'Luciana Prates'],
            ['Agroindustrial Vale do Sol', '56.432.109/0001-84', 'Limeira', 'SP', 'Rod. Limeira-Piracicaba km 4', 'Eng. Paulo Mendes'],
            ['Condomínio Residencial Bosque Azul', '41.098.765/0001-12', 'Indaiatuba', 'SP', 'Rua das Palmeiras, 250', 'Síndica Helena Coutinho'],
        ];
        foreach ($dados as $i => [$nome, $cnpj, $cidade, $uf, $end, $contato]) {
            $slug = Str::slug(Str::before($nome, ' '));
            $this->ref['cli'][] = $this->mk('clients', [
                'name' => $nome, 'legal_name' => $nome, 'fantasy_name' => Str::before($nome, ' Ltda'), 'cpf_cnpj' => $cnpj, 'document' => $cnpj,
                'contact_name' => $contato, 'city' => $cidade, 'uf' => $uf, 'state' => $uf, 'address' => $end,
                'neighborhood' => 'Centro', 'cep' => '13000-'.str_pad((string) (100 + $i * 7), 3, '0'), 'zip_code' => '13000-'.str_pad((string) (100 + $i * 7), 3, '0'),
                'phone' => '(19) 3'.rand(200, 899).'-'.rand(1000, 9999), 'whatsapp' => '(19) 99'.rand(100, 899).'-'.rand(1000, 9999),
                'email' => "contato@{$slug}.demo.br", 'email_financial' => "financeiro@{$slug}.demo.br", 'email_purchasing' => "compras@{$slug}.demo.br",
                'activity_type' => ['Construção civil', 'Indústria', 'Logística', 'Terraplenagem', 'Comércio', 'Agroindústria', 'Condomínio'][$i % 7],
                'tax_regime' => 'lucro_presumido', 'credit_score' => rand(620, 940), 'site_manager' => $contato,
                'latitude' => -22.9 - $i * 0.03, 'longitude' => -47.06 - $i * 0.02,
                'check_internal_fraud' => true, 'check_blacklist' => true, 'check_credit_bureau' => true, 'doc_cnpj' => true, 'doc_statute' => true, 'doc_id' => true,
            ]);
        }
    }

    private function categoriasEAtivos(): void
    {
        $cats = [];
        foreach (['Empilhadeira', 'Plataforma Elevatória', 'Gerador', 'Compressor de Ar', 'Retroescavadeira', 'Caminhão Munck'] as $c) {
            $existente = DB::table('asset_categories')->where('tenant_id', $this->tenantId)->where('name', $c)->value('id');
            $cats[$c] = $existente ?: $this->mk('asset_categories', ['name' => $c]);
        }
        $this->ref['cat'] = $cats;

        foreach (['Equipamentos de Elevação', 'Geração de Energia', 'Veículos Pesados'] as $g) {
            $this->mk('checklist_groups', ['name' => $g]);
        }
        $this->ref['grupo_check'] = DB::table('checklist_groups')->where('tenant_id', $this->tenantId)->value('id');

        $ativos = [
            ['Empilhadeira Hyster H50FT', 'Empilhadeira', 'Hyster', 'H50FT', 2021, 'EMP-001', 'locado', 185000, 5000, 'kg'],
            ['Empilhadeira Yale GLP 25', 'Empilhadeira', 'Yale', 'GLP25', 2020, 'EMP-002', 'disponivel', 142000, 2500, 'kg'],
            ['Plataforma Genie GS-3246', 'Plataforma Elevatória', 'Genie', 'GS-3246', 2022, 'PLT-001', 'locado', 98000, 11.5, 'm'],
            ['Plataforma Haulotte H12SX', 'Plataforma Elevatória', 'Haulotte', 'H12SX', 2019, 'PLT-002', 'manutencao', 124000, 12, 'm'],
            ['Gerador Cummins 150 kVA', 'Gerador', 'Cummins', 'C150D5', 2021, 'GER-001', 'locado', 76000, 150, 'kVA'],
            ['Gerador Stemac 80 kVA', 'Gerador', 'Stemac', 'S80', 2018, 'GER-002', 'disponivel', 41000, 80, 'kVA'],
            ['Compressor Atlas Copco XAS 185', 'Compressor de Ar', 'Atlas Copco', 'XAS185', 2020, 'CMP-001', 'locado', 67000, 185, 'cfm'],
            ['Retroescavadeira CAT 416F2', 'Retroescavadeira', 'Caterpillar', '416F2', 2022, 'RET-001', 'locado', 410000, 0.9, 'm³'],
            ['Retroescavadeira JCB 3CX', 'Retroescavadeira', 'JCB', '3CX', 2019, 'RET-002', 'disponivel', 355000, 1, 'm³'],
            ['Caminhão Munck Volvo FMX', 'Caminhão Munck', 'Volvo', 'FMX 440', 2021, 'MNK-001', 'locado', 690000, 20, 't'],
        ];
        foreach ($ativos as $i => [$nome, $cat, $fab, $mod, $ano, $tag, $status, $valor, $cap, $un]) {
            $locado = $status === 'locado';
            $this->ref['ativo'][$tag] = $this->mk('assets', [
                'name' => $nome, 'tag' => $tag, 'patrimonio' => 'PAT-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'status' => $status,
                'asset_category' => $cat, 'asset_category_id' => $cats[$cat], 'fabricante' => $fab, 'modelo' => $mod, 'manufacturing_year' => $ano, 'ano_modelo' => $ano,
                'serial_number' => strtoupper(Str::random(3)).rand(100000, 999999), 'acquisition_value' => $valor, 'residual_value' => round($valor * 0.35),
                'acquisition_date' => Carbon::create($ano, rand(1, 12), rand(1, 28))->toDateString(), 'useful_life_years' => 10,
                'capacity_value' => $cap, 'capacity_unit' => $un, 'criticality' => ['alta', 'media', 'baixa'][$i % 3],
                'horimetro_inicial' => 0, 'horimetro_atual' => rand(400, 5200), 'last_horimetro' => rand(400, 5200),
                'client_id' => $locado ? $this->ref['cli'][$i % count($this->ref['cli'])] : null,
                'checklist_group_id' => $this->ref['grupo_check'], 'is_vehicle' => $cat === 'Caminhão Munck',
                'latitude' => -22.9 - $i * 0.02, 'longitude' => -47.05 - $i * 0.015,
            ]);
            $this->mk('fleet_statuses', ['asset_id' => $this->ref['ativo'][$tag], 'is_available' => $status === 'disponivel', 'capacity_label' => $cap.' '.$un]);
        }
    }

    private function contratos(): void
    {
        $locados = DB::table('assets')->where('tenant_id', $this->tenantId)->where('status', 'locado')->whereNotNull('client_id')->get();
        foreach ($locados as $i => $ativo) {
            $inicio = now()->subMonths(rand(2, 8))->startOfMonth();
            $id = $this->mk('contracts', [
                'client_id' => $ativo->client_id, 'asset_id' => $ativo->id, 'contract_number' => 'LOC-'.now()->year.'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'status' => 'Ativo', 'start_date' => $inicio->toDateString(), 'end_date' => $inicio->copy()->addMonths(12)->toDateString(),
                'price' => [8500, 6200, 4800, 3900, 15800, 21500][$i % 6], 'payment_method' => 'boleto', 'billing_type' => 'mensal', 'is_active' => true,
                'cidade_obra' => 'Campinas', 'uf_obra' => 'SP', 'endereco_obra' => 'Canteiro de obras — acesso pela portaria 2',
                'usage_purpose' => 'Operação em obra/planta do cliente', 'frete_incluso' => (bool) ($i % 2), 'multa_rescisoria' => 10,
                'prohibit_sublease' => true, 'responsavel_manutencao' => 'locadora', 'initial_horimeter' => $ativo->horimetro_atual ?? 0,
                'observations' => 'Contrato de demonstração.',
            ]);
            $this->ref['contrato'][] = ['id' => $id, 'asset' => $ativo->id, 'client' => $ativo->client_id, 'inicio' => $inicio, 'preco' => [8500, 6200, 4800, 3900, 15800, 21500][$i % 6]];

            $this->mk('rental_hour_franchises', [
                'contract_id' => $id, 'included_hours_per_period' => 176, 'period_type' => 'monthly', 'overage_rate_per_hour' => 85.5,
                'effective_from' => $inicio->toDateString(),
            ]);
        }
    }

    private function medicoes(): void
    {
        foreach ($this->ref['contrato'] ?? [] as $i => $c) {
            for ($m = 2; $m >= 1; $m--) {
                $ini = now()->subMonths($m)->startOfMonth();
                $fim = $ini->copy()->endOfMonth();
                $this->mk('contract_measurements', [
                    'contract_id' => $c['id'], 'reference_period_start' => $ini->toDateString(), 'reference_period_end' => $fim->toDateString(),
                    'total_days_in_period' => $ini->daysInMonth, 'prorated_days' => $ini->daysInMonth, 'status' => $m === 2 ? 'invoiced' : 'awaiting_approval',
                    'total_base_amount' => $c['preco'], 'total_excess_hours_amount' => 0, 'total_extras_amount' => 0, 'total_amount' => $c['preco'],
                ]);
            }
            $this->mk('rental_overage_charges', [
                'contract_id' => $c['id'], 'asset_id' => $c['asset'], 'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(), 'hours_included' => 176, 'hours_used' => 200,
                'hours_overage' => 24, 'amount' => 24 * 85.5, 'status' => 'pending',
            ]);
        }
    }

    private function comercial(): void
    {
        foreach ([[0, 'Locação mensal de empilhadeira', 'enviado', 8500], [1, 'Locação de 2 plataformas — obra residencial', 'aprovado', 12400], [4, 'Gerador para evento corporativo', 'rascunho', 3600]] as [$cli, $desc, $status, $total]) {
            $quote = $this->mk('quotes', [
                'client_id' => $this->ref['cli'][$cli], 'type' => 'interno', 'status' => $status, 'technical_report' => $desc,
                'total_value' => $total, 'assigned_user_id' => $this->adminId, 'sent_at' => $status === 'rascunho' ? null : now()->subDays(2),
            ]);
            $this->mk('quote_items', ['quote_id' => $quote, 'type' => 'servico', 'description' => $desc, 'quantity' => 1, 'unit_price' => $total, 'subtotal' => $total]);
        }

        foreach ([[0, 'rascunho', 8500], [1, 'aprovada_interna', 12400], [5, 'aceita_pelo_cliente', 21500]] as [$cli, $status, $total]) {
            $prop = $this->mk('proposta_comerciais', [
                'client_id' => $this->ref['cli'][$cli], 'seller_user_id' => $this->adminId, 'status' => $status, 'total_value' => $total,
                'valid_until' => now()->addDays(20)->toDateString(), 'terms' => 'Pagamento em 28 dias. Frete e montagem inclusos na primeira locação.',
                'cabecalho' => 'Proposta comercial de locação de equipamentos',
            ]);
            $this->mk('proposta_comercial_itens', [
                'proposta_comercial_id' => $prop, 'type' => 'locacao', 'description' => 'Locação mensal de equipamento', 'quantity' => 1, 'unit_price' => $total,
                'unit_period' => 'mes', 'subtotal' => $total, 'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addMonths(6)->toDateString(),
            ]);
        }

        foreach ([[2, 'sugestao', 'Elogio ao atendimento da equipe técnica', 'A equipe chegou no prazo e resolveu a pane do gerador em poucas horas.'],
            [3, 'reclamacao', 'Atraso na entrega da plataforma', 'A plataforma chegou 1 dia depois do combinado.'],
            [5, 'elogio', 'Operador muito atencioso', 'Parabéns ao operador que acompanhou a montagem.']] as $i => [$cli, $tipo, $assunto, $texto]) {
            $this->mk('client_feedbacks', [
                'client_id' => $this->ref['cli'][$cli], 'protocol' => 'FB-'.now()->format('ymd').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'type' => $tipo, 'subject' => $assunto, 'description' => $texto, 'status' => ['recebido', 'em_analise', 'concluido'][$i],
            ]);
        }
    }

    private function unidadesEEstoque(): void
    {
        $this->ref['unidade'] = $this->mk('internal_units', ['name' => 'Matriz — Campinas', 'code' => 'MTZ', 'address' => 'Av. das Indústrias, 1500', 'number' => '1500', 'neighborhood' => 'Distrito Industrial', 'city' => 'Campinas', 'state' => 'SP', 'cep' => '13054-700', 'is_active' => true]);
        $filial = $this->mk('internal_units', ['name' => 'Filial — Sumaré', 'code' => 'SMR', 'address' => 'Rua do Pátio, 300', 'number' => '300', 'neighborhood' => 'Parque Industrial', 'city' => 'Sumaré', 'state' => 'SP', 'cep' => '13170-000', 'is_active' => true]);

        $this->ref['armazem'] = $this->mk('warehouses', ['name' => 'Almoxarifado Central', 'code' => 'ALM-01', 'city' => 'Campinas', 'state' => 'SP', 'type' => 'fixed', 'is_active' => true]);
        $this->mk('warehouses', ['name' => 'Pátio de Equipamentos', 'code' => 'PAT-01', 'city' => 'Sumaré', 'state' => 'SP', 'type' => 'fixed', 'is_active' => true]);

        foreach (['A-01', 'A-02', 'B-01'] as $i => $codigo) {
            $this->ref['local'][] = $this->mk('storage_locations', [
                'internal_unit_id' => $this->ref['unidade'], 'context' => 'almoxarifado', 'code' => $codigo, 'row' => $i + 1, 'column' => 1, 'label' => "Prateleira $codigo", 'is_active' => true,
            ]);
        }

        $cats = [];
        foreach (['Filtros', 'Hidráulica', 'Elétrica', 'Lubrificantes', 'Pneus e Rodas'] as $c) {
            $cats[$c] = $this->mk('material_categories', ['name' => $c]);
        }
        $this->ref['mcat'] = $cats;

        $mats = [
            ['Filtro de óleo motor', 'Filtros', 'FIL-001', 48.9, 120.0, 14, 5, 30], ['Filtro de ar primário', 'Filtros', 'FIL-002', 76.5, 160.0, 9, 4, 20],
            ['Filtro de combustível', 'Filtros', 'FIL-003', 38.0, 95.0, 22, 6, 30], ['Óleo hidráulico ISO 68 (20L)', 'Lubrificantes', 'LUB-001', 410.0, 690.0, 6, 3, 12],
            ['Óleo motor 15W40 (20L)', 'Lubrificantes', 'LUB-002', 330.0, 540.0, 11, 4, 16], ['Mangueira hidráulica 1/2"', 'Hidráulica', 'HID-001', 92.0, 210.0, 18, 6, 30],
            ['Cilindro hidráulico de inclinação', 'Hidráulica', 'HID-002', 1850.0, 3100.0, 2, 1, 4], ['Bateria 12V 150Ah', 'Elétrica', 'ELE-001', 780.0, 1250.0, 4, 2, 8],
            ['Alternador 24V', 'Elétrica', 'ELE-002', 920.0, 1480.0, 1, 2, 5], ['Pneu superelástico 28x9-15', 'Pneus e Rodas', 'PNE-001', 640.0, 1050.0, 8, 4, 16],
            ['Roda de empilhadeira 6.50-10', 'Pneus e Rodas', 'PNE-002', 285.0, 480.0, 5, 2, 10], ['Correia dentada gerador', 'Elétrica', 'ELE-003', 135.0, 290.0, 7, 3, 12],
        ];
        foreach ($mats as $i => [$nome, $cat, $sku, $custo, $preco, $atual, $min, $max]) {
            $this->ref['material'][] = $this->mk('materials', [
                'name' => $nome, 'sku' => $sku, 'material_category_id' => $cats[$cat], 'unit_cost' => $custo, 'price' => $preco,
                'current_stock' => $atual, 'min_stock' => $min, 'max_stock' => $max, 'unit_of_measure' => 'UN', 'part_number' => 'PN-'.rand(10000, 99999),
                'storage_location_id' => $this->ref['local'][$i % 3], 'last_purchase_price' => $custo, 'warranty_days' => 90,
            ]);
            $this->mk('material_stock_movements', [
                'material_id' => end($this->ref['material']), 'type' => 'entrada', 'quantity' => $atual, 'balance_after' => $atual,
                'reason' => 'Saldo inicial da demonstração', 'created_by_user_id' => $this->adminId, 'to_location_id' => $this->ref['unidade'],
            ]);
        }

        $pc = DB::table('part_categories')->where('tenant_id', $this->tenantId)->value('id')
            ?? $this->mk('part_categories', ['name' => 'Peças de reposição', 'slug' => 'pecas-de-reposicao-demo']);
        foreach ([['Kit de vedação cilindro', 'PCA-001', 185.0], ['Sensor de horímetro', 'PCA-002', 240.0], ['Painel de comando', 'PCA-003', 1320.0]] as [$nome, $sku, $custo]) {
            $part = $this->mk('parts', ['part_category_id' => $pc, 'sku' => $sku, 'name' => $nome, 'unit_of_measure' => 'UN', 'cost_price' => $custo, 'minimum_stock' => 2, 'maximum_stock' => 12, 'is_active' => true]);
            $this->mk('stock_movements', [
                'part_id' => $part, 'warehouse_id' => $this->ref['armazem'], 'movement_type' => 'entrada', 'quantity' => 6, 'balance_before' => 0, 'balance_after' => 6,
                'unit_cost' => $custo, 'total_cost' => $custo * 6, 'created_by' => $this->adminId, 'notes' => 'Saldo inicial da demonstração',
            ]);
        }

        $this->mk('material_stock_takes', ['conducted_by_user_id' => $this->adminId, 'status' => 'finalizado', 'notes' => 'Inventário mensal — demonstração', 'finished_at' => now()->subDays(2), 'internal_unit_id' => $this->ref['unidade']]);
    }

    private function compras(): void
    {
        $forn = [];
        foreach ([['Distribuidora Hidrotec Peças', '61.234.567/0001-10', 'vendas@hidrotec.demo.br'], ['Lubrimax Lubrificantes', '23.987.654/0001-28', 'comercial@lubrimax.demo.br'],
            ['Baterias Moura Distribuidor', '14.321.987/0001-45', 'pedidos@moura.demo.br'], ['Pneus Brasil Atacado', '09.765.432/0001-99', 'atacado@pneusbrasil.demo.br']] as [$nome, $cnpj, $email]) {
            $forn[] = $this->mk('suppliers', ['name' => $nome, 'document' => $cnpj, 'email' => $email, 'phone' => '(11) 4'.rand(100, 999).'-'.rand(1000, 9999),
                'termo_lgpd' => true, 'compliance_ceis_cnep' => false, 'lista_trabalho_escravo' => false]);
        }
        $this->ref['fornecedor'] = $forn;

        foreach ([[0, 'recebida', 3820.0], [1, 'aberta', 2460.0], [2, 'aberta', 3120.0]] as $i => [$f, $status, $total]) {
            $po = $this->mk('purchase_orders', [
                'supplier_id' => $forn[$f], 'created_by_user_id' => $this->adminId, 'status' => $status, 'total_value' => $total,
                'expected_delivery_date' => now()->addDays(7 + $i)->toDateString(),
            ]);
            $mat = $this->ref['material'][$i * 2];
            $this->mk('purchase_order_items', ['purchase_order_id' => $po, 'material_id' => $mat, 'quantity' => 10, 'unit_price' => $total / 10, 'quantity_received' => $status === 'recebida' ? 10 : 0]);
            if ($status === 'recebida') {
                $this->mk('goods_receipts', ['purchase_order_id' => $po, 'received_by_user_id' => $this->adminId, 'received_at' => now()->subDays(3), 'notes' => 'Conferido e armazenado', 'invoice_number' => 'NF-48213', 'internal_unit_id' => $this->ref['unidade']]);
            }
        }

        foreach ([['aguardando_aprovacao', 'Reposição de filtros para a revisão mensal', 1], ['entregue', 'Óleo hidráulico para a plataforma PLT-002', 3]] as [$status, $nota, $mat]) {
            $req = $this->mk('material_requests', ['user_id' => $this->adminId, 'status' => $status, 'notes' => $nota, 'requested_at' => now()->subDays(4), 'priority' => 'normal', 'origin' => 'manual']);
            $this->mk('material_request_items', ['material_request_id' => $req, 'material_id' => $this->ref['material'][$mat], 'quantity' => 6]);
        }
    }

    private function financeiro(): void
    {
        $cats = [];
        foreach (['Locação de equipamentos', 'Manutenção e peças', 'Combustível', 'Folha de pagamento', 'Impostos', 'Aluguel do pátio'] as $c) {
            $cats[$c] = $this->mk('bill_categories', ['name' => $c]);
        }

        foreach ($this->ref['contrato'] ?? [] as $i => $c) {
            foreach ([[-35, 'pago'], [-5, 'pendente'], [25, 'pendente']] as [$dias, $status]) {
                $this->mk('account_receivables', [
                    'client_id' => $c['client'], 'contract_id' => $c['id'], 'bill_category_id' => $cats['Locação de equipamentos'],
                    'description' => 'Locação mensal — contrato '.($i + 1), 'amount' => $c['preco'], 'due_date' => now()->addDays($dias)->toDateString(),
                    'status' => $dias < 0 && $status === 'pendente' ? 'atrasado' : $status, 'payment_date' => $status === 'pago' ? now()->subDays(33)->toDateString() : null, 'mes' => now()->addDays($dias)->format('m'), 'ano' => now()->addDays($dias)->format('Y'),
                ]);
            }
        }

        foreach ([['Hidrotec — peças hidráulicas', 3820.0, -2, 'pago', 'Manutenção e peças'], ['Lubrimax — óleos e graxas', 2460.0, 6, 'pendente', 'Manutenção e peças'],
            ['Posto Ipiranga — combustível frota', 4180.0, 10, 'pendente', 'Combustível'], ['Aluguel do pátio — Sumaré', 9800.0, 18, 'pendente', 'Aluguel do pátio'],
            ['Folha de pagamento', 32500.0, 5, 'pendente', 'Folha de pagamento'], ['DAS Simples Nacional', 6120.0, -8, 'atrasado', 'Impostos']] as [$desc, $valor, $dias, $status, $cat]) {
            $this->mk('account_payables', [
                'description' => $desc, 'amount' => $valor, 'due_date' => now()->addDays($dias)->toDateString(), 'status' => $status, 'bill_category_id' => $cats[$cat],
                'payment_date' => $status === 'pago' ? now()->subDays(1)->toDateString() : null, 'mes' => now()->addDays($dias)->format('m'), 'ano' => now()->addDays($dias)->format('Y'),
            ]);
        }

        foreach (array_slice($this->ref['contrato'] ?? [], 0, 3) as $c) {
            $this->mk('billing_plans', ['client_id' => $c['client'], 'contract_id' => $c['id'], 'frequency' => 'mensal', 'amount' => $c['preco'], 'due_day' => 10, 'active' => true]);
        }
    }

    private function frota(): void
    {
        $caminhao = $this->ref['ativo']['MNK-001'] ?? null;
        if ($caminhao) {
            $this->mk('frota_saidas_veiculo', [
                'ativo_id' => $caminhao, 'motorista_id' => null, 'condutor_nome' => 'Anderson Souza Prado', 'finalidade' => 'locacao',
                'destino' => 'Obra Construtora Horizonte — Campinas', 'motivo' => 'Entrega de equipamento locado', 'saida_em' => now()->subHours(5),
                'odometro_saida' => 48210, 'combustivel_saida' => 'cheio', 'registrado_por' => $this->adminId, 'origem' => 'Matriz — Campinas',
            ]);
        }
        if ($this->ref['contrato'] ?? null) {
            $c = $this->ref['contrato'][0];
            $this->mk('equipment_pickup_requests', [
                'client_id' => $c['client'], 'asset_id' => $c['asset'], 'contract_id' => $c['id'], 'requested_at' => now()->subDays(1), 'status' => 'solicitado',
                'notes' => 'Cliente solicitou retirada ao fim do mês.',
            ]);
        }
        $this->mk('traccar_devices', ['user_id' => $this->adminId, 'traccar_device_id' => 1001, 'identifier' => '358240051111110']);
    }

    private function manutencao(): void
    {
        // ordens de serviço, planos e avarias já existem no tenant (criados no onboarding); aqui só garantimos o tipo de ação
        // e penduramos algumas requisições de peças nas OS existentes.
        $ordens = DB::table('maintenance_orders')->where('tenant_id', $this->tenantId)->limit(3)->pluck('id');
        foreach ($ordens as $i => $os) {
            $material = $this->ref['material'][$i] ?? null;
            if ($material) {
                $this->mk('parts_requests', ['maintenance_order_id' => $os, 'material_id' => $material, 'quantity' => 2 + $i, 'status' => 'pendente']);
            }
        }

        if (! DB::table('maintenance_action_types')->where('tenant_id', $this->tenantId)->exists()) {
            foreach (['Troca de óleo', 'Revisão preventiva', 'Reparo hidráulico'] as $i => $t) {
                $this->mk('maintenance_action_types', ['name' => $t, 'sort_order' => $i, 'is_active' => true]);
            }
        }
    }

    private function relacionamento(): void
    {
        $this->mk('chat_rooms', ['type' => 'group', 'title' => 'Equipe de Manutenção']);
        $this->mk('chat_rooms', ['type' => 'group', 'title' => 'Comercial e Operações']);
    }

    // ───────────────────────── infra ─────────────────────────

    /** Insere uma linha, ignorando colunas que a tabela não tem, e anota o id para o --remover. */
    private function mk(string $tabela, array $dados): string|int
    {
        $colunas = $this->colunas($tabela);
        $linha = array_intersect_key($dados, array_flip($colunas));
        foreach (array_diff(array_keys($dados), $colunas) as $ausente) {
            $this->descartadas[$tabela.'.'.$ausente] = true;
        }
        if (in_array('tenant_id', $colunas, true)) {
            $linha['tenant_id'] = $this->tenantId;
        }
        $agora = now();
        foreach (['created_at', 'updated_at'] as $c) {
            if (in_array($c, $colunas, true)) {
                $linha[$c] = $agora;
            }
        }

        foreach ($linha as $k => $v) {
            if (is_array($v)) {
                $linha[$k] = json_encode($v);
            }
        }

        if ($this->tipoDoId($tabela) === 'uuid') {
            $linha['id'] = (string) Str::uuid();
            DB::table($tabela)->insert($linha);
            $id = $linha['id'];
        } else {
            $id = DB::table($tabela)->insertGetId($linha);
        }

        $this->criados[$tabela][] = (string) $id;

        return $id;
    }

    private function colunas(string $tabela): array
    {
        static $cache = [];

        return $cache[$tabela] ??= Schema::getColumnListing($tabela);
    }

    private function tipoDoId(string $tabela): string
    {
        static $cache = [];

        return $cache[$tabela] ??= (string) DB::table('information_schema.columns')
            ->where('table_name', $tabela)->where('column_name', 'id')->where('table_schema', 'public')->value('data_type');
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-loc2-ids.json');
    }

    private function remover(): int
    {
        if (! File::exists($this->arquivo())) {
            $this->warn('Nada a remover (arquivo de ids não encontrado).');

            return self::SUCCESS;
        }

        $criados = json_decode(File::get($this->arquivo()), true) ?: [];
        $total = 0;
        DB::transaction(function () use ($criados, &$total) {
            foreach (array_reverse($criados, true) as $tabela => $ids) {
                $consulta = DB::table($tabela)->whereIn('id', $ids);
                if (Schema::hasColumn($tabela, 'tenant_id')) {
                    $consulta->where('tenant_id', $this->tenantId);
                }
                $total += $consulta->delete();
            }
        });
        File::delete($this->arquivo());
        $this->info("Removidas $total linhas de demonstração.");

        return self::SUCCESS;
    }
}
