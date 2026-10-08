<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDowntimeEvent;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaBateria;
use App\Models\FrotaChecklist;
use App\Models\FrotaCustoAvulso;
use App\Models\FrotaItemSeguranca;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaPlanoRevisao;
use App\Models\FrotaPneu;
use App\Models\MaintenanceOrder;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\BaixaVeiculoService;
use App\Services\Frota\BateriaService;
use App\Services\Frota\CatalogoEstoqueFrota;
use App\Services\Frota\ChaveService;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\KitSegurancaService;
use App\Services\Frota\LavagemService;
use App\Services\Frota\ModelosChecklistPadrao;
use App\Services\Frota\MultaService;
use App\Services\Frota\OleoService;
use App\Services\Frota\PedagioService;
use App\Services\Frota\PneuService;
use App\Services\Frota\RevisaoService;
use App\Services\Frota\SaidaVeiculoService;
use App\Services\Frota\SinistroService;
use App\Services\Frota\VinculoMotoristaService;
use App\Support\SaaSRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dados de demonstração para o cliente "Oravel": a Gestão de Frota inteira (veículos, pneus, baterias, óleo, revisões,
 * kit, entrada e saída, multas, sinistro, abastecimento, custos...) e propostas comerciais em todas as etapas do Kanban.
 *
 * Passa pelas MESMAS regras do sistema (os serviços reais) e marca tudo como demonstração ("DEMO-" na etiqueta,
 * "Demo — " no nome), então `--remover` apaga só isso. Só mexe no cliente Oravel; liga no contrato dele os módulos
 * que a demonstração usa. Não manda e-mail nem toca em outros clientes.
 */
class DemoPopularOravel extends Command
{
    protected $signature = 'demo:popular-oravel {--remover : Apaga os dados de demonstração} {--force : Cria mesmo se a demonstração já existir} {--sem-modulos : Não liga os módulos no contrato} {--criar-usuario : Cria (ou renova a senha do) usuário de teste do cliente Oravel e mostra a senha uma vez}';

    protected $description = 'Popula o cliente Oravel com dados de demonstração da Gestão de Frota e das Propostas (removível com --remover)';

    private Tenant $tenant;

    private ?User $usuario = null;

    public function handle(): int
    {
        $this->tenant = Tenant::where('slug', 'oravel')->orWhere('name', 'Oravel')->first() ?? new Tenant;
        if (! $this->tenant->exists) {
            $this->error('Cliente "Oravel" não encontrado.');

            return self::FAILURE;
        }
        $this->usuario = User::where('tenant_id', $this->tenant->id)->orderBy('created_at')->first();

        if ($this->option('remover')) {
            return $this->remover();
        }

        if (! $this->option('force') && Asset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('tag', 'like', 'DEMO-%')->exists()) {
            $this->warn('A demonstração já existe. Use --remover para apagar ou --force para criar de novo.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            if ($this->option('criar-usuario')) {
                $this->usuarioDeTeste();
            }
            if (! $this->option('sem-modulos')) {
                $this->ligarModulos();
            }
            $this->frota();
            $this->propostas();
        });

        $this->info('Demonstração criada para o cliente Oravel.');

        return self::SUCCESS;
    }

    // --------------------------------------------------------- usuário de teste

    public const EMAIL_TESTE = 'demo.oravel@oravel.test';

    /**
     * Administrador do cliente Oravel para os testes no DEV. NÃO é administrador da plataforma (ele fica de fora da lista
     * SUPER_ADMINS), então enxerga o sistema como um cliente enxerga. A senha é gerada e mostrada uma vez.
     */
    private function usuarioDeTeste(): void
    {
        $senha = Str::password(14, symbols: false);
        $u = User::where('email', self::EMAIL_TESTE)->first() ?? new User(['email' => self::EMAIL_TESTE]);
        $u->forceFill(['name' => 'Demo — Administrador Oravel', 'tenant_id' => $this->tenant->id, 'password' => $senha, 'is_approved' => true, 'email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        abort_if($u->fresh()->isSuperAdmin(), 500, 'O usuário de teste não pode ser administrador da plataforma.');
        $this->usuario ??= $u;

        $this->newLine();
        $this->info('Usuário de teste (administrador do cliente Oravel, NÃO é administrador da plataforma):');
        $this->line('  e-mail: '.self::EMAIL_TESTE);
        $this->line('  senha:  '.$senha.'   (mostrada só agora; rode com --criar-usuario de novo para gerar outra)');
        $this->newLine();
    }

    // ---------------------------------------------------------------- módulos

    private function ligarModulos(): void
    {
        $plano = Plan::find($this->tenant->plan_id);
        if (! $plano) {
            return;
        }
        $usadas = [Asset::class, Client::class, FleetDriver::class, PropostaComercial::class, Part::class, Warehouse::class, MaintenanceOrder::class, AssetDowntimeEvent::class];
        $chaves = array_map(fn ($c) => $c::saasFeatureKey(), $usadas);
        foreach (SaaSRegistry::modules() as $m) {
            if ($m['feature'] && str_starts_with($m['feature'], 'tabela_frota_')) {
                $chaves[] = $m['feature'];
            }
        }
        $features = (array) $plano->features;
        if (array_is_list($features)) {   // contrato salvo como lista de chaves ligadas
            $features = array_fill_keys($features, true);
        }
        $novas = 0;
        foreach (array_unique(array_filter($chaves)) as $k) {
            if (($features[$k] ?? false) !== true) {
                $features[$k] = true;
                $novas++;
            }
        }
        $plano->update(['features' => $features]);
        $this->line('Módulos ligados no contrato "'.$plano->name.'": '.$novas.' novos.');
    }

    // ------------------------------------------------------------------ frota

    private function ativo(array $d): Asset
    {
        return Asset::create(array_merge([
            'tenant_id' => $this->tenant->id, 'status' => Asset::STATUS_DISPONIVEL, 'grupo' => Asset::GRUPO_VEICULO,
            'acquisition_date' => now()->subYears(3)->toDateString(),
        ], $d));
    }

    private function frota(): void
    {
        $t = $this->tenant->id;
        ModelosChecklistPadrao::garantir($t);
        (new CatalogoEstoqueFrota)->garantir($t);

        // Motoristas
        $joao = FleetDriver::create(['tenant_id' => $t, 'name' => 'Demo — João Silva', 'cnh_number' => '01234567890', 'cnh_category' => 'E', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);
        $maria = FleetDriver::create(['tenant_id' => $t, 'name' => 'Demo — Maria Souza', 'cnh_number' => '09876543210', 'cnh_category' => 'D', 'cnh_expiry_date' => now()->addDays(10), 'active' => true]);
        FleetDriver::create(['tenant_id' => $t, 'name' => 'Demo — Carlos Lima', 'cnh_number' => '05555555555', 'cnh_category' => 'B', 'cnh_expiry_date' => now()->subDays(5), 'active' => true]);

        // Máquinas com placa de equipamento
        $this->ativo(['grupo' => Asset::GRUPO_MAQUINA, 'patrimonio' => 'DEMO-M01', 'tag' => 'DEMO-M01', 'name' => 'Demo — Gerador 180 kVA', 'placa' => 'GER0001', 'fabricante' => 'Cummins']);
        $this->ativo(['grupo' => Asset::GRUPO_MAQUINA, 'patrimonio' => 'DEMO-M02', 'tag' => 'DEMO-M02', 'name' => 'Demo — Empilhadeira 3 t', 'placa' => 'EMP0007', 'fabricante' => 'Hyster']);

        $v1 = $this->ativo([
            'patrimonio' => 'DEMO-V1', 'tag' => 'DEMO-V1', 'name' => 'Demo — Caminhão baú Atego 2429', 'fabricante' => 'Mercedes-Benz', 'modelo' => 'Atego 2429', 'cor' => 'Branca',
            'placa' => 'DEM1A01', 'renavam' => '12345678901', 'chassi' => '9BM958179LB123456', 'manufacturing_year' => 2021, 'ano_modelo' => 2022,
            'odometro_atual' => 100000, 'horimetro_inicial' => 62000, 'acquisition_value' => 420000, 'veiculo_pesado' => true,
            'carroceria_tipo' => 'bau', 'carroceria_detalhe' => 'Baú de 8 m', 'quantidade_eixos' => 3, 'tacografo_numero' => 'TC-101', 'tacografo_vencimento' => now()->addMonths(5)->toDateString(),
            'licenciamento_vencimento' => now()->addMonths(3)->toDateString(), 'ipva_vencimento' => now()->subDays(6)->toDateString(),
            'seguro_seguradora' => 'Porto Seguro', 'seguro_apolice' => 'AP-7788', 'seguro_vencimento' => now()->addDays(12)->toDateString(),
            'seguro_valor_cobertura' => 380000, 'seguro_cobertura_terceiros' => 300000, 'seguro_franquia_colisao' => 9500, 'seguro_franquia_vidros' => 1200,
        ]);
        $v2 = $this->ativo([
            'patrimonio' => 'DEMO-V2', 'tag' => 'DEMO-V2', 'name' => 'Demo — Caminhão carga aberta FH 460', 'fabricante' => 'Volvo', 'modelo' => 'FH 460', 'cor' => 'Azul',
            'placa' => 'DEM2B02', 'chassi' => '9BVAG20C0LE654321', 'manufacturing_year' => 2020, 'ano_modelo' => 2020, 'odometro_atual' => 200000, 'horimetro_inicial' => 145000,
            'acquisition_value' => 510000, 'veiculo_pesado' => true, 'carroceria_tipo' => 'carga_aberta', 'carroceria_detalhe' => 'Carroceria de madeira', 'quantidade_eixos' => 2,
            'licenciamento_vencimento' => now()->addMonths(2)->toDateString(), 'ipva_vencimento' => now()->addMonths(2)->toDateString(),
            'seguro_seguradora' => 'Azul Seguros', 'seguro_apolice' => 'AP-1199', 'seguro_vencimento' => now()->addMonths(7)->toDateString(),
        ]);
        $v3 = $this->ativo([
            'patrimonio' => 'DEMO-V3', 'tag' => 'DEMO-V3', 'name' => 'Demo — Utilitário Fiorino', 'fabricante' => 'Fiat', 'modelo' => 'Fiorino', 'cor' => 'Prata',
            'placa' => 'DEM3C03', 'manufacturing_year' => 2023, 'ano_modelo' => 2023, 'odometro_atual' => 30000, 'horimetro_inicial' => 0, 'acquisition_value' => 98000,
            'licenciamento_vencimento' => now()->addMonths(8)->toDateString(), 'ipva_vencimento' => now()->addMonths(8)->toDateString(),
            'seguro_seguradora' => 'Porto Seguro', 'seguro_apolice' => 'AP-4500', 'seguro_vencimento' => now()->addMonths(9)->toDateString(),
        ]);
        $v4 = $this->ativo([
            'patrimonio' => 'DEMO-V4', 'tag' => 'DEMO-V4', 'name' => 'Demo — Carreta Randon (vendida)', 'fabricante' => 'Randon', 'modelo' => 'SR BA', 'cor' => 'Cinza',
            'placa' => 'DEM4D04', 'manufacturing_year' => 2016, 'ano_modelo' => 2016, 'odometro_atual' => 350000, 'horimetro_inicial' => 120000, 'acquisition_value' => 180000, 'veiculo_pesado' => true,
            'carroceria_tipo' => 'implemento', 'carroceria_detalhe' => 'Carreta 3 eixos', 'quantidade_eixos' => 6,
        ]);

        $this->frotaCaminhaoBau($v1, $joao);
        $this->frotaCaminhaoAberto($v2);
        $this->frotaUtilitario($v3, $maria, $v1);
        (new BaixaVeiculoService)->registrar($v4, ['tipo' => 'venda', 'valor' => 78000, 'comprador' => 'Transportes Delta', 'documento' => 'NF 1520', 'motivo' => 'Renovação da frota', 'odometro_final' => 351200]);

        // Estoque da frota: quase tudo com saldo; Arla e pneu abaixo do mínimo, bateria zerada
        $alm = Warehouse::create(['tenant_id' => $t, 'name' => 'Demo — Almoxarifado Central', 'is_active' => true]);
        $categorias = PartCategory::withoutGlobalScopes()->where('tenant_id', $t)->whereIn('name', array_keys(CatalogoEstoqueFrota::ITENS))->pluck('id');
        foreach (Part::withoutGlobalScopes()->where('tenant_id', $t)->whereIn('part_category_id', $categorias)->get() as $peca) {
            $saldo = match ($peca->name) {
                'Arla 32' => 30, 'Pneu' => 1, 'Bateria' => 0,
                default => max(1, (float) $peca->minimum_stock * 2),
            };
            WarehouseStock::create(['warehouse_id' => $alm->id, 'part_id' => $peca->id, 'current_quantity' => $saldo, 'reserved_quantity' => 0]);
        }
        $this->line('Frota: 2 máquinas com placa, 4 veículos (1 vendido), 3 motoristas e estoque.');
    }

    /** Veículo "completo": quase todas as telas têm algo para ver. */
    private function frotaCaminhaoBau(Asset $v, FleetDriver $joao): void
    {
        $t = $this->tenant->id;
        $u = $this->usuario;

        // Pneus montados (seis rodas + estepe); um deles com sulco crítico
        $pos = ['E1-LE', 'E1-LD', 'E2-LEE', 'E2-LEI', 'E2-LDI', 'E2-LDE', 'ESTEPE'];
        foreach ($pos as $i => $p) {
            $pneu = FrotaPneu::create(['tenant_id' => $t, 'numero_fogo' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'marca' => 'Pirelli', 'modelo' => 'FG01', 'medida' => '295/80 R22.5',
                'dot' => $i === 0 ? '1218' : '2523', 'vida' => FrotaPneu::VIDA_NOVO, 'sulco_inicial_mm' => 16, 'custo' => 2300, 'pressao_min_psi' => 110, 'pressao_max_psi' => 120]);
            (new PneuService)->montar($pneu, $v->fresh(), $p, 100000, $u);
            (new PneuService)->registrarInspecao($pneu, $i === 1 ? 1.4 : 9.5 - $i * 0.4, 112, 100000);
        }
        FrotaPneu::create(['tenant_id' => $t, 'numero_fogo' => 'DEMO-101', 'marca' => 'Michelin', 'medida' => '295/80 R22.5', 'dot' => '4024', 'sulco_inicial_mm' => 16, 'custo' => 2900]);
        FrotaPneu::create(['tenant_id' => $t, 'numero_fogo' => 'DEMO-102', 'marca' => 'Michelin', 'medida' => '295/80 R22.5', 'dot' => '4024', 'sulco_inicial_mm' => 16, 'custo' => 2900]);

        // Baterias
        $b1 = FrotaBateria::create(['tenant_id' => $t, 'marca' => 'Moura', 'modelo' => 'M150', 'amperagem_ah' => 150, 'numero_serie' => 'DEMO-B1', 'comprada_em' => now()->subMonths(40)->toDateString(), 'garantia_ate' => now()->addDays(20)->toDateString(), 'custo' => 980]);
        $b2 = FrotaBateria::create(['tenant_id' => $t, 'marca' => 'Moura', 'modelo' => 'M150', 'amperagem_ah' => 150, 'numero_serie' => 'DEMO-B2', 'comprada_em' => now()->subMonths(8)->toDateString(), 'garantia_ate' => now()->addMonths(10)->toDateString(), 'custo' => 980]);
        (new BateriaService)->instalar($b1, $v->fresh(), 100000, $u);
        (new BateriaService)->instalar($b2, $v->fresh(), 100000, $u);
        (new BateriaService)->registrarTeste($b1, 11.8, 100000);
        (new BateriaService)->registrarTeste($b2, 12.7, 100000);
        FrotaBateria::create(['tenant_id' => $t, 'marca' => 'Heliar', 'modelo' => 'HF150', 'amperagem_ah' => 150, 'numero_serie' => 'DEMO-B3', 'custo' => 1050]);

        // Óleo (plano + troca) e revisões feitas cedo; o odômetro sobe depois e vence algumas
        $oleo = new OleoService;
        $oleo->salvarPlano($v->fresh(), ['intervalo_km' => 10000, 'intervalo_dias' => 180, 'especificacao_oleo' => '15W40 CK-4', 'capacidade_litros' => 38]);
        $oleo->registrar($v->fresh(), ['tipo' => 'troca', 'litros' => 38, 'produto' => '15W40 CK-4', 'odometro' => 100000, 'custo' => 760], $u);
        $rev = new RevisaoService;
        $rev->aplicarPadrao($v->fresh());
        foreach (['Freios (pastilhas/lonas)' => [-400, 100000], 'Filtro de ar' => [-30, 100000], 'Alinhamento e balanceamento' => [-200, 100000]] as $nome => [$dias, $km]) {
            $plano = FrotaPlanoRevisao::where('ativo_id', $v->id)->where('nome', $nome)->first();
            if ($plano) {
                $rev->registrar($plano, ['realizada_em' => now()->addDays($dias)->toDateString(), 'odometro' => $km, 'custo' => 450], $u);
            }
        }

        // Kit de segurança
        $kit = new KitSegurancaService;
        $kit->aplicarPadrao($v->fresh());
        $item = fn (string $nome) => FrotaItemSeguranca::where('ativo_id', $v->id)->where('nome', $nome)->first();
        $kit->conferir($item('Extintor de incêndio'), ['presente' => true, 'validade' => now()->subDays(20)->toDateString(), 'identificacao' => 'LAC-5521']);
        $kit->conferir($item('Triângulo de sinalização'), ['presente' => true]);
        $kit->conferir($item('Macaco'), ['presente' => false, 'observacoes' => 'Macaco emprestado para a oficina']);
        $kit->conferir($item('Chave de roda'), ['presente' => true]);
        $kit->conferir($item('Kit de primeiros socorros'), ['presente' => true, 'validade' => now()->addDays(20)->toDateString()]);
        $kit->conferir($item('Colete refletivo'), ['presente' => true]);

        // Titular, chaves e checklist completo (tudo OK)
        (new VinculoMotoristaService)->atribuir($v->fresh(), $joao->id, now()->subDays(60)->toDateString(), null, $u);
        $chaves = new ChaveService;
        $principal = $chaves->criar($v->fresh(), 'Chave principal');
        $chaves->entregar($principal, ['motorista_id' => $joao->id, 'motivo' => 'Uso diário', 'entregue_em' => now()->subDays(2)->toDateTimeString()], $u);
        $reserva = $chaves->criar($v->fresh(), 'Chave reserva');
        $chaves->entregar($reserva, ['responsavel_nome' => 'Mecânico Zé (oficina Central)', 'motivo' => 'Cópia da chave', 'entregue_em' => now()->subDays(9)->toDateTimeString()], $u);
        $this->checklist($v->fresh(), FrotaModeloChecklist::TIPO_COMPLETO, FrotaChecklist::TIPO_SAIDA, 100000, $joao, null);

        // Abastecimentos: consumo estável ~3,3 km/l e uma queda no último
        $abast = new AbastecimentoService;
        $km = 100000;
        $abast->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 400, 'valor_total' => 2480, 'odometro' => $km + 60, 'abastecido_em' => now()->subDays(52)->toDateTimeString(), 'motorista_id' => $joao->id]);
        $km += 60;
        foreach ([[-45, 500], [-38, 500], [-31, 500], [-24, 500], [-17, 500], [-8, 720]] as [$dias, $litros]) {
            $km += 1650;   // 1.650 km por abastecimento: 3,3 km/l (e 2,3 km/l no último, que acende o alerta)
            $abast->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => $litros, 'valor_total' => $litros * 6.2, 'odometro' => $km, 'abastecido_em' => now()->addDays($dias)->toDateTimeString(), 'motorista_id' => $joao->id, 'posto' => 'Posto Rodovia']);
        }

        // Entrada e saída (diretoria, devolvida) e multas ligadas pelo horário da saída
        $saidas = new SaidaVeiculoService;
        $saida = $saidas->registrarSaida($v->fresh(), ['finalidade' => 'diretoria', 'motorista_id' => $joao->id, 'destino' => 'Feira de equipamentos — São Paulo', 'motivo' => 'Visita à feira',
            'odometro' => $km + 20, 'saida_em' => now()->subDays(6)->subHours(8)->toDateTimeString(), 'combustivel' => 'cheio'], $u);
        $saidas->registrarEntrada($saida, ['odometro' => $km + 420, 'retorno_em' => now()->subDays(5)->toDateTimeString(), 'combustivel' => '1/2', 'observacoes' => 'Sem ocorrências'], $u);
        $multas = new MultaService;
        $mk = fn (string $auto, string $grav, float $valor, string $venc, string $desc) => $multas->registrar($v->fresh(), [
            'numero_auto' => $auto, 'infracao_em' => now()->subDays(6)->subHours(4)->toDateTimeString(), 'descricao' => $desc, 'gravidade' => $grav, 'valor' => $valor,
            'vencimento' => $venc, 'local' => 'BR-116 km 220']);
        $mk('DEMO-AIT-001', 'gravissima', 293.47, now()->addDays(20)->toDateString(), 'Excesso de velocidade acima de 50%');
        $mk('DEMO-AIT-002', 'grave', 195.23, now()->subDays(3)->toDateString(), 'Ultrapassagem em local proibido');
        $mk('DEMO-AIT-003', 'media', 130.16, now()->addDays(5)->toDateString(), 'Estacionar em local proibido');

        // Custos avulsos, tag e pedágios, lavagem
        FrotaCustoAvulso::create(['tenant_id' => $this->tenant->id, 'ativo_id' => $v->id, 'tipo' => 'seguro', 'data' => now()->subMonths(2)->startOfMonth()->toDateString(), 'valor' => 14400, 'rateio_meses' => 12, 'descricao' => 'Seguro anual Porto Seguro']);
        FrotaCustoAvulso::create(['tenant_id' => $this->tenant->id, 'ativo_id' => $v->id, 'tipo' => 'ipva', 'data' => now()->subMonths(1)->startOfMonth()->toDateString(), 'valor' => 7200, 'rateio_meses' => 12, 'descricao' => 'IPVA']);
        $ped = new PedagioService;
        $ped->criarTag($v->fresh(), 'DEMO-TAG-0001', 'sem_parar');
        foreach ([['Pedágio Anhanguera km 60', 14.20], ['Pedágio Bandeirantes km 41', 31.60], ['Pedágio Dutra km 124', 22.90]] as $i => [$local, $valor]) {
            $ped->registrar($v->fresh(), ['local' => $local, 'valor' => $valor, 'passou_em' => now()->subDays(6)->subHours(7 - $i)->toDateTimeString()], $u);
        }
        (new LavagemService)->registrar($v->fresh(), ['tipo' => 'completa', 'realizada_em' => now()->subDays(20)->toDateString(), 'valor' => 120, 'local' => 'Lava-jato Central', 'intervalo_dias' => 15], $u);
    }

    /** Veículo bloqueado pelo checklist e com acidente (parado há mais de 15 dias, com OS de reparo). */
    private function frotaCaminhaoAberto(Asset $v): void
    {
        $u = $this->usuario;
        $this->checklist($v->fresh(), FrotaModeloChecklist::TIPO_RAPIDO, FrotaChecklist::TIPO_SAIDA, 200000, null, 'problema');
        $sin = new SinistroService;
        $s = $sin->registrar($v->fresh(), ['tipo' => 'colisao', 'ocorrido_em' => now()->subDays(17)->toDateTimeString(), 'descricao' => 'Colisão traseira na descida da serra. Danos no para-choque e no radiador.',
            'local' => 'SP-270 km 85', 'culpa' => 'terceiro', 'houve_vitima' => false, 'seguradora' => 'Azul Seguros', 'apolice' => 'AP-1199', 'numero_sinistro_seguradora' => 'SIN-2231',
            'veiculo_parado' => true, 'valor_franquia' => 6500], $u);
        $sin->registrarOrcamento($s, 18400, 6500);
        $sin->gerarOs($s->fresh());
        (new AbastecimentoService)->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 300, 'valor_total' => 1860, 'odometro' => 200400, 'abastecido_em' => now()->subDays(20)->toDateTimeString()]);
    }

    /** Utilitário leve: saída em aberto há mais de um dia, motorista com CNH vencendo. */
    private function frotaUtilitario(Asset $v, FleetDriver $maria, Asset $v1): void
    {
        $u = $this->usuario;
        (new SaidaVeiculoService)->registrarSaida($v->fresh(), ['finalidade' => 'visita_tecnica', 'motorista_id' => $maria->id, 'destino' => 'Obra do cliente — Campinas',
            'motivo' => 'Visita técnica ao gerador', 'odometro' => 30020, 'saida_em' => now()->subHours(30)->toDateTimeString(), 'combustivel' => '3/4'], $u);
        (new KitSegurancaService)->aplicarPadrao($v->fresh());
    }

    /** Preenche um checklist inteiro: tudo OK, ou (modo "problema") um item crítico com problema. */
    private function checklist(Asset $v, string $tipoModelo, string $tipo, int $odometro, ?FleetDriver $motorista, ?string $modo): void
    {
        $modelo = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('tipo', $tipoModelo)->where('ativo', true)->with('itens')->firstOrFail();
        $respostas = [];
        $marcado = false;
        foreach ($modelo->itens as $item) {
            $r = ['resultado' => 'ok'];
            if ($item->unidade === 'mm') {
                $r['valor'] = 8.5;
            } elseif ($item->unidade === 'V') {
                $r['valor'] = 12.6;
            }
            if ($modo === 'problema' && ! $marcado && $item->gravidade === 'critica') {
                $r = ['resultado' => 'problema', 'observacao' => 'Freio com folga excessiva.'];
                $marcado = true;
            }
            $respostas[$item->id] = $r;
        }
        (new ChecklistFrotaService)->registrar($v, ['tipo' => $tipo, 'modelo_id' => $modelo->id, 'motorista_id' => $motorista?->id, 'odometro' => $odometro,
            'nivel_combustivel' => 'cheio', 'respostas' => $respostas, 'observacoes' => $modo === 'problema' ? 'Veículo bloqueado para o teste.' : null], $this->usuario);
    }

    // -------------------------------------------------------------- propostas

    private function propostas(): void
    {
        $t = $this->tenant->id;
        $vendedor = $this->usuario;
        $alfa = Client::create(['tenant_id' => $t, 'name' => 'Demo — Construtora Alfa', 'email' => 'compras@alfa.demo.test', 'whatsapp' => '(19) 99933-2615', 'phone' => '(19) 3333-0001']);
        $beta = Client::create(['tenant_id' => $t, 'name' => 'Demo — Mineração Beta', 'email' => 'contato@beta.demo.test', 'phone' => '(31) 98888-7777']);
        $gama = Client::create(['tenant_id' => $t, 'name' => 'Demo — Prefeitura Gama', 'email' => 'licitacao@gama.demo.test']);
        $cat = AssetCategory::firstOrCreate(['tenant_id' => $t, 'name' => 'Demo — Gerador']);
        $cat2 = AssetCategory::firstOrCreate(['tenant_id' => $t, 'name' => 'Demo — Empilhadeira']);

        $nova = function (?Client $cliente, array $itens, string $status, array $extra = []) use ($t, $vendedor) {
            $p = PropostaComercial::create(array_merge(['tenant_id' => $t, 'client_id' => $cliente?->id, 'seller_user_id' => $vendedor->id,
                'terms' => '[DEMO] Pagamento em 30 dias. Frete por conta do locatário.', 'valid_until' => now()->addDays(15)->toDateString()], $extra));
            foreach ($itens as [$tipo, $categoria, $desc, $qtd, $valor]) {
                $p->items()->create(['tenant_id' => $t, 'type' => $tipo, 'asset_category_id' => $categoria?->id, 'description' => $desc, 'quantity' => $qtd, 'unit_price' => $valor, 'unit_period' => 'mensal']);
            }
            $p->recalculateTotal();
            // Status colocado direto (sem o fluxo real) para NÃO mandar e-mail de verdade nos dados de demonstração.
            $p->forceFill(['status' => $status])->save();

            return $p->fresh();
        };
        $eq = fn ($c, $d, $q, $v) => ['equipamento', $c, $d, $q, $v];
        $sv = fn ($d, $q, $v) => ['servico', null, $d, $q, $v];

        $nova($alfa, [$eq($cat, 'Gerador 180 kVA', 2, 8500), $sv('Operador de gerador', 2, 4200)], PropostaComercial::STATUS_RASCUNHO);
        $nova(null, [$eq($cat2, 'Empilhadeira 3 t', 1, 6200)], PropostaComercial::STATUS_RASCUNHO);   // incompleta: sem cliente
        $nova($beta, [$eq($cat, 'Gerador 500 kVA', 1, 18000)], PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, ['sent_at' => now()->subDay()]);
        $nova($alfa, [$eq($cat2, 'Empilhadeira 3 t', 3, 6200)], PropostaComercial::STATUS_APROVADA_INTERNA,
            ['sent_at' => now()->subDays(3), 'reviewed_by_user_id' => $vendedor->id, 'reviewed_at' => now()->subDays(2), 'approval_token' => Str::random(48)]);
        $nova($beta, [$eq($cat, 'Gerador 180 kVA', 1, 8500)], PropostaComercial::STATUS_ACEITA_PELO_CLIENTE,
            ['sent_at' => now()->subDays(10), 'reviewed_by_user_id' => $vendedor->id, 'reviewed_at' => now()->subDays(9), 'approval_token' => Str::random(48)]);
        $nova($gama, [$eq($cat2, 'Empilhadeira 3 t', 2, 6200)], PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE,
            ['sent_at' => now()->subDays(12), 'reviewed_by_user_id' => $vendedor->id, 'reviewed_at' => now()->subDays(11), 'approval_token' => Str::random(48)]);
        $nova($gama, [$eq($cat, 'Gerador 500 kVA', 1, 18000)], PropostaComercial::STATUS_REJEITADA,
            ['sent_at' => now()->subDays(5), 'reviewed_by_user_id' => $vendedor->id, 'reviewed_at' => now()->subDays(4), 'rejection_reason' => 'Valor abaixo da tabela. Refazer com o desconto autorizado.']);
        $this->line('Propostas: 3 clientes e 7 propostas, uma em cada etapa do Kanban (duas em rascunho).');
    }

    // ---------------------------------------------------------------- remover

    private function remover(): int
    {
        $t = $this->tenant->id;
        // Apaga de verdade: modelos com exclusão reversível (SoftDeletes) usam forceDelete.
        $apagar = fn ($consulta, string $modelo) => in_array(SoftDeletes::class, class_uses_recursive($modelo), true) ? $consulta->forceDelete() : $consulta->delete();
        DB::transaction(function () use ($t, $apagar) {
            $ativos = Asset::withoutGlobalScopes()->where('tenant_id', $t)->where('tag', 'like', 'DEMO-%')->pluck('id');
            $clientes = Client::withoutGlobalScopes()->where('tenant_id', $t)->where('name', 'like', 'Demo — %')->pluck('id');

            $propostas = PropostaComercial::withoutGlobalScopes()->where('tenant_id', $t)->where('terms', 'like', '[DEMO]%')->pluck('id');
            PropostaComercialItem::withoutGlobalScopes()->whereIn('proposta_comercial_id', $propostas)->delete();
            $apagar(PropostaComercial::withoutGlobalScopes()->whereIn('id', $propostas), PropostaComercial::class);
            $apagar(Client::withoutGlobalScopes()->whereIn('id', $clientes), Client::class);
            $apagar(AssetCategory::withoutGlobalScopes()->where('tenant_id', $t)->where('name', 'like', 'Demo — %'), AssetCategory::class);

            $apagar(MaintenanceOrder::withoutGlobalScopes()->whereIn('asset_id', $ativos), MaintenanceOrder::class);
            $apagar(FrotaPneu::withoutGlobalScopes()->where('tenant_id', $t)->where('numero_fogo', 'like', 'DEMO-%'), FrotaPneu::class);
            $apagar(FrotaBateria::withoutGlobalScopes()->where('tenant_id', $t)->where('numero_serie', 'like', 'DEMO-B%'), FrotaBateria::class);
            $apagar(Asset::withoutGlobalScopes()->whereIn('id', $ativos), Asset::class);
            $apagar(FleetDriver::withoutGlobalScopes()->where('tenant_id', $t)->where('name', 'like', 'Demo — %'), FleetDriver::class);
            User::where('email', self::EMAIL_TESTE)->where('tenant_id', $t)->delete();
            $alm = Warehouse::withoutGlobalScopes()->where('tenant_id', $t)->where('name', 'like', 'Demo — %')->pluck('id');
            WarehouseStock::whereIn('warehouse_id', $alm)->delete();
            DB::table('warehouses')->whereIn('id', $alm)->delete();
        });
        $this->info('Dados de demonstração do cliente Oravel removidos.');

        return self::SUCCESS;
    }
}
