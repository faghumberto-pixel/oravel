<?php

namespace App\Services\Demo;

use App\Models\FrotaMulta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Popula MATERIAIS, FINANCEIRO, FROTA e LOGISTICA da Locadora Demo (so' ela), para as telas "Visao geral" desses modulos
 * nao aparecerem vazias. Idempotente: apaga as linhas destas tabelas no tenant demo e recria, de forma deterministica.
 * Insere direto nas tabelas (sem observers: nao manda e-mail nem notificacao). Tudo fictício.
 */
class LocadoraDemoOperacional
{
    private const SEMENTE = 20261111;

    private const TAG_VEICULO = 'DEMO-VEI-';

    /** Tabelas limpas e recriadas por este servico (so' do tenant demo). A ordem respeita as chaves estrangeiras. */
    private const TABELAS = [
        'freight_records', 'equipment_pickup_requests', 'equipment_movements', 'freight_carriers', 'fleet_vehicles',
        'frota_sinistros', 'frota_multas', 'frota_trocas_oleo', 'frota_abastecimentos', 'frota_saidas_veiculo',
        'maintenance_order_materials', 'material_stock_movements', 'purchase_order_items', 'purchase_orders', 'material_requests',
        'materials', 'material_categories', 'suppliers', 'account_receivables', 'account_payables', 'bill_categories', 'cost_centers',
    ];

    private string $tenantId;

    private Carbon $agora;

    private string $admin;

    /** @var array<string, string> */
    private array $categorias = [];

    /** @var list<array{id: string, nome: string, custo: float}> */
    private array $materiais = [];

    /** @var list<string> */
    private array $fornecedores = [];

    /** @var list<array{id: string, nome: string, placa: string}> */
    private array $veiculos = [];

    /** @var list<string> */
    private array $clientes = [];

    /** @var list<string> */
    private array $equipamentos = [];

    private const MATERIAIS = [
        ['Hidráulica', 'Mangueira hidráulica 3/4" (m)', 48.0, 40, 200, 'm'], ['Hidráulica', 'Cilindro hidráulico de elevação', 1850.0, 2, 8, 'un'],
        ['Hidráulica', 'Óleo hidráulico ISO 68 (balde 20L)', 420.0, 6, 24, 'un'], ['Hidráulica', 'Kit de vedação de cilindro', 210.0, 5, 20, 'un'],
        ['Hidráulica', 'Bomba hidráulica de engrenagens', 2300.0, 1, 4, 'un'], ['Hidráulica', 'Válvula direcional 4 vias', 980.0, 2, 6, 'un'],
        ['Elétrica', 'Bateria tracionária 12V 200Ah', 1650.0, 4, 16, 'un'], ['Elétrica', 'Contator 80A', 185.0, 4, 14, 'un'],
        ['Elétrica', 'Painel de comando completo', 3200.0, 1, 3, 'un'], ['Elétrica', 'Motor de arranque 24V', 1450.0, 2, 6, 'un'],
        ['Elétrica', 'Alternador 24V 70A', 1280.0, 2, 5, 'un'], ['Elétrica', 'Chicote elétrico universal', 340.0, 3, 10, 'un'],
        ['Lubrificantes e Filtros', 'Óleo de motor 15W40 (balde 20L)', 520.0, 8, 30, 'un'], ['Lubrificantes e Filtros', 'Filtro de óleo', 58.0, 20, 80, 'un'],
        ['Lubrificantes e Filtros', 'Filtro de ar primário', 96.0, 12, 40, 'un'], ['Lubrificantes e Filtros', 'Filtro de combustível', 74.0, 15, 50, 'un'],
        ['Lubrificantes e Filtros', 'Graxa multiuso (kg)', 32.0, 10, 40, 'kg'], ['Lubrificantes e Filtros', 'Aditivo radiador (galão)', 85.0, 6, 20, 'un'],
        ['Pneus e Rodízios', 'Pneu maciço 6.00-9', 640.0, 4, 16, 'un'], ['Pneus e Rodízios', 'Rodízio giratório 8"', 120.0, 10, 40, 'un'],
        ['Pneus e Rodízios', 'Pneu 295/80 R22.5 caminhão', 2100.0, 2, 8, 'un'], ['Pneus e Rodízios', 'Câmara de ar aro 22.5', 190.0, 4, 14, 'un'],
        ['Pneus e Rodízios', 'Roda de aço aro 22.5', 980.0, 1, 4, 'un'], ['Pneus e Rodízios', 'Parafuso de roda (jogo)', 160.0, 6, 20, 'un'],
    ];

    private const FORNECEDORES = ['Hidrotec Peças Hidráulicas', 'Eletro Campinas Componentes', 'Lubrimax Distribuidora', 'Pneus Bandeirantes', 'Mecânica Central Autopeças'];

    /** [nome, placa, ano, valor, tipo de carroceria]. */
    private const VEICULOS = [
        ['Caminhão Munck 12t', 'DEM1A23', 2019, 485000, 'munck'], ['Caminhão Prancha 3 eixos', 'DEM2B34', 2020, 520000, 'prancha'],
        ['Caminhão Toco Baú', 'DEM3C45', 2018, 310000, 'bau'], ['Pick-up Cabine Dupla 4x4', 'DEM4D56', 2022, 245000, 'picape'],
        ['Van de Apoio Técnico', 'DEM5E67', 2021, 188000, 'van'], ['Utilitário de Campo', 'DEM6F78', 2023, 142000, 'utilitario'],
    ];

    private const CATEGORIAS_FINANCEIRAS = ['Locação de equipamentos', 'Manutenção e peças', 'Combustível', 'Folha e encargos', 'Impostos', 'Seguros', 'Estrutura e aluguel', 'Marketing', 'Serviços de manutenção', 'Frete e transporte', 'Venda de equipamentos'];

    public function popular(Tenant $tenant): array
    {
        if (! $tenant->is_demo) {
            throw new \RuntimeException('O operacional só é populado na empresa de demonstração.');
        }

        $this->tenantId = (string) $tenant->id;
        $this->agora = Carbon::now();
        mt_srand(self::SEMENTE);

        try {
            return DB::transaction(function () {
                $this->limpar();
                $this->admin = (string) User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->orderBy('created_at')->value('id');
                $this->clientes = DB::table('clients')->where('tenant_id', $this->tenantId)->pluck('id')->map(fn ($v) => (string) $v)->all();
                $this->equipamentos = DB::table('assets')->where('tenant_id', $this->tenantId)->where('tag', 'not like', self::TAG_VEICULO.'%')->pluck('id')->map(fn ($v) => (string) $v)->all();

                $this->criarVeiculos();

                return [
                    'materiais' => $this->criarMateriais(),
                    'financeiro' => $this->criarFinanceiro(),
                    'frota' => $this->criarFrota(),
                    'logistica' => $this->criarLogistica(),
                ];
            });
        } finally {
            mt_srand();
        }
    }

    private function limpar(): void
    {
        DB::table('assets')->where('tenant_id', $this->tenantId)->where('tag', 'like', self::TAG_VEICULO.'%')->get(['id'])->each(function ($a) {
            foreach (['frota_abastecimentos', 'frota_multas', 'frota_sinistros', 'frota_trocas_oleo', 'frota_saidas_veiculo'] as $t) {
                DB::table($t)->where('ativo_id', $a->id)->delete();
            }
        });
        // Pedidos de peca (criados por LocadoraDemoManutencao) apontam para os materiais: saem antes deles.
        DB::table('parts_requests')->where('tenant_id', $this->tenantId)->delete();
        DB::table('material_request_items')->whereIn('material_request_id', DB::table('material_requests')->where('tenant_id', $this->tenantId)->select('id'))->delete();
        DB::table('quote_items')->where('tenant_id', $this->tenantId)->whereNotNull('material_id')->update(['material_id' => null]);
        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)->where('tenant_id', $this->tenantId)->delete();
        }
        DB::table('assets')->where('tenant_id', $this->tenantId)->where('tag', 'like', self::TAG_VEICULO.'%')->delete();
    }

    private function dia(int $diasAtras, int $hora = 10): Carbon
    {
        return $this->agora->copy()->subDays($diasAtras)->setTime($hora, mt_rand(0, 59));
    }

    /** @return string uuid */
    private function inserir(string $tabela, array $dados, ?Carbon $criadoEm = null): string
    {
        $id = (string) Str::uuid();
        $quando = $criadoEm ?? $this->agora;
        DB::table($tabela)->insert(array_merge(['id' => $id], $tabela === 'material_request_items' ? [] : ['tenant_id' => $this->tenantId], $dados, ['created_at' => $quando, 'updated_at' => $quando]));

        return $id;
    }

    private function criarVeiculos(): void
    {
        $cat = $this->inserir('asset_categories', ['name' => 'Veículos', 'slug' => 'veiculos-demo', 'is_active' => true]);
        $docs = [[-12, 18, 40], [22, -5, 200], [9, 28, 300], [60, 14, 90], [150, 210, 24], [300, 12, 180]];

        foreach (self::VEICULOS as $i => [$nome, $placa, $ano, $valor, $carroceria]) {
            $id = $this->inserir('assets', [
                'name' => $nome, 'tag' => self::TAG_VEICULO.($i + 1), 'patrimonio' => 'VEI-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'serial_number' => 'CH'.mt_rand(100000, 999999),
                'status' => 'disponivel', 'grupo' => 'veiculo', 'is_vehicle' => true, 'placa' => $placa, 'manufacturing_year' => $ano, 'ano_modelo' => $ano,
                'acquisition_date' => Carbon::create($ano, mt_rand(1, 12), mt_rand(1, 28))->toDateString(), 'acquisition_value' => $valor, 'residual_value' => round($valor * 0.2, 2),
                'useful_life_years' => 10, 'asset_category' => 'Veículos', 'asset_category_id' => $cat, 'carroceria_tipo' => $carroceria, 'odometro_atual' => mt_rand(40000, 210000),
                'licenciamento_vencimento' => $this->agora->copy()->addDays($docs[$i][0])->toDateString(),
                'seguro_vencimento' => $this->agora->copy()->addDays($docs[$i][1])->toDateString(),
                'ipva_vencimento' => $this->agora->copy()->addDays($docs[$i][2])->toDateString(),
                'description' => 'Veículo fictício da Locadora Demo.',
            ]);
            $this->veiculos[] = ['id' => $id, 'nome' => $nome, 'placa' => $placa];
        }
    }

    // ---------------------------------------------------------------- MATERIAIS

    private function criarMateriais(): array
    {
        foreach (array_unique(array_column(self::MATERIAIS, 0)) as $nome) {
            $this->categorias[$nome] = $this->inserir('material_categories', ['name' => $nome]);
        }
        foreach (self::FORNECEDORES as $i => $nome) {
            $this->fornecedores[] = $this->inserir('suppliers', ['name' => $nome, 'document' => sprintf('33.%03d.%03d/0001-%02d', 100 + $i, 200 + $i, 10 + $i), 'email' => 'vendas'.$i.'@fornecedor-demo.test', 'phone' => '(19) 3200-'.(1000 + $i)]);
        }

        // Alguns itens ficam abaixo do minimo de proposito (alimenta "Itens abaixo do minimo" e a lista de sugestao).
        $abaixo = [1, 3, 6, 9, 14, 19, 21];
        foreach (self::MATERIAIS as $i => [$cat, $nome, $custo, $min, $max, $un]) {
            $atual = in_array($i, $abaixo, true) ? max(0, $min - mt_rand(1, max(1, (int) ($min / 2)))) : mt_rand($min + 2, $max);
            $id = $this->inserir('materials', [
                'sku' => 'MAT-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'name' => $nome, 'unit_cost' => $custo, 'price' => round($custo * 1.35, 2), 'last_purchase_price' => $custo,
                'min_stock' => $min, 'max_stock' => $max, 'current_stock' => $atual, 'material_category_id' => $this->categorias[$cat],
                'supplier_id' => $this->fornecedores[$i % count($this->fornecedores)], 'unit_of_measure' => $un, 'brand_name' => 'Linha Demo', 'warehouse_location' => 'Almoxarifado A'.(1 + $i % 4),
            ]);
            $this->materiais[] = ['id' => $id, 'nome' => $nome, 'custo' => $custo];
        }

        $movs = 0;
        // Saidas: concentradas nos materiais "populares", espalhadas pelos ultimos 100 dias (alimenta consumo, ranking e cobertura).
        for ($n = 0; $n < 70; $n++) {
            $m = $this->materiais[$n % 4 === 0 ? mt_rand(12, 17) : mt_rand(0, count($this->materiais) - 1)];
            $qtd = mt_rand(1, 6);
            $quando = $this->dia(mt_rand(0, 100), mt_rand(8, 17));
            $this->inserir('material_stock_movements', [
                'material_id' => $m['id'], 'type' => 'saida', 'quantity' => $qtd, 'balance_after' => mt_rand(3, 40), 'created_by_user_id' => $this->admin,
                'reason' => ['Aplicado em ordem de serviço', 'Manutenção preventiva', 'Reposição de oficina', 'Troca em campo'][mt_rand(0, 3)], 'document_reference' => 'OS-DEMO-'.mt_rand(10, 220),
            ], $quando);
            $movs++;
        }
        for ($n = 0; $n < 24; $n++) {
            $m = $this->materiais[mt_rand(0, count($this->materiais) - 1)];
            $this->inserir('material_stock_movements', [
                'material_id' => $m['id'], 'type' => 'entrada', 'quantity' => mt_rand(4, 20), 'balance_after' => mt_rand(10, 60), 'created_by_user_id' => $this->admin,
                'reason' => 'Recebimento de pedido de compra', 'document_reference' => 'NF-'.mt_rand(1000, 9999),
            ], $this->dia(mt_rand(0, 100), mt_rand(8, 17)));
            $movs++;
        }
        for ($n = 0; $n < 4; $n++) {
            $m = $this->materiais[mt_rand(0, count($this->materiais) - 1)];
            $this->inserir('material_stock_movements', ['material_id' => $m['id'], 'type' => 'ajuste', 'quantity' => mt_rand(1, 3), 'balance_after' => mt_rand(5, 30), 'created_by_user_id' => $this->admin, 'reason' => 'Ajuste de inventário'], $this->dia(mt_rand(2, 60)));
            $movs++;
        }

        // Pedidos de compra: [status, dias para a entrega prevista (neg = atrasado), dias desde a criacao]
        $pedidos = [
            ['aberta', -4, 18], ['aberta', -1, 12], ['aberta', 0, 9], ['aberta', 3, 6], ['aberta', 8, 4], ['aberta', 12, 2],
            ['parcialmente_recebida', 2, 20], ['parcialmente_recebida', 5, 15],
            ['recebida', -20, 60], ['recebida', -40, 85], ['recebida', -65, 110], ['recebida', -95, 140], ['recebida', -130, 175], ['recebida', -170, 210], ['recebida', -250, 290],
            ['cancelada', -30, 50],
        ];
        foreach ($pedidos as $i => [$status, $entrega, $criado]) {
            $total = 0;
            $itens = [];
            foreach (array_unique([mt_rand(0, count($this->materiais) - 1), mt_rand(0, count($this->materiais) - 1), mt_rand(0, count($this->materiais) - 1)]) as $k) {
                $m = $this->materiais[$k];
                $qtd = mt_rand(3, 15);
                $itens[] = [$m['id'], $qtd, $m['custo']];
                $total += $qtd * $m['custo'];
            }
            $po = $this->inserir('purchase_orders', [
                'supplier_id' => $this->fornecedores[$i % count($this->fornecedores)], 'status' => $status, 'total_value' => $total,
                'expected_delivery_date' => $this->agora->copy()->addDays($entrega)->toDateString(), 'created_by_user_id' => $this->admin,
            ], $this->dia($criado));
            foreach ($itens as [$mid, $qtd, $preco]) {
                $this->inserir('purchase_order_items', [
                    'purchase_order_id' => $po, 'material_id' => $mid, 'quantity' => $qtd, 'unit_price' => $preco,
                    'quantity_received' => match ($status) {
                        'recebida' => $qtd, 'parcialmente_recebida' => (int) ($qtd / 2), default => 0
                    },
                ], $this->dia($criado));
            }
        }

        // Requisicoes de material
        $requisicoes = ['rascunho' => 2, 'aguardando_aprovacao' => 4, 'aprovado' => 3, 'cotado' => 2, 'a_caminho' => 2, 'entregue' => 4];
        $nReq = 0;
        foreach ($requisicoes as $status => $qtd) {
            for ($n = 0; $n < $qtd; $n++) {
                $m = $this->materiais[mt_rand(0, count($this->materiais) - 1)];
                $req = $this->inserir('material_requests', [
                    'user_id' => $this->admin, 'status' => $status, 'requested_at' => $this->dia(mt_rand(1, 25)), 'priority' => ['normal', 'normal', 'urgente', 'critico'][mt_rand(0, 3)],
                    'origin' => 'manual', 'notes' => 'Requisição fictícia da demonstração.',
                ], $this->dia(mt_rand(1, 25)));
                $this->inserir('material_request_items', ['material_request_id' => $req, 'material_id' => $m['id'], 'quantity' => mt_rand(2, 10), 'cost_price' => $m['custo']]);
                $nReq++;
            }
        }

        // Pecas aplicadas em atendimento (cruza com o que foi orcado ao cliente)
        $os = DB::table('maintenance_orders')->where('tenant_id', $this->tenantId)->orderByDesc('created_at')->limit(80)->pluck('id', 'created_at');
        $aplicadas = 0;
        foreach ($os->take(60) as $criado => $osId) {
            $m = $this->materiais[mt_rand(0, count($this->materiais) - 1)];
            $this->inserir('maintenance_order_materials', ['maintenance_order_id' => $osId, 'name' => $m['nome'], 'quantity' => mt_rand(1, 4), 'unit_price' => $m['custo'], 'material_id' => $m['id']], Carbon::parse($criado));
            $aplicadas++;
        }
        // Itens orcados com material (para "Aplicadas x Orcadas"): marca itens dos orcamentos da demo, se o comercial ja existir.
        $itensOrcados = DB::table('quote_items')->where('tenant_id', $this->tenantId)->whereNull('material_id')->orderBy('created_at')->limit(40)->pluck('id');
        foreach ($itensOrcados as $k => $itemId) {
            DB::table('quote_items')->where('id', $itemId)->update(['material_id' => $this->materiais[$k % count($this->materiais)]['id']]);
        }

        return ['materiais' => count($this->materiais), 'fornecedores' => count($this->fornecedores), 'movimentacoes' => $movs, 'pedidos_de_compra' => count($pedidos), 'requisicoes' => $nReq, 'pecas_aplicadas' => $aplicadas];
    }

    // ---------------------------------------------------------------- FINANCEIRO

    private function criarFinanceiro(): array
    {
        $cat = [];
        foreach (self::CATEGORIAS_FINANCEIRAS as $nome) {
            $cat[$nome] = $this->inserir('bill_categories', ['name' => $nome]);
        }
        $centro = $this->inserir('cost_centers', ['name' => 'Operação', 'code' => 'OP-01']);
        $this->inserir('cost_centers', ['name' => 'Administrativo', 'code' => 'ADM-01']);

        $clientes = DB::table('clients')->where('tenant_id', $this->tenantId)->pluck('name', 'id')->all();
        $ids = array_keys($clientes);
        $receber = $pagar = 0;

        // Contas a receber: 9 meses para tras ate o mes que vem. Passado = pago (alguns atrasados em aberto); mes atual = misto.
        for ($mesesAtras = 8; $mesesAtras >= -1; $mesesAtras--) {
            $base = $this->agora->copy()->startOfMonth()->subMonths($mesesAtras);
            $qtd = mt_rand(7, 10);
            for ($n = 0; $n < $qtd; $n++) {
                $venc = $base->copy()->addDays(mt_rand(0, 27));
                $cliente = $ids[mt_rand(0, count($ids) - 1)];
                $valor = mt_rand(18, 96) * 100 + [0, 50, 90][mt_rand(0, 2)];
                $pago = $venc->lt($this->agora->copy()->startOfDay()) && mt_rand(1, 100) > ($mesesAtras >= 1 ? 8 : 35);
                $tipoReceita = [['Locação de equipamentos', 'Locação'], ['Locação de equipamentos', 'Locação'], ['Locação de equipamentos', 'Locação'], ['Serviços de manutenção', 'Serviço de manutenção'], ['Frete e transporte', 'Frete'], ['Venda de equipamentos', 'Venda de equipamento']][mt_rand(0, 5)];
                $this->inserir('account_receivables', [
                    'client_id' => $cliente, 'bill_category_id' => $cat[$tipoReceita[0]], 'cost_center_id' => $centro,
                    'description' => $tipoReceita[1].' — '.$clientes[$cliente].' ('.$base->format('m/Y').')', 'amount' => $valor, 'due_date' => $venc->toDateString(),
                    'payment_date' => $pago ? $venc->copy()->addDays(mt_rand(-2, 6))->min($this->agora)->toDateString() : null, 'status' => $pago ? 'pago' : 'pendente',
                    'mes' => $base->format('m'), 'ano' => $base->format('Y'),
                ], $base->copy()->subDays(5));
                $receber++;
            }
        }

        // Contas a pagar
        $fixos = [['Folha e encargos', 'Folha de pagamento', 38000], ['Estrutura e aluguel', 'Aluguel do pátio', 9800], ['Seguros', 'Seguro da frota', 4600], ['Impostos', 'Impostos do mês', 12400]];
        $variaveis = [['Manutenção e peças', 'Compra de peças — fornecedor', 3400], ['Combustível', 'Abastecimento da frota', 5200], ['Marketing', 'Campanha de divulgação', 1800], ['Manutenção e peças', 'Serviço terceirizado de solda', 2300]];
        for ($mesesAtras = 8; $mesesAtras >= -1; $mesesAtras--) {
            $base = $this->agora->copy()->startOfMonth()->subMonths($mesesAtras);
            $linhas = $fixos;
            for ($n = 0, $q = mt_rand(3, 5); $n < $q; $n++) {
                $linhas[] = $variaveis[mt_rand(0, count($variaveis) - 1)];
            }
            foreach ($linhas as $idx => [$categoria, $descricao, $valor]) {
                $venc = $base->copy()->addDays($idx < 4 ? [4, 9, 14, 19][$idx] : mt_rand(2, 27));
                $pago = $venc->lt($this->agora->copy()->startOfDay()) && mt_rand(1, 100) > ($mesesAtras >= 1 ? 5 : 25);
                $this->inserir('account_payables', [
                    'bill_category_id' => $cat[$categoria], 'cost_center_id' => $centro, 'description' => $descricao.' ('.$base->format('m/Y').')',
                    'amount' => round($valor * (mt_rand(88, 115) / 100), 2), 'due_date' => $venc->toDateString(),
                    'payment_date' => $pago ? $venc->copy()->addDays(mt_rand(-1, 3))->min($this->agora)->toDateString() : null, 'status' => $pago ? 'pago' : 'pendente',
                    'mes' => $base->format('m'), 'ano' => $base->format('Y'),
                ], $base->copy()->subDays(3));
                $pagar++;
            }
        }

        // Recebimentos ja confirmados neste mes (o "Recebido no mes" e o saldo nao ficam zerados logo apos a virada do mes).
        foreach ([[1, 28400, 'Locação'], [2, 19600, 'Locação'], [3, 14800, 'Serviço de manutenção']] as [$d, $valor, $rotulo]) {
            $cliente = $ids[mt_rand(0, count($ids) - 1)];
            $venc = $this->agora->copy()->startOfMonth()->addDays($d - 1);
            if ($venc->gt($this->agora)) {
                $venc = $this->agora->copy()->startOfDay();
            }
            $this->inserir('account_receivables', ['client_id' => $cliente, 'bill_category_id' => $cat[$rotulo === 'Locação' ? 'Locação de equipamentos' : 'Serviços de manutenção'], 'cost_center_id' => $centro,
                'description' => $rotulo.' — '.$clientes[$cliente].' ('.$this->agora->format('m/Y').')', 'amount' => $valor, 'due_date' => $venc->toDateString(), 'payment_date' => $venc->toDateString(), 'status' => 'pago',
                'mes' => $this->agora->format('m'), 'ano' => $this->agora->format('Y')]);
            $receber++;
        }

        // Garante compromissos nos proximos dias (agenda da semana) e atrasos visiveis.
        foreach ([[0, 2400, 'Compra de peças — Hidrotec'], [2, 5800, 'Parcela do seguro da frota'], [5, 3100, 'Abastecimento da frota']] as [$d, $valor, $desc]) {
            $this->inserir('account_payables', ['bill_category_id' => $cat['Manutenção e peças'], 'cost_center_id' => $centro, 'description' => $desc, 'amount' => $valor,
                'due_date' => $this->agora->copy()->addDays($d)->toDateString(), 'status' => 'pendente', 'mes' => $this->agora->format('m'), 'ano' => $this->agora->format('Y')]);
            $pagar++;
        }
        foreach ([[1, 7200], [3, 4300], [6, 9100]] as [$d, $valor]) {
            $cliente = $ids[mt_rand(0, count($ids) - 1)];
            $this->inserir('account_receivables', ['client_id' => $cliente, 'bill_category_id' => $cat['Locação de equipamentos'], 'cost_center_id' => $centro,
                'description' => 'Locação — '.$clientes[$cliente], 'amount' => $valor, 'due_date' => $this->agora->copy()->addDays($d)->toDateString(), 'status' => 'pendente',
                'mes' => $this->agora->format('m'), 'ano' => $this->agora->format('Y')]);
            $receber++;
        }

        return ['a_receber' => $receber, 'a_pagar' => $pagar, 'categorias' => count($cat)];
    }

    // ---------------------------------------------------------------- FROTA

    private function criarFrota(): array
    {
        $abast = 0;
        $postos = ['Posto Boa Vista', 'Posto Rodovia', 'Auto Posto Campinas', 'Posto Distrito'];
        foreach ($this->veiculos as $i => $v) {
            $odometro = mt_rand(60000, 180000);
            $pesado = $i < 3;
            $combustivel = $pesado ? 'diesel_s10' : ($i === 4 ? 'etanol' : ($i === 3 ? 'diesel_s10' : 'gasolina'));
            $consumoBase = $pesado ? 3.4 + $i * 0.5 : 8.5 + $i;
            $n = $pesado ? 12 : 9;
            for ($k = $n; $k >= 0; $k--) {
                $km = mt_rand(300, 900);
                $odometro += $km;
                $consumo = round($consumoBase * (mt_rand(88, 112) / 100), 2);
                $litros = round($km / $consumo, 1);
                $precoLitro = round(match ($combustivel) {
                    'diesel_s10' => 6.15, 'etanol' => 4.2, default => 6.4
                } * (mt_rand(97, 104) / 100), 3);
                $quando = $this->agora->copy()->subDays((int) ($k * (140 / $n)) + mt_rand(0, 3))->setTime(mt_rand(6, 18), mt_rand(0, 59));
                $this->inserir('frota_abastecimentos', [
                    'ativo_id' => $v['id'], 'abastecido_em' => $quando, 'odometro' => $odometro, 'combustivel' => $combustivel, 'litros' => $litros, 'valor_litro' => $precoLitro,
                    'valor_total' => round($litros * $precoLitro, 2), 'tanque_cheio' => true, 'origem' => 'posto', 'posto' => $postos[mt_rand(0, 3)], 'km_rodado' => $km, 'consumo_km_l' => $consumo,
                    'registrado_por' => $this->admin,
                ], $quando);
                $abast++;
            }
            DB::table('assets')->where('id', $v['id'])->update(['odometro_atual' => $odometro]);
        }

        // Multas
        $multas = [
            [0, 'aberta', 5, 'media', 'Excesso de velocidade até 20%', 130.16, 18], [1, 'aberta', 3, 'grave', 'Estacionar em local proibido', 195.23, 40],
            [3, 'aberta', 6, 'leve', 'Avanço de sinal vermelho', 293.47, 25], [2, 'condutor_indicado', 12, 'media', 'Excesso de velocidade até 20%', 130.16, 30],
            [4, 'paga', 45, 'leve', 'Transitar em faixa exclusiva', 88.38, 70], [5, 'paga', 60, 'media', 'Uso de celular ao volante', 293.47, 90],
        ];
        foreach ($multas as $i => [$vi, $situacao, $venc, $grav, $desc, $valor, $diasInfr]) {
            $infr = $this->dia($diasInfr, 14);
            $this->inserir('frota_multas', [
                'ativo_id' => $this->veiculos[$vi]['id'], 'numero_auto' => 'AIT'.mt_rand(100000, 999999), 'infracao_em' => $infr, 'local' => 'Rod. Anhanguera, km '.mt_rand(80, 140), 'descricao' => $desc,
                'gravidade' => $grav, 'pontos' => FrotaMulta::PONTOS[$grav] ?? 4, 'valor' => $valor,
                'vencimento' => $situacao === 'paga' ? $infr->copy()->addDays(30)->toDateString() : $this->agora->copy()->addDays($venc > 20 ? $venc - 14 : $venc - 4)->toDateString(),
                'situacao' => $situacao, 'pago_em' => $situacao === 'paga' ? $infr->copy()->addDays(20)->toDateString() : null, 'quem_paga' => 'empresa', 'registrado_por' => $this->admin,
            ], $infr);
        }

        // Sinistros
        $sin = [[0, 'colisao', 'aberto', true, 9, 'Colisão traseira em manobra no pátio do cliente'], [1, 'avaria', 'em_reparo', true, 21, 'Avaria no sistema de freios durante transporte'], [3, 'colisao', 'encerrado', false, 75, 'Arranhões laterais em estacionamento']];
        foreach ($sin as [$vi, $tipo, $situacao, $parado, $dias, $desc]) {
            $quando = $this->dia($dias, 11);
            $this->inserir('frota_sinistros', [
                'ativo_id' => $this->veiculos[$vi]['id'], 'tipo' => $tipo, 'ocorrido_em' => $quando, 'local' => 'Campinas/SP', 'descricao' => $desc, 'culpa' => 'indefinida', 'houve_vitima' => false,
                'seguradora' => 'Seguradora Demo', 'valor_orcamento' => mt_rand(40, 190) * 100, 'valor_franquia' => 2500, 'veiculo_parado' => $parado, 'parado_desde' => $parado ? $quando : null,
                'situacao' => $situacao, 'encerrado_em' => $situacao === 'encerrado' ? $quando->copy()->addDays(12) : null, 'registrado_por' => $this->admin,
            ], $quando);
        }

        // Trocas de oleo: cada veiculo com a proxima troca em datas diferentes (vencida, esta semana, proximo mes, longe)
        $proximas = [-6, 4, 12, 27, 70, 110];
        foreach ($this->veiculos as $i => $v) {
            $odo = (int) DB::table('assets')->where('id', $v['id'])->value('odometro_atual');
            $feita = $this->agora->copy()->addDays($proximas[$i])->subMonths(6);
            $this->inserir('frota_trocas_oleo', [
                'ativo_id' => $v['id'], 'tipo' => 'troca', 'odometro' => $odo - mt_rand(2000, 9000), 'litros' => $i < 3 ? 22 : 6, 'produto' => $i < 3 ? 'Óleo 15W40 Diesel' : 'Óleo 5W30 Sintético',
                'custo' => $i < 3 ? 780 : 320, 'realizado_por' => $this->admin, 'realizado_em' => $feita, 'proxima_troca_odometro' => $odo + 3000,
                'proxima_troca_data' => $this->agora->copy()->addDays($proximas[$i])->toDateString(),
            ], $feita);
        }

        // Entradas e saidas de veiculos: 3 fora do patio (uma ha mais de 24h) e 6 ja devolvidos
        $saidas = [[2, 'Obra Construtora Horizonte', 'visita_tecnica', 30], [3, 'Cliente Metalúrgica Vale Forte', 'cliente', 7], [5, 'Fornecedor Hidrotec', 'administrativo', 3]];
        foreach ($saidas as [$vi, $destino, $fin, $horas]) {
            $this->inserir('frota_saidas_veiculo', [
                'ativo_id' => $this->veiculos[$vi]['id'], 'condutor_nome' => ['Marcos Pereira', 'João Batista', 'Rafael Nunes'][mt_rand(0, 2)], 'finalidade' => $fin, 'destino' => $destino,
                'motivo' => 'Saída registrada na demonstração', 'saida_em' => $this->agora->copy()->subHours($horas), 'odometro_saida' => (int) DB::table('assets')->where('id', $this->veiculos[$vi]['id'])->value('odometro_atual'),
                'registrado_por' => $this->admin,
            ], $this->agora->copy()->subHours($horas));
        }
        for ($n = 0; $n < 6; $n++) {
            $saida = $this->dia(mt_rand(3, 40), 8);
            $this->inserir('frota_saidas_veiculo', [
                'ativo_id' => $this->veiculos[mt_rand(0, 5)]['id'], 'condutor_nome' => 'Motorista Demo', 'finalidade' => 'cliente', 'destino' => 'Visita a cliente', 'motivo' => 'Visita de rotina',
                'saida_em' => $saida, 'odometro_saida' => mt_rand(50000, 200000), 'retorno_em' => $saida->copy()->addHours(mt_rand(3, 9)), 'odometro_retorno' => mt_rand(50000, 200000), 'registrado_por' => $this->admin,
            ], $saida);
        }

        return ['veiculos' => count($this->veiculos), 'abastecimentos' => $abast, 'multas' => count($multas), 'sinistros' => count($sin), 'trocas_de_oleo' => count($this->veiculos), 'saidas_de_veiculo' => count($saidas) + 6];
    }

    // ---------------------------------------------------------------- LOGISTICA

    private function criarLogistica(): array
    {
        $transportadoras = [];
        foreach ([['Transportes Via Rápida', '41.222.333/0001-55'], ['Cargas Pesadas Brasil', '52.333.444/0001-66']] as [$nome, $doc]) {
            $transportadoras[] = $this->inserir('freight_carriers', ['nome' => $nome, 'documento' => $doc, 'contato_nome' => 'Central de despacho', 'contato_telefone' => '(19) 3300-4500']);
        }
        $frota = [];
        foreach ([['PRA1A11', 'Prancha 3 eixos', 'prancha'], ['MUN2B22', 'Munck 12t', 'munck']] as [$placa, $modelo, $tipo]) {
            $frota[] = $this->inserir('fleet_vehicles', ['placa' => $placa, 'modelo' => $modelo, 'tipo' => $tipo, 'status' => 'ativo', 'km_atual' => mt_rand(40000, 120000), 'custo_por_km' => 6.8]);
        }

        $cidades = ['Campinas', 'Sorocaba', 'Jundiaí', 'Paulínia', 'Americana', 'Indaiatuba', 'Piracicaba', 'São Paulo'];
        $n = 0;
        $fretes = 0;
        // 6 meses de movimentacoes concluidas
        for ($mesesAtras = 5; $mesesAtras >= 0; $mesesAtras--) {
            $qtd = mt_rand(5, 8);
            for ($k = 0; $k < $qtd; $k++) {
                $dia = min(27, mt_rand(1, 27));
                $quando = $this->agora->copy()->startOfMonth()->subMonths($mesesAtras)->addDays($dia - 1)->setTime(mt_rand(7, 16), 0);
                if ($quando->gt($this->agora)) {
                    $quando = $this->agora->copy()->subDays(mt_rand(1, 6));
                }
                $tipo = $k % 2 === 0 ? 'mobilizacao' : 'desmobilizacao';
                $mov = $this->inserir('equipment_movements', [
                    'asset_id' => $this->equipamentos[mt_rand(0, count($this->equipamentos) - 1)], 'type' => $tipo, 'status' => 'concluido', 'scheduled_at' => $quando,
                    'started_at' => $quando, 'completed_at' => $quando->copy()->addHours(mt_rand(2, 6)), 'custo_transporte' => 0,
                    'notes' => $tipo === 'mobilizacao' ? 'Pátio → '.$cidades[mt_rand(0, 7)] : $cidades[mt_rand(0, 7)].' → Pátio',
                ], $quando);
                $proprio = mt_rand(1, 100) <= 45;
                $this->inserir('freight_records', [
                    'equipment_movement_id' => $mov, 'tipo' => $proprio ? 'proprio' : 'terceirizado', 'fleet_vehicle_id' => $proprio ? $frota[mt_rand(0, 1)] : null,
                    'freight_carrier_id' => $proprio ? null : $transportadoras[mt_rand(0, 1)], 'valor' => $proprio ? mt_rand(380, 920) : mt_rand(900, 2600), 'origem' => 'Pátio', 'destino' => $cidades[mt_rand(0, 7)],
                    'km_percorrido' => mt_rand(25, 160), 'data' => $quando->toDateString(),
                ], $quando);
                $n++;
                $fretes++;
            }
        }
        // Em curso / pendentes (alimenta programacao e indicadores)
        $pendentes = [
            ['em_andamento', 'mobilizacao', 0, 'Pátio → Obra Construtora Horizonte'], ['em_andamento', 'desmobilizacao', 0, 'Metalúrgica Vale Forte → Pátio'],
            ['aguardando_aprovacao', 'mobilizacao', 1, 'Pátio → Indústria Têxtil Brasa'], ['aguardando_aprovacao', 'desmobilizacao', 2, 'Condomínio Parque das Águas → Pátio'],
            ['aguardando_vistoria', 'mobilizacao', 3, 'Pátio → Agroindustrial Campo Bom'], ['aguardando_vistoria', 'desmobilizacao', 5, 'Logística Rota Sul → Pátio'],
        ];
        foreach ($pendentes as [$status, $tipo, $dias, $nota]) {
            $this->inserir('equipment_movements', [
                'asset_id' => $this->equipamentos[mt_rand(0, count($this->equipamentos) - 1)], 'type' => $tipo, 'status' => $status,
                'scheduled_at' => $this->agora->copy()->addDays($dias)->setTime(mt_rand(7, 15), 0), 'started_at' => $status === 'em_andamento' ? $this->agora->copy()->subHours(2) : null, 'notes' => $nota,
            ]);
            $n++;
        }

        // Retiradas solicitadas pelos clientes
        $retiradas = [['solicitado', 6], ['solicitado', 4], ['solicitado', 1], ['solicitado', 0], ['agendado', 2]];
        foreach ($retiradas as [$status, $dias]) {
            $this->inserir('equipment_pickup_requests', [
                'client_id' => $this->clientes[mt_rand(0, count($this->clientes) - 1)], 'asset_id' => $this->equipamentos[mt_rand(0, count($this->equipamentos) - 1)], 'status' => $status,
                'notes' => 'Obra concluída, solicitar retirada do equipamento.', 'requested_at' => $this->agora->copy()->subDays($dias),
            ], $this->agora->copy()->subDays($dias));
        }

        return ['movimentacoes' => $n, 'fretes' => $fretes, 'retiradas' => count($retiradas), 'transportadoras' => count($transportadoras)];
    }
}
