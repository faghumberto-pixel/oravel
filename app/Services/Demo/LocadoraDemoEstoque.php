<?php

namespace App\Services\Demo;

use App\Models\Material;
use App\Models\StockCount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Estoque\ContagemFisica;
use App\Services\Estoque\EstoqueGrupos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Povoa o ESTOQUE ESTRUTURADO da Locadora Demo (so' ela): grupos, material de escritorio, pecas e oleo, itens agregados,
 * enderecos (corredor > prateleira > nivel), onde cada item esta e duas contagens fisicas (uma finalizada, outra em andamento)
 * com historico de quem contou e quando. Roda DEPOIS de LocadoraDemoOperacional (que cria os materiais da oficina). Idempotente nos cadastros e nas contagens; o saldo dos materiais, porem, recebe os ajustes da contagem finalizada a cada rodada: para voltar ao zero use demo:alec-criar.
 */
class LocadoraDemoEstoque
{
    private const SEMENTE = 20261113;

    private const PREFIXO_ADM = 'ADM-';

    private string $tenantId;

    private Carbon $agora;

    private string $admin;

    /** @var array<string, string> nome do grupo => id */
    private array $grupos = [];

    /** @var list<array{tipo: string, id: string, grupo: string, qtd: float}> */
    private array $itens = [];

    private const ADMINISTRACAO = [
        ['Papel sulfite A4 (resma)', 24.9, 20, 120, 'un'], ['Toner para impressora', 289.0, 2, 8, 'un'], ['Caneta esferográfica (caixa 50)', 38.0, 3, 12, 'cx'],
        ['Grampeador de mesa', 32.0, 2, 6, 'un'], ['Café em pó (kg)', 31.0, 5, 20, 'kg'], ['Copo descartável 200 ml (cento)', 9.5, 6, 30, 'un'],
        ['Papel higiênico (fardo)', 46.0, 3, 15, 'un'], ['Detergente (galão 5 L)', 24.0, 3, 12, 'un'], ['Água sanitária (galão 5 L)', 17.0, 3, 12, 'un'], ['Saco de lixo 100 L (pacote)', 21.0, 4, 20, 'un'],
    ];

    /** [nome, categoria, grupo, custo, minimo, unidade, saldo no deposito] */
    private const PECAS = [
        ['Óleo de motor 15W40 (litro)', 'Óleos e lubrificantes', 'Veículos', 26.0, 60, 'L', 140], ['Óleo de motor 5W30 sintético (litro)', 'Óleos e lubrificantes', 'Veículos', 42.0, 20, 'L', 12],
        ['Óleo hidráulico ISO 68 (litro)', 'Óleos e lubrificantes', 'Manutenção', 21.0, 80, 'L', 230], ['Graxa de lítio (kg)', 'Óleos e lubrificantes', 'Manutenção', 33.0, 10, 'kg', 26],
        ['Arla 32 (litro)', 'Óleos e lubrificantes', 'Veículos', 6.5, 100, 'L', 340], ['Filtro de óleo — caminhão', 'Filtros', 'Veículos', 64.0, 6, 'un', 3],
        ['Filtro de combustível — caminhão', 'Filtros', 'Veículos', 88.0, 6, 'un', 14],
    ];

    /** [nome, categoria, grupo, valor de compra, quantidade de unidades, prefixo do codigo] */
    private const AGREGADOS = [
        ['Cinta de içamento 3 t', 'acessorio', 'Equipamentos', 180.0, 8, 'CI'], ['Corrente de içamento 5 m', 'acessorio', 'Equipamentos', 420.0, 5, 'CO'],
        ['Extintor de incêndio ABC 6 kg', 'acessorio', 'Logística', 235.0, 7, 'EX'], ['Cone de sinalização', 'acessorio', 'Logística', 38.0, 12, 'CN'],
        ['Capacete de segurança', 'insumo', 'Manutenção', 29.0, 10, 'CP'],
    ];

    public function popular(Tenant $tenant): array
    {
        if (! $tenant->is_demo) {
            throw new \RuntimeException('O estoque estruturado só é populado na empresa de demonstração.');
        }

        $this->tenantId = (string) $tenant->id;
        $this->agora = Carbon::now();
        $this->itens = [];
        mt_srand(self::SEMENTE);

        try {
            return DB::transaction(function () {
                $this->limpar();
                $this->admin = (string) User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->orderBy('created_at')->value('id');
                $this->grupos = app(EstoqueGrupos::class)->garantirPadrao($this->tenantId)->pluck('id', 'name')->map(fn ($v) => (string) $v)->all();

                $materiais = $this->gruposDosMateriais();
                $adm = $this->materiaisDeEscritorio();
                $pecas = $this->pecasEOleo();
                $agregados = $this->itensAgregados();
                $enderecos = $this->enderecosEAlocacao();
                $contagens = $this->contagens();

                return ['grupos' => count($this->grupos), 'materiais_com_grupo' => $materiais, 'materiais_de_escritorio' => $adm, 'pecas_e_oleo' => $pecas, 'itens_agregados' => $agregados] + $enderecos + $contagens;
            });
        } finally {
            mt_srand();
        }
    }

    private function limpar(): void
    {
        $contagens = DB::table('stock_counts')->where('tenant_id', $this->tenantId)->pluck('id');
        $itens = DB::table('stock_count_items')->whereIn('stock_count_id', $contagens)->pluck('id');
        DB::table('stock_count_entries')->whereIn('stock_count_item_id', $itens)->delete();
        DB::table('stock_count_items')->whereIn('id', $itens)->delete();
        DB::table('stock_counts')->whereIn('id', $contagens)->delete();
        DB::table('stock_placements')->where('tenant_id', $this->tenantId)->delete();
        DB::table('stock_locations')->where('tenant_id', $this->tenantId)->delete();

        DB::table('frota_trocas_oleo')->where('tenant_id', $this->tenantId)->update(['peca_id' => null]);
        DB::table('stock_movements')->where('tenant_id', $this->tenantId)->delete();
        $pecas = DB::table('parts')->where('tenant_id', $this->tenantId)->pluck('id');
        DB::table('warehouse_stocks')->whereIn('part_id', $pecas)->delete();
        DB::table('parts')->where('tenant_id', $this->tenantId)->delete();
        DB::table('part_categories')->where('tenant_id', $this->tenantId)->delete();
        DB::table('warehouses')->where('tenant_id', $this->tenantId)->delete();

        DB::table('aggregate_item_exits')->where('tenant_id', $this->tenantId)->delete();
        DB::table('aggregate_item_entries')->where('tenant_id', $this->tenantId)->delete();
        DB::table('aggregate_items')->where('tenant_id', $this->tenantId)->delete();
        DB::table('aggregate_item_types')->where('tenant_id', $this->tenantId)->delete();

        $adm = DB::table('materials')->where('tenant_id', $this->tenantId)->where('sku', 'like', self::PREFIXO_ADM.'%')->pluck('id');
        DB::table('material_stock_movements')->whereIn('material_id', $adm)->delete();
        DB::table('material_location_stock')->whereIn('material_id', $adm)->delete();
        DB::table('materials')->whereIn('id', $adm)->delete();
        DB::table('material_categories')->where('tenant_id', $this->tenantId)->where('name', 'Escritório e limpeza')->delete();
        DB::table('materials')->where('tenant_id', $this->tenantId)->update(['stock_group_id' => null]);
        DB::table('stock_groups')->where('tenant_id', $this->tenantId)->delete();
    }

    private function agoraMais(int $diasAtras, int $hora = 10): Carbon
    {
        return $this->agora->copy()->subDays($diasAtras)->setTime($hora, mt_rand(0, 59));
    }

    /** Materiais da oficina (criados pelo operacional) ganham grupo: manutencao, veiculos ou equipamentos. */
    private function gruposDosMateriais(): int
    {
        $n = 0;
        foreach (Material::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->orderBy('sku')->get(['id', 'name', 'material_category_id']) as $m) {
            $nome = mb_strtolower($m->name);
            $grupo = match (true) {
                str_contains($nome, 'caminhão') || str_contains($nome, '22.5') || str_contains($nome, 'roda de aço') || str_contains($nome, 'parafuso de roda') => 'Veículos',
                str_contains($nome, 'rodízio') || str_contains($nome, 'maciço') || str_contains($nome, 'bateria tracionária') => 'Equipamentos',
                default => 'Manutenção',
            };
            DB::table('materials')->where('id', $m->id)->update(['stock_group_id' => $this->grupos[$grupo]]);
            $this->itens[] = ['tipo' => 'material', 'id' => (string) $m->id, 'grupo' => $grupo, 'qtd' => (float) DB::table('materials')->where('id', $m->id)->value('current_stock')];
            $n++;
        }

        return $n;
    }

    private function materiaisDeEscritorio(): int
    {
        $categoria = (string) Str::uuid();
        DB::table('material_categories')->insert(['id' => $categoria, 'tenant_id' => $this->tenantId, 'name' => 'Escritório e limpeza', 'created_at' => $this->agora, 'updated_at' => $this->agora]);
        $abaixo = [1, 4, 8];

        foreach (self::ADMINISTRACAO as $i => [$nome, $custo, $min, $max, $un]) {
            $saldo = in_array($i, $abaixo, true) ? max(0, $min - mt_rand(1, 2)) : mt_rand($min + 2, $max);
            $id = (string) Str::uuid();
            DB::table('materials')->insert([
                'id' => $id, 'tenant_id' => $this->tenantId, 'sku' => self::PREFIXO_ADM.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'name' => $nome, 'unit_cost' => $custo, 'price' => $custo,
                'min_stock' => $min, 'max_stock' => $max, 'current_stock' => $saldo, 'material_category_id' => $categoria, 'stock_group_id' => $this->grupos['Administração e escritório'],
                'unit_of_measure' => $un, 'warehouse_location' => 'Escritório', 'created_at' => $this->agora, 'updated_at' => $this->agora,
            ]);
            // historico: compra inicial e consumo ao longo dos meses
            $entrada = $saldo + mt_rand(10, 40);
            DB::table('material_stock_movements')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'material_id' => $id, 'type' => 'entrada', 'quantity' => $entrada, 'balance_after' => $entrada, 'created_by_user_id' => $this->admin, 'reason' => 'Compra para o escritório', 'document_reference' => 'NF-'.mt_rand(1000, 9999), 'created_at' => $this->agoraMais(mt_rand(60, 150)), 'updated_at' => $this->agora]);
            DB::table('material_stock_movements')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'material_id' => $id, 'type' => 'saida', 'quantity' => $entrada - $saldo, 'balance_after' => $saldo, 'created_by_user_id' => $this->admin, 'reason' => 'Consumo do escritório', 'created_at' => $this->agoraMais(mt_rand(1, 30)), 'updated_at' => $this->agora]);
            $this->itens[] = ['tipo' => 'material', 'id' => $id, 'grupo' => 'Administração e escritório', 'qtd' => (float) $saldo];
        }

        return count(self::ADMINISTRACAO);
    }

    private function pecasEOleo(): int
    {
        $deposito = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenantId, 'name' => 'Depósito Central', 'code' => 'DC', 'type' => 'central', 'is_active' => true, 'city' => 'Campinas', 'state' => 'SP', 'created_at' => $this->agora, 'updated_at' => $this->agora]);
        $categorias = [];
        foreach (array_unique(array_column(self::PECAS, 1)) as $nome) {
            $categorias[$nome] = DB::table('part_categories')->insertGetId(['tenant_id' => $this->tenantId, 'name' => $nome, 'slug' => Str::slug($nome).'-demo', 'created_at' => $this->agora, 'updated_at' => $this->agora]);
        }

        $oleoMotor = null;
        foreach (self::PECAS as $i => [$nome, $cat, $grupo, $custo, $min, $un, $saldo]) {
            $id = DB::table('parts')->insertGetId([
                'tenant_id' => $this->tenantId, 'part_category_id' => $categorias[$cat], 'sku' => 'PC-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'name' => $nome, 'unit_of_measure' => $un,
                'cost_price' => $custo, 'minimum_stock' => $min, 'maximum_stock' => $min * 4, 'is_active' => true, 'stock_group_id' => $this->grupos[$grupo], 'created_at' => $this->agora, 'updated_at' => $this->agora,
            ]);
            DB::table('warehouse_stocks')->insert(['warehouse_id' => $deposito, 'part_id' => $id, 'current_quantity' => $saldo, 'reserved_quantity' => 0, 'created_at' => $this->agora, 'updated_at' => $this->agora]);
            $oleoMotor ??= $id;

            // Movimentacao: compras (entradas) e usos (saidas para frota e OS), do mais antigo ao mais recente, fechando no saldo atual.
            $movs = [['entry_purchase', mt_rand(80, 200)], ['exit_fleet', mt_rand(20, 60)], ['exit_work_order', mt_rand(10, 40)], ['entry_purchase', mt_rand(40, 120)], ['exit_fleet', mt_rand(15, 50)], ['exit_work_order', mt_rand(5, 30)]];
            $liquido = array_sum(array_map(fn ($m) => str_starts_with($m[0], 'entry_') ? $m[1] : -$m[1], $movs));
            $saldoIni = max(0, $saldo - $liquido);
            $corrente = $saldoIni;
            foreach ($movs as $k => [$tipo, $q]) {
                $antes = $corrente;
                $corrente = max(0, $corrente + (str_starts_with($tipo, 'entry_') ? $q : -$q));
                DB::table('stock_movements')->insert([
                    'tenant_id' => $this->tenantId, 'part_id' => $id, 'warehouse_id' => $deposito, 'movement_type' => $tipo, 'quantity' => $q, 'balance_before' => $antes, 'balance_after' => $corrente,
                    'unit_cost' => $custo, 'total_cost' => round($q * $custo, 2), 'reference_document' => str_starts_with($tipo, 'entry_') ? 'NF-'.mt_rand(1000, 9999) : null,
                    'notes' => $tipo === 'exit_fleet' ? 'Saída para a frota' : ($tipo === 'exit_work_order' ? 'Aplicado em ordem de serviço' : 'Compra de reposição'), 'created_by' => $this->admin, 'created_at' => $this->agoraMais(150 - $k * 24),
                ]);
            }
            $this->itens[] = ['tipo' => 'part', 'id' => (string) $id, 'grupo' => $grupo, 'qtd' => (float) $saldo];
        }

        // Troca de oleo da frota passa a apontar para o oleo do estoque: o uso aparece no rastreio do item.
        DB::table('frota_trocas_oleo')->where('tenant_id', $this->tenantId)->update(['peca_id' => $oleoMotor]);

        return count(self::PECAS);
    }

    private function itensAgregados(): int
    {
        $assets = DB::table('assets')->where('tenant_id', $this->tenantId)->where('tag', 'not like', 'DEMO-VEI-%')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $n = 0;
        foreach (self::AGREGADOS as [$nome, $categoria, $grupo, $valor, $qtd, $prefixo]) {
            $tipo = (string) Str::uuid();
            DB::table('aggregate_item_types')->insert(['id' => $tipo, 'tenant_id' => $this->tenantId, 'name' => $nome, 'category' => $categoria, 'unit_of_measure' => 'un', 'inspection_interval_days' => 180, 'stock_group_id' => $this->grupos[$grupo], 'created_at' => $this->agora, 'updated_at' => $this->agora]);
            DB::table('aggregate_item_entries')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'aggregate_item_type_id' => $tipo, 'entry_date' => $this->agora->copy()->subDays(mt_rand(120, 300))->toDateString(), 'unit_price' => $valor, 'quantity' => $qtd, 'total' => $valor * $qtd, 'invoice_number' => (string) mt_rand(10000, 99999), 'created_at' => $this->agora, 'updated_at' => $this->agora]);

            for ($k = 1; $k <= $qtd; $k++) {
                $unidade = (string) Str::uuid();
                $foraEmUso = $k % 4 === 0;
                DB::table('aggregate_items')->insert([
                    'id' => $unidade, 'tenant_id' => $this->tenantId, 'aggregate_item_type_id' => $tipo, 'code' => $prefixo.'-'.str_pad((string) $k, 3, '0', STR_PAD_LEFT), 'status' => $foraEmUso ? 'locado' : 'disponivel', 'condition' => 'ok',
                    'serial_number' => 'S'.mt_rand(100000, 999999), 'purchase_date' => $this->agora->copy()->subDays(mt_rand(120, 300))->toDateString(), 'purchase_value' => $valor, 'next_inspection_date' => $this->agora->copy()->addDays(mt_rand(10, 170))->toDateString(),
                    'created_at' => $this->agora, 'updated_at' => $this->agora,
                ]);
                if ($foraEmUso && $assets) {
                    DB::table('aggregate_item_exits')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'aggregate_item_type_id' => $tipo, 'aggregate_item_id' => $unidade, 'asset_id' => $assets[array_rand($assets)], 'exit_date' => $this->agora->copy()->subDays(mt_rand(3, 40))->toDateString(), 'quantity' => 1, 'reason' => 'locacao', 'returned' => false, 'unit_cost' => $valor, 'created_at' => $this->agora, 'updated_at' => $this->agora]);
                } elseif ($k % 3 === 0 && $assets) {
                    $saida = $this->agora->copy()->subDays(mt_rand(45, 120));
                    DB::table('aggregate_item_exits')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'aggregate_item_type_id' => $tipo, 'aggregate_item_id' => $unidade, 'asset_id' => $assets[array_rand($assets)], 'exit_date' => $saida->toDateString(), 'quantity' => 1, 'reason' => 'locacao', 'returned' => true, 'returned_at' => $saida->copy()->addDays(mt_rand(5, 30))->toDateString(), 'returned_condition' => 'ok', 'unit_cost' => $valor, 'created_at' => $this->agora, 'updated_at' => $this->agora]);
                }
            }
            $this->itens[] = ['tipo' => 'aggregate', 'id' => $tipo, 'grupo' => $grupo, 'qtd' => (float) $qtd];
            $n++;
        }

        return $n;
    }

    /** Enderecos: corredores por assunto (A manutencao, B veiculos, C equipamentos/logistica na matriz; D administracao no deposito). */
    private function enderecosEAlocacao(): array
    {
        $unidades = DB::table('internal_units')->where('tenant_id', $this->tenantId)->orderBy('created_at')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $matriz = $unidades[0];
        $deposito = $unidades[1] ?? $unidades[0];

        $enderecos = ['A' => [], 'B' => [], 'C' => [], 'D' => []];
        $criar = function (string $unidade, string $corredor, int $prateleiras, int $niveis) use (&$enderecos) {
            for ($p = 1; $p <= $prateleiras; $p++) {
                for ($n = 1; $n <= $niveis; $n++) {
                    $prat = str_pad((string) $p, 2, '0', STR_PAD_LEFT);
                    $id = (string) Str::uuid();
                    DB::table('stock_locations')->insert(['id' => $id, 'tenant_id' => $this->tenantId, 'internal_unit_id' => $unidade, 'aisle' => $corredor, 'shelf' => $prat, 'level' => (string) $n, 'code' => 'C-'.$corredor.' · P-'.$prat.' · N-'.$n, 'is_active' => true, 'created_at' => $this->agora, 'updated_at' => $this->agora]);
                    $enderecos[$corredor][] = $id;
                }
            }
        };
        $criar($matriz, 'A', 4, 3);
        $criar($matriz, 'B', 3, 3);
        $criar($matriz, 'C', 3, 2);
        $criar($deposito, 'D', 3, 3);

        $corredorDoGrupo = ['Manutenção' => 'A', 'Veículos' => 'B', 'Equipamentos' => 'C', 'Logística' => 'C', 'Administração e escritório' => 'D', 'Outros' => 'D'];
        $cursor = array_fill_keys(array_keys($enderecos), 0);
        $colocados = $semEndereco = 0;

        foreach ($this->itens as $i => $item) {
            // alguns itens ficam sem endereco de proposito (aparecem como pendencia na visao geral)
            if ($i % 11 === 10) {
                $semEndereco++;

                continue;
            }
            $corredor = $corredorDoGrupo[$item['grupo']] ?? 'D';
            $local = $enderecos[$corredor][$cursor[$corredor]++ % count($enderecos[$corredor])];
            $divide = $item['tipo'] !== 'aggregate' && $item['qtd'] >= 2 && $i % 4 === 0;
            $partes = $divide ? [round($item['qtd'] * 0.6, 3), round($item['qtd'] * 0.4, 3)] : [$item['qtd']];
            foreach ($partes as $k => $q) {
                $destino = $k === 0 ? $local : $enderecos[$corredor][$cursor[$corredor]++ % count($enderecos[$corredor])];
                DB::table('stock_placements')->insertOrIgnore(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenantId, 'stock_location_id' => $destino, 'item_type' => $item['tipo'], 'item_id' => $item['id'], 'quantity' => $q, 'created_at' => $this->agora, 'updated_at' => $this->agora]);
                $colocados++;
            }
        }

        return ['enderecos' => (int) DB::table('stock_locations')->where('tenant_id', $this->tenantId)->count(), 'itens_com_endereco' => $colocados, 'itens_sem_endereco' => $semEndereco];
    }

    /** Duas contagens: uma finalizada (ha ~3 semanas, corredor A, 3 pessoas) e uma em andamento (hoje e ontem). */
    private function contagens(): array
    {
        $servico = app(ContagemFisica::class);
        $matriz = (string) DB::table('internal_units')->where('tenant_id', $this->tenantId)->orderBy('created_at')->value('id');
        $contadores = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->orderBy('created_at')->limit(4)->get();
        $quemConta = fn (int $i) => $contadores[$i % max(1, $contadores->count())];

        // 1) Finalizada, corredor A, ha 21 dias
        $dia = $this->agora->copy()->subDays(21)->setTime(8, 30);
        $velha = $servico->criar($this->tenantId, $matriz, 'Inventário de outubro — corredor A', null, 'A', $this->admin);
        foreach ($velha->items()->orderBy('location_code')->orderBy('item_name')->get() as $k => $item) {
            $esperado = (float) $item->expected_quantity;
            $contado = $k % 5 === 2 ? max(0, $esperado - mt_rand(1, 3)) : ($k % 7 === 3 ? $esperado + mt_rand(1, 2) : $esperado);
            $servico->registrar($item, $contado, $quemConta($k), $k % 2 === 0 ? 'celular' : 'web', null, $dia->copy()->addMinutes($k * 4));
        }
        $velha->refresh();
        $ajustes = $servico->finalizar($velha, $this->admin);
        $fim = $dia->copy()->addHours(4);
        $velha->update(['finished_at' => $fim]);
        DB::table('stock_counts')->where('id', $velha->id)->update(['created_at' => $dia->copy()->subHours(2)]);
        DB::table('material_stock_movements')->where('reference_type', StockCount::class)->where('reference_id', $velha->id)->update(['created_at' => $fim]);
        DB::table('stock_movements')->where('reference_document', 'Contagem física: '.$velha->name)->update(['created_at' => $fim]);

        // 2) Em andamento, todos os corredores da matriz: ~65% contado (hoje e ontem), com divergencias
        $aberta = $servico->criar($this->tenantId, $matriz, 'Inventário de novembro — matriz', null, null, $this->admin);
        $itens = $aberta->items()->orderBy('location_code')->orderBy('item_name')->get();
        $meta = (int) round($itens->count() * 0.65);
        foreach ($itens->take($meta) as $k => $item) {
            $esperado = (float) $item->expected_quantity;
            $contado = $k % 6 === 1 ? max(0, $esperado - mt_rand(1, 4)) : ($k % 9 === 4 ? $esperado + 1 : $esperado);
            $quando = $k < $meta / 2 ? $this->agora->copy()->subDay()->setTime(14, 10)->addMinutes($k * 3) : $this->agora->copy()->subHours(mt_rand(1, 4))->subMinutes($k);
            $servico->registrar($item, $contado, $quemConta($k + 1), $k % 3 === 0 ? 'web' : 'celular', null, $quando);
        }

        return ['contagens' => 2, 'ajustes_da_contagem_finalizada' => $ajustes['ajustados'], 'itens_contados_em_andamento' => $meta, 'itens_na_folha_em_andamento' => $itens->count()];
    }
}
