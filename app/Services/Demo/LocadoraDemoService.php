<?php

namespace App\Services\Demo;

use App\Dashboards\Personalizacao\DashboardInteligenciaDeAtivos;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Empresa "Locadora Demo" (apresentacao para a ALEC): 25 equipamentos, clientes e obras ficticios em cidades de
 * SP, 18 meses de manutencao com custos realistas e 4 equipamentos "problematicos" (custo acumulado alto em
 * relacao ao valor). Tudo isolado em UM tenant marcado is_demo; nada toca em tenant real.
 *
 * - criar(): recria do zero (apaga o que ja existir da demo e cria de novo; resultado deterministico).
 * - apagar(): remove SO' o tenant demo e as linhas dele (todas as tabelas com tenant_id), com trava: recusa se
 *   o tenant de slug "locadora-demo" existir sem a marca is_demo.
 *
 * Insere direto nas tabelas (sem observers: nao geocodifica pela rede, nao manda e-mail nem notificacao).
 */
class LocadoraDemoService
{
    public const SLUG = 'locadora-demo';

    public const NOME = 'Locadora Demo';

    public const EMAIL_ADMIN = 'demo@locadorademo.test';

    public const PLANO_NOME = 'Contrato Locadora Demo (DEMO)';

    /** Chaves das telas novas (custo por equipamento e localizacao): ligadas SO' no contrato da demo. */
    public const FEATURES_EXTRAS = ['menu_custo_equipamentos', 'menu_localizacao_equipamentos'];

    private const SEMENTE = 20261109;

    private string $tenantId;

    /** @var string[] */
    private array $tecnicos = [];

    /** @var array<int, string> id dos clientes por indice */
    private array $clienteIds = [];

    /** @var array<string, string> id das unidades internas por chave */
    private array $unidadeIds = [];

    /** @var array<string, string> id das categorias por nome */
    private array $categoriaIds = [];

    private int $seqOs = 0;

    private int $seqContrato = 0;

    private Carbon $agora;

    /** Cidades/obras (coordenadas aproximadas do centro; a demo nao consulta a rede). */
    private const OBRAS = [
        0 => ['cliente' => 0, 'nome' => 'Obra Residencial Vista Verde', 'cep' => '13015-904', 'endereco' => 'Av. Dr. Moraes Salles, 1136 - Centro', 'cidade' => 'Campinas', 'lat' => -22.9056, 'lng' => -47.0608],
        1 => ['cliente' => 0, 'nome' => 'Torre Comercial Jundiaí', 'cep' => '13201-005', 'endereco' => 'Av. 9 de Julho, 2000 - Anhangabaú', 'cidade' => 'Jundiaí', 'lat' => -23.1857, 'lng' => -46.8978],
        2 => ['cliente' => 1, 'nome' => 'Ampliação da Planta Industrial', 'cep' => '12243-000', 'endereco' => 'Av. Cassiano Ricardo, 601 - Jd. Aquarius', 'cidade' => 'São José dos Campos', 'lat' => -23.2237, 'lng' => -45.9009],
        3 => ['cliente' => 2, 'nome' => 'Reforma da Fachada do Shopping', 'cep' => '01310-100', 'endereco' => 'Av. Paulista, 1000 - Bela Vista', 'cidade' => 'São Paulo', 'lat' => -23.5614, 'lng' => -46.6559],
        4 => ['cliente' => 3, 'nome' => 'Galpão Logístico - Fase 2', 'cep' => '18035-400', 'endereco' => 'Av. Dom Aguirre, 1500 - Jd. Sorocaba', 'cidade' => 'Sorocaba', 'lat' => -23.5015, 'lng' => -47.4526],
        5 => ['cliente' => 4, 'nome' => 'Manutenção de Fachada do Condomínio', 'cep' => '11065-001', 'endereco' => 'Av. Ana Costa, 400 - Gonzaga', 'cidade' => 'Santos', 'lat' => -23.9618, 'lng' => -46.3322],
        6 => ['cliente' => 5, 'nome' => 'Troca de Cobertura da Fábrica', 'cep' => '13400-000', 'endereco' => 'Av. Independência, 3500 - Alemães', 'cidade' => 'Piracicaba', 'lat' => -22.7253, 'lng' => -47.6492],
        7 => ['cliente' => 6, 'nome' => 'Montagem de Palco - Festival', 'cep' => '09015-000', 'endereco' => 'Av. Industrial, 600 - Santa Maria', 'cidade' => 'Santo André', 'lat' => -23.6639, 'lng' => -46.5383],
        8 => ['cliente' => 7, 'nome' => 'Silo de Armazenagem', 'cep' => '14020-260', 'endereco' => 'Av. Presidente Vargas, 2121 - Jd. América', 'cidade' => 'Ribeirão Preto', 'lat' => -21.1775, 'lng' => -47.8103],
        9 => ['cliente' => 7, 'nome' => 'Unidade Bauru', 'cep' => '17012-340', 'endereco' => 'Av. Nações Unidas, 8-50 - Vila Universitária', 'cidade' => 'Bauru', 'lat' => -22.3246, 'lng' => -49.0871],
    ];

    private const CLIENTES = [
        0 => ['name' => 'Construtora Horizonte Ltda', 'cidade' => 'Campinas', 'doc' => '11.111.111/0001-01'],
        1 => ['name' => 'Metalúrgica Vale Forte S.A.', 'cidade' => 'São José dos Campos', 'doc' => '11.111.111/0001-02'],
        2 => ['name' => 'Shopping Plaza Nova', 'cidade' => 'São Paulo', 'doc' => '11.111.111/0001-03'],
        3 => ['name' => 'Logística Rota Sul Ltda', 'cidade' => 'Sorocaba', 'doc' => '11.111.111/0001-04'],
        4 => ['name' => 'Condomínio Parque das Águas', 'cidade' => 'Santos', 'doc' => '11.111.111/0001-05'],
        5 => ['name' => 'Indústria Têxtil Brasa', 'cidade' => 'Piracicaba', 'doc' => '11.111.111/0001-06'],
        6 => ['name' => 'Eventos Estrela Produções', 'cidade' => 'Santo André', 'doc' => '11.111.111/0001-07'],
        7 => ['name' => 'Agroindustrial Campo Bom', 'cidade' => 'Ribeirão Preto', 'doc' => '11.111.111/0001-08'],
    ];

    /**
     * Equipamentos: codigo, categoria, nome, fabricante, modelo, ano, valor de aquisicao, status, obra (se locado),
     * perfil ('normal' ou 'problematico' com meta de custo acumulado e numero de corretivas).
     */
    private const ATIVOS = [
        ['PT-01', 'Plataforma Elevatória', 'Plataforma Tesoura 8m Elétrica', 'Genie', 'GS-1932', 2021, 62000, 'locado', 3, null],
        ['PT-02', 'Plataforma Elevatória', 'Plataforma Tesoura 10m Elétrica', 'JLG', '3246ES', 2020, 78000, 'locado', 0, ['alvo' => 49200, 'corretivas' => 9, 'retrabalho' => 3, 'tema' => 'hidraulica']],
        ['PT-03', 'Plataforma Elevatória', 'Plataforma Tesoura 12m Elétrica', 'Skyjack', 'SJIII-4632', 2022, 95000, 'disponivel', null, null],
        ['PT-04', 'Plataforma Elevatória', 'Plataforma Tesoura 8m Elétrica', 'Genie', 'GS-1932', 2019, 58000, 'manutencao', null, null],
        ['PT-05', 'Plataforma Elevatória', 'Plataforma Tesoura 10m Elétrica', 'JLG', '3246ES', 2023, 88000, 'locado', 9, null],
        ['PA-01', 'Plataforma Elevatória', 'Plataforma Articulada 15m Diesel', 'Genie', 'Z-45/25', 2020, 185000, 'locado', 2, null],
        ['PA-02', 'Plataforma Elevatória', 'Plataforma Articulada 12m Diesel', 'Skyjack', 'TB34', 2015, 120000, 'manutencao', null, ['alvo' => 66400, 'corretivas' => 8, 'retrabalho' => 2, 'tema' => 'articulada']],
        ['PA-03', 'Plataforma Elevatória', 'Plataforma Articulada 20m Diesel', 'JLG', '600AJ', 2022, 260000, 'disponivel', null, null],
        ['G-01', 'Gerador', 'Gerador 40 kVA Diesel', 'Stemac', 'STM-40', 2021, 38000, 'locado', 4, null],
        ['G-02', 'Gerador', 'Gerador 80 kVA Diesel', 'Cummins', 'C80D5', 2019, 62000, 'disponivel', null, null],
        ['G-03', 'Gerador', 'Gerador 150 kVA Diesel', 'Stemac', 'STM-150', 2016, 95000, 'locado', 8, ['alvo' => 61300, 'corretivas' => 10, 'retrabalho' => 3, 'tema' => 'gerador']],
        ['G-04', 'Gerador', 'Gerador 20 kVA Diesel', 'Branco', 'B4T-20', 2022, 21000, 'locado', 7, null],
        ['G-05', 'Gerador', 'Gerador 180 kVA Diesel', 'Cummins', 'C180D5', 2023, 118000, 'manutencao', null, null],
        ['C-01', 'Compressor', 'Compressor Portátil 750 PCM', 'Atlas Copco', 'XAS 185', 2021, 96000, 'locado', 1, null],
        ['C-02', 'Compressor', 'Compressor Parafuso 185 PCM', 'Schulz', 'SRP 3030', 2017, 42000, 'locado', 6, ['alvo' => 38100, 'corretivas' => 9, 'retrabalho' => 2, 'tema' => 'compressor']],
        ['C-03', 'Compressor', 'Compressor Pistão 20 pés', 'Schulz', 'MSV 40', 2022, 9800, 'disponivel', null, null],
        ['C-04', 'Compressor', 'Compressor Pistão 10 pés', 'Chiaperini', 'CJ 10', 2020, 4200, 'disponivel', null, null],
        ['AN-01', 'Andaime', 'Andaime Tubular (jogo 20 m²)', 'Metalmax', 'Tubular', 2020, 14000, 'locado', 5, null],
        ['AN-02', 'Andaime', 'Andaime Fachadeiro (jogo 50 m²)', 'Metalmax', 'Fachadeiro', 2019, 32000, 'locado', 3, null],
        ['AN-03', 'Andaime', 'Torre de Andaime Móvel 6m', 'Metalmax', 'TM-6', 2022, 11500, 'disponivel', null, null],
        ['FE-01', 'Ferramenta Elétrica', 'Martelete Rompedor 11 kg', 'Bosch', 'GSH 11 E', 2022, 6800, 'locado', 0, null],
        ['FE-02', 'Ferramenta Elétrica', 'Cortadora de Piso 14"', 'Husqvarna', 'FS 400', 2021, 18500, 'locado', 4, null],
        ['FE-03', 'Ferramenta Elétrica', 'Serra Mármore 110 mm', 'Makita', '4100NH', 2023, 1250, 'disponivel', null, null],
        ['FE-04', 'Ferramenta Elétrica', 'Betoneira 400 L', 'Menegotti', 'B400', 2020, 5600, 'disponivel', null, null],
        ['FE-05', 'Ferramenta Elétrica', 'Vibrador de Concreto 2"', 'Wacker', 'IREN 38', 2021, 3900, 'manutencao', null, null],
    ];

    private const VIDA_UTIL = ['Plataforma Elevatória' => 10, 'Gerador' => 10, 'Compressor' => 10, 'Andaime' => 15, 'Ferramenta Elétrica' => 5];

    private const DESC_PREVENTIVA = [
        'Plataforma Elevatória' => ['Preventiva 250h: lubrificação, inspeção de cilindros e teste de emergência de descida', 'Troca de óleo hidráulico e filtros, teste de carga e inspeção de baterias', 'Inspeção de segurança NR-18 e verificação de sensores de inclinação'],
        'Gerador' => ['Preventiva: troca de óleo, filtros de óleo/combustível/ar e teste em carga', 'Revisão do sistema de arrefecimento e teste do QTA', 'Inspeção do alternador e aperto de conexões elétricas'],
        'Compressor' => ['Preventiva: troca de óleo e elemento separador, limpeza do radiador', 'Revisão de válvulas, correias e filtros de ar', 'Teste de pressostato e válvula de segurança'],
        'Andaime' => ['Inspeção de travas, pinos e plataformas antes de nova locação', 'Revisão de rodízios e sapatas, repintura de pontos de oxidação'],
        'Ferramenta Elétrica' => ['Revisão geral: escovas, rolamentos e cabo de alimentação', 'Limpeza interna, lubrificação e teste de isolamento'],
    ];

    private const DESC_CORRETIVA = [
        'Plataforma Elevatória' => ['Vazamento no cilindro hidráulico de elevação', 'Falha no sensor de inclinação, plataforma não eleva', 'Troca de baterias tracionárias sem carga', 'Mangueira hidráulica rompida', 'Falha no painel de comando da cesta'],
        'Gerador' => ['Falha na partida: motor de arranque e bateria', 'Superaquecimento: radiador e bomba d\'água', 'Variação de tensão: regulador (AVR) defeituoso', 'Vazamento de óleo no cárter e retentores', 'Falha no módulo de controle do QTA'],
        'Compressor' => ['Perda de pressão: válvula de admissão e mangueiras', 'Superaquecimento: radiador e termostato', 'Vazamento de óleo no elemento compressor', 'Falha no pressostato e na válvula de alívio', 'Ruído anormal: rolamento do motor'],
        'Andaime' => ['Reposição de peças danificadas na devolução (travessas e pinos)', 'Reparo em plataforma metálica empenada'],
        'Ferramenta Elétrica' => ['Motor queimado: troca do induzido', 'Troca de gatilho e cabo danificado na devolução'],
    ];

    public function tenant(): ?Tenant
    {
        return Tenant::withoutGlobalScopes()->where('slug', self::SLUG)->first();
    }

    /** Recria a demo do zero. Devolve um resumo com contagens e a senha usada. */
    public function criar(string $senha): array
    {
        $existente = $this->tenant();
        if ($existente && ! $existente->is_demo) {
            throw new RuntimeException('Já existe uma empresa com o slug "'.self::SLUG.'" que NÃO está marcada como demo. Nada foi feito.');
        }
        if (User::withoutGlobalScopes()->where('email', self::EMAIL_ADMIN)->exists() && ! $existente) {
            throw new RuntimeException('O e-mail '.self::EMAIL_ADMIN.' já pertence a outro usuário. Nada foi feito.');
        }

        $this->agora = Carbon::now();
        $this->seqOs = 0;
        $this->seqContrato = 0;
        $this->tecnicos = $this->clienteIds = $this->unidadeIds = $this->categoriaIds = [];

        if ($existente) {
            $this->apagar();
        }

        mt_srand(self::SEMENTE);

        try {
            return DB::transaction(function () use ($senha) {
                $tenant = $this->criarEmpresa($senha);
                $this->tenantId = (string) $tenant->id;
                $this->criarUnidadesECategorias();
                $this->criarClientes();
                $resumo = $this->criarAtivosContratosEOrdens();

                // Dashboard "Inteligencia de Ativos" (modelo da plataforma) atribuido SO' a esta empresa, como tela Inicio.
                DashboardInteligenciaDeAtivos::atribuirA($tenant, true);

                return $resumo + [
                    'tenant_id' => $this->tenantId,
                    'email' => self::EMAIL_ADMIN,
                    'senha' => $senha,
                ];
            });
        } finally {
            mt_srand();
        }
    }

    /** Apaga a demo (tenant + linhas de todas as tabelas com tenant_id). Nao toca em nenhum outro tenant. */
    public function apagar(): array
    {
        $tenant = $this->tenant();
        if (! $tenant) {
            return ['apagado' => false, 'linhas' => 0];
        }
        if (! $tenant->is_demo) {
            throw new RuntimeException('A empresa "'.self::SLUG.'" existe mas NÃO está marcada como demo: nada foi apagado.');
        }

        $id = (string) $tenant->id;
        $planoId = (string) $tenant->plan_id;
        $usuarios = DB::table('users')->where('tenant_id', $id)->pluck('id')->map(fn ($v) => (string) $v)->all();

        $linhas = 0;

        DB::transaction(function () use ($id, $planoId, $usuarios, &$linhas) {
            foreach (['model_has_roles', 'model_has_permissions'] as $pivo) {
                if ($usuarios && Schema::hasTable($pivo)) {
                    $linhas += DB::table($pivo)->whereIn('model_id', $usuarios)->delete();
                }
            }
            if ($usuarios && Schema::hasTable('notifications')) {
                $linhas += DB::table('notifications')->whereIn('notifiable_id', $usuarios)->delete();
            }
            if (Schema::hasTable('activity_log')) {
                $linhas += DB::table('activity_log')->whereIn('subject_id', array_merge($usuarios, [$id, $planoId]))->delete();
            }

            // Tabelas com tenant_id, em varias passadas ate resolver as chaves estrangeiras (cada DELETE em savepoint).
            $pendentes = collect(Schema::getTables())
                ->map(fn ($t) => $t['name'])
                ->reject(fn ($n) => $n === 'tenants')
                ->filter(fn ($n) => Schema::hasColumn($n, 'tenant_id'))
                ->values()->all();

            for ($passada = 0; $passada < 12 && $pendentes; $passada++) {
                $restantes = [];
                foreach ($pendentes as $tabela) {
                    try {
                        $linhas += DB::transaction(fn () => DB::table($tabela)->where('tenant_id', $id)->delete());
                    } catch (QueryException $e) {
                        $restantes[] = $tabela;
                    }
                }
                if (count($restantes) === count($pendentes)) {
                    break;
                }
                $pendentes = $restantes;
            }

            DB::table('tenants')->where('id', $id)->delete();
            $linhas++;

            if ($planoId !== '' && ! DB::table('tenants')->where('plan_id', $planoId)->exists()) {
                $linhas += DB::table('plans')->where('id', $planoId)->where('name', self::PLANO_NOME)->delete();
            }
        });

        return ['apagado' => true, 'linhas' => $linhas];
    }

    // ───────────────────────── criacao ─────────────────────────

    private function criarEmpresa(string $senha): Tenant
    {
        // Formato MAPA (chave => true), o mesmo que a Central grava: o saas:sync-modules do deploy acrescenta as chaves novas como
        // false, e num contrato em formato de lista isso derrubava os modulos (a chave existente vence a lista).
        $features = collect(array_unique(array_merge(Pacotes::features('locadora'), self::FEATURES_EXTRAS)))
            ->mapWithKeys(fn ($chave) => [$chave => true])->all();

        $plano = Plan::create([
            'name' => self::PLANO_NOME, 'pacote' => 'locadora', 'price' => 0, 'base_price' => 0, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);

        $tenant = Tenant::create([
            'name' => self::NOME, 'slug' => self::SLUG, 'status' => 'active', 'plan_id' => $plano->id, 'is_demo' => true,
            'razao_social' => 'Locadora Demo Ltda (empresa fictícia para demonstração)', 'nome_fantasia' => 'Locadora Demo',
            'cpf_cnpj' => '00.000.000/0001-91', 'cidade' => 'Campinas', 'uf' => 'SP', 'email_contato' => self::EMAIL_ADMIN,
            'onboarding_completed' => true, 'interface_simples' => false,
        ]);

        $admin = TenantProvisioner::provision($tenant, ['name' => 'Administrador Demo', 'email' => self::EMAIL_ADMIN, 'password' => $senha]);
        $admin->forceFill(['is_approved' => true])->save();

        foreach (['Carlos Eduardo (técnico)', 'Marcos Vinícius (técnico)', 'Rafael Amaral (técnico)'] as $i => $nome) {
            $tecnico = User::create([
                'name' => $nome, 'email' => 'tecnico'.($i + 1).'@locadorademo.test', 'password' => $senha,
                'tenant_id' => $tenant->id, 'role' => 'tecnico', 'hourly_rate' => 48 + $i * 6,
            ]);
            $tecnico->forceFill(['is_approved' => true, 'email_verified_at' => now()])->save();
            $this->tecnicos[] = (string) $tecnico->id;
        }

        return $tenant;
    }

    private function criarUnidadesECategorias(): void
    {
        $unidades = [
            'matriz' => ['name' => 'Matriz Campinas (Oficina e Pátio)', 'code' => 'MTZ', 'type' => 'matriz', 'cidade' => 'Campinas', 'cep' => '13054-500', 'lat' => -22.9618, 'lng' => -47.1120],
            'deposito' => ['name' => 'Depósito São Paulo (Barueri)', 'code' => 'DEP-SP', 'type' => 'deposito', 'cidade' => 'Barueri', 'cep' => '06454-000', 'lat' => -23.5114, 'lng' => -46.8764],
        ];
        foreach ($unidades as $chave => $u) {
            $this->unidadeIds[$chave] = $this->inserir('internal_units', [
                'name' => $u['name'], 'code' => $u['code'], 'type' => $u['type'], 'is_active' => true,
                'city' => $u['cidade'], 'state' => 'SP', 'zip_code' => $u['cep'], 'cep' => $u['cep'],
                'latitude' => $u['lat'], 'longitude' => $u['lng'],
            ]);
        }

        foreach (array_keys(self::VIDA_UTIL) as $nome) {
            $this->categoriaIds[$nome] = $this->inserir('asset_categories', ['name' => $nome, 'slug' => Str::slug($nome), 'is_active' => true]);
        }
    }

    private function criarClientes(): void
    {
        foreach (self::CLIENTES as $i => $c) {
            $obra = collect(self::OBRAS)->firstWhere('cliente', $i);
            $this->clienteIds[$i] = $this->inserir('clients', [
                'name' => $c['name'], 'legal_name' => $c['name'], 'cpf_cnpj' => $c['doc'], 'document' => $c['doc'],
                'activity_type' => $i === 0 || $i === 3 ? 'construcao_civil' : 'industrial_hospitalar',
                'contact_name' => 'Contato '.($i + 1).' (fictício)', 'email' => 'contato'.($i + 1).'@cliente-demo.test',
                'phone' => '(19) 3000-00'.str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT),
                'city' => $c['cidade'], 'uf' => 'SP', 'state' => 'SP', 'cep' => $obra['cep'], 'zip_code' => $obra['cep'],
                'address' => $obra['endereco'], 'latitude' => $obra['lat'], 'longitude' => $obra['lng'],
            ]);
        }
    }

    private function criarAtivosContratosEOrdens(): array
    {
        $inicioJanela = $this->agora->copy()->subMonths(18)->startOfDay();
        $totalContratos = $totalOs = 0;
        $custos = [];

        foreach (self::ATIVOS as $idx => [$codigo, $categoria, $nome, $fabricante, $modelo, $ano, $valor, $status, $obraIdx, $problematico]) {
            $obraAtiva = $obraIdx !== null ? self::OBRAS[$obraIdx] : null;
            $maquina = in_array($categoria, ['Plataforma Elevatória', 'Gerador', 'Compressor'], true);
            $unidade = match ($status) {
                'manutencao' => 'matriz',
                'disponivel' => $idx % 2 === 0 ? 'matriz' : 'deposito',
                default => null,
            };
            $jitter = fn () => (mt_rand(-20, 20)) / 10000;

            $assetId = $this->inserir('assets', [
                'name' => $nome, 'tag' => 'DEMO-'.$codigo, 'patrimonio' => $codigo, 'serial_number' => 'SN-'.mt_rand(100000, 999999),
                'status' => $status, 'grupo' => 'maquina', 'fabricante' => $fabricante, 'modelo' => $modelo,
                'manufacturing_year' => $ano, 'acquisition_date' => Carbon::create($ano, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'acquisition_value' => $valor, 'residual_value' => round($valor * 0.1, 2), 'useful_life_years' => self::VIDA_UTIL[$categoria],
                'asset_category' => $categoria, 'asset_category_id' => $this->categoriaIds[$categoria],
                'internal_unit_id' => $unidade ? $this->unidadeIds[$unidade] : null,
                'client_id' => $obraAtiva ? $this->clienteIds[$obraAtiva['cliente']] : null,
                'latitude' => null, 'longitude' => null,
                'horimetro_inicial' => 0, 'last_horimetro' => $maquina ? mt_rand(900, 6200) : 0, 'horimetro_atual' => $maquina ? mt_rand(900, 6200) : 0,
                'description' => 'Equipamento fictício da Locadora Demo.',
            ]);

            $totalContratos += $this->criarContratos($assetId, $valor, $status, $obraIdx, $categoria, $inicioJanela, $jitter);
            [$n, $custo] = $this->criarOrdens($assetId, $categoria, $valor, $status, $problematico, $inicioJanela);
            $totalOs += $n;
            $custos[$codigo] = ['custo' => $custo, 'valor' => $valor, 'problematico' => $problematico !== null];
        }

        $problematicos = collect($custos)->where('problematico', true)->map(fn ($c) => round($c['custo'] / $c['valor'] * 100).'%')->all();

        return [
            'equipamentos' => count(self::ATIVOS), 'clientes' => count(self::CLIENTES), 'obras' => count(self::OBRAS),
            'contratos' => $totalContratos, 'ordens_de_servico' => $totalOs,
            'custo_total' => round(collect($custos)->sum('custo'), 2), 'problematicos' => $problematicos,
        ];
    }

    private function criarContratos(string $assetId, int $valor, string $status, ?int $obraAtivaIdx, string $categoria, Carbon $inicioJanela, \Closure $jitter): int
    {
        $taxa = match ($categoria) {
            'Ferramenta Elétrica' => 0.06, 'Andaime' => 0.05, default => 0.035,
        };
        $mensal = max(150, round($valor * $taxa / 10) * 10);
        $fim = $this->agora->copy()->subDays(20);
        $cursor = $inicioJanela->copy()->addDays(mt_rand(0, 40));
        $n = 0;
        $outrasObras = array_keys(self::OBRAS);

        while ($cursor->lt($fim)) {
            $dias = mt_rand(45, 150);
            $termino = $cursor->copy()->addDays($dias);
            $ultimo = $termino->gte($fim);
            $ativo = $ultimo && $status === 'locado' && $obraAtivaIdx !== null;
            $obraIdx = $ativo ? $obraAtivaIdx : $outrasObras[mt_rand(0, count($outrasObras) - 1)];

            if ($ultimo && ! $ativo) {
                $termino = $fim->copy()->subDays(mt_rand(0, 25));
                if ($termino->lte($cursor)) {
                    break;
                }
            }

            $this->inserirContrato($assetId, $obraIdx, $cursor, $ativo ? $this->agora->copy()->addDays(mt_rand(15, 120)) : $termino, $ativo, $mensal, $jitter);
            $n++;
            if ($ativo) {
                break;
            }
            $cursor = $termino->copy()->addDays(mt_rand(4, 20));
        }

        return $n;
    }

    private function inserirContrato(string $assetId, int $obraIdx, Carbon $inicio, Carbon $fim, bool $ativo, float $mensal, \Closure $jitter): void
    {
        $obra = self::OBRAS[$obraIdx];
        $meses = max(1, $inicio->diffInDays($fim) / 30);
        $this->seqContrato++;

        $this->inserir('contracts', [
            'client_id' => $this->clienteIds[$obra['cliente']], 'asset_id' => $assetId,
            'contract_number' => 'DEMO-CT-'.str_pad((string) $this->seqContrato, 4, '0', STR_PAD_LEFT),
            'status' => $ativo ? 'Ativo' : 'Encerrado', 'is_active' => $ativo,
            'start_date' => $inicio->toDateString(), 'end_date' => $fim->toDateString(), 'price' => round($mensal * $meses, 2),
            'billing_type' => 'mensal_fixo', 'local_tipo' => 'outro',
            'cep_obra' => $obra['cep'], 'endereco_obra' => $obra['endereco'].' ('.$obra['nome'].')', 'cidade_obra' => $obra['cidade'], 'uf_obra' => 'SP',
            'latitude_obra' => round($obra['lat'] + $jitter(), 7), 'longitude_obra' => round($obra['lng'] + $jitter(), 7),
            'observations' => 'Contrato fictício da Locadora Demo.',
        ]);
    }

    /** @return array{0: int, 1: float} [quantidade de OS, custo total] */
    private function criarOrdens(string $assetId, string $categoria, int $valor, string $status, ?array $problematico, Carbon $inicioJanela): array
    {
        $eventos = $problematico ? $this->eventosProblematicos($categoria, $problematico, $inicioJanela) : $this->eventosNormais($categoria, $valor, $inicioJanela);

        if ($problematico) {
            $this->escalarCustos($eventos, (float) $problematico['alvo']);
        } else {
            $soma = array_sum(array_map(fn ($e) => $e['labor'] + $e['material'] + $e['logistics'], $eventos));
            $teto = $valor * 0.20;
            if ($soma > $teto && $soma > 0) {
                $this->escalarCustos($eventos, round($teto, 2));
            }
        }

        $total = 0.0;
        foreach ($eventos as $e) {
            $total += $this->inserirOs($assetId, $e);
        }

        $abertas = 0;
        if ($status === 'manutencao') {
            $total += $this->inserirOsAberta($assetId, $categoria, $problematico !== null);
            $abertas = 1;
        }

        return [count($eventos) + $abertas, round($total, 2)];
    }

    private function eventosNormais(string $categoria, int $valor, Carbon $inicioJanela): array
    {
        $intervalo = match ($categoria) {
            'Ferramenta Elétrica' => 180, 'Andaime' => 120, default => 95,
        };
        $eventos = [];
        $cursor = $inicioJanela->copy()->addDays(mt_rand(5, 70));
        while ($cursor->lt($this->agora->copy()->subDays(8))) {
            $eventos[] = $this->novoEvento('Preventiva', $categoria, $valor, $cursor->copy(), false, false);
            $cursor->addDays($intervalo + mt_rand(-8, 8));
        }

        $maxCorretivas = in_array($categoria, ['Ferramenta Elétrica', 'Andaime'], true) ? 1 : 2;
        for ($i = 0, $n = mt_rand(0, $maxCorretivas); $i < $n; $i++) {
            $quando = $inicioJanela->copy()->addDays(mt_rand(30, (int) $inicioJanela->diffInDays($this->agora) - 15));
            $eventos[] = $this->novoEvento('Corretiva', $categoria, $valor, $quando, false, false);
        }

        return $eventos;
    }

    private function eventosProblematicos(string $categoria, array $p, Carbon $inicioJanela): array
    {
        $valorBase = 60000;
        $eventos = [];
        $dias = (int) $inicioJanela->diffInDays($this->agora) - 10;
        $n = (int) $p['corretivas'];

        // Corretivas cada vez mais frequentes: pontos concentrados no fim da janela.
        for ($i = 0; $i < $n; $i++) {
            $fracao = pow(($i + 0.5) / $n, 0.75);
            $quando = $inicioJanela->copy()->addDays((int) round($fracao * $dias));
            $emergencia = $i % 4 === 3;
            $eventos[] = $this->novoEvento($emergencia ? 'Emergência' : 'Corretiva', $categoria, $valorBase, $quando, $i >= $n - (int) $p['retrabalho'], $emergencia);
        }

        $cursor = $inicioJanela->copy()->addDays(mt_rand(10, 40));
        while ($cursor->lt($this->agora->copy()->subDays(8))) {
            $eventos[] = $this->novoEvento('Preventiva', $categoria, $valorBase, $cursor->copy(), false, false);
            $cursor->addDays(100 + mt_rand(-10, 10));
        }

        return $eventos;
    }

    private function novoEvento(string $tipo, string $categoria, int $valor, Carbon $quando, bool $retrabalho, bool $emergencia): array
    {
        $corretiva = $tipo !== 'Preventiva';
        $descricoes = $corretiva ? self::DESC_CORRETIVA[$categoria] : self::DESC_PREVENTIVA[$categoria];
        $fator = $corretiva ? mt_rand(8, 25) / 1000 : mt_rand(3, 8) / 1000;

        return [
            'tipo' => $tipo, 'quando' => $quando->setTime(mt_rand(7, 15), mt_rand(0, 59)), 'retrabalho' => $retrabalho,
            'descricao' => $descricoes[mt_rand(0, count($descricoes) - 1)],
            'horas' => $corretiva ? mt_rand(30, 120) / 10 : mt_rand(15, 45) / 10,
            'labor' => round(($corretiva ? mt_rand(250, 750) : mt_rand(120, 320)) * ($emergencia ? 1.4 : 1), 2),
            'material' => round(max(30, $valor * $fator * mt_rand(70, 130) / 100), 2),
            'logistics' => $corretiva && mt_rand(0, 2) === 0 ? mt_rand(80, 280) : 0,
        ];
    }

    /** Escala mao de obra, material e logistica proporcionalmente para o total bater exatamente com a meta. */
    private function escalarCustos(array &$eventos, float $alvo): void
    {
        $soma = array_sum(array_map(fn ($e) => $e['labor'] + $e['material'] + $e['logistics'], $eventos));
        if ($soma <= 0) {
            return;
        }
        $fator = $alvo / $soma;
        foreach ($eventos as &$e) {
            foreach (['labor', 'material', 'logistics'] as $k) {
                $e[$k] = round($e[$k] * $fator, 2);
            }
        }
        unset($e);

        // Ajuste de centavos na ultima OS para o total fechar exato.
        $diff = round($alvo - array_sum(array_map(fn ($e) => $e['labor'] + $e['material'] + $e['logistics'], $eventos)), 2);
        $ultimo = array_key_last($eventos);
        $eventos[$ultimo]['material'] = round($eventos[$ultimo]['material'] + $diff, 2);
    }

    private function inserirOs(string $assetId, array $e): float
    {
        $this->seqOs++;
        $total = round($e['labor'] + $e['material'] + $e['logistics'], 2);
        $inicio = $e['quando']->copy()->addHours(2);
        $fim = $inicio->copy()->addMinutes((int) round($e['horas'] * 60));

        $this->inserir('maintenance_orders', [
            'os_number' => 'DEMO-OS-'.str_pad((string) $this->seqOs, 5, '0', STR_PAD_LEFT),
            'asset_id' => $assetId, 'technician_id' => $this->tecnicos[mt_rand(0, count($this->tecnicos) - 1)],
            'maintenance_type' => $e['tipo'], 'description' => $e['descricao'], 'status' => 'Concluída', 'internal_status' => 'concluido',
            'started_at' => $inicio, 'finished_at' => $fim, 'hours_spent' => $e['horas'], 'total_time_seconds' => (int) round($e['horas'] * 3600),
            'labor_cost' => $e['labor'], 'material_cost' => $e['material'], 'logistics_cost' => $e['logistics'], 'total_order_cost' => $total,
            'is_rework' => $e['retrabalho'], 'technical_notes' => 'Ordem de serviço fictícia da Locadora Demo.',
        ], criadoEm: $e['quando']);

        return $total;
    }

    private function inserirOsAberta(string $assetId, string $categoria, bool $emergencia): float
    {
        $this->seqOs++;
        $quando = $this->agora->copy()->subDays(mt_rand(1, 4))->setTime(8, 30);
        $labor = (float) ($categoria === 'Ferramenta Elétrica' ? mt_rand(60, 180) : mt_rand(200, 600));

        $this->inserir('maintenance_orders', [
            'os_number' => 'DEMO-OS-'.str_pad((string) $this->seqOs, 5, '0', STR_PAD_LEFT),
            'asset_id' => $assetId, 'technician_id' => $this->tecnicos[0],
            'maintenance_type' => $emergencia ? 'Emergência' : 'Corretiva',
            'description' => self::DESC_CORRETIVA[$categoria][0].' (em andamento)', 'status' => 'Em Andamento', 'internal_status' => 'em_manutencao',
            'started_at' => $quando->copy()->addHour(), 'hours_spent' => 0, 'total_time_seconds' => 0,
            'labor_cost' => $labor, 'material_cost' => 0, 'logistics_cost' => 0, 'total_order_cost' => $labor,
            'is_rework' => false, 'technical_notes' => 'Ordem de serviço fictícia da Locadora Demo.',
        ], criadoEm: $quando);

        return $labor;
    }

    /** Insercao direta (sem eventos de modelo). Devolve o id uuid gerado. */
    private function inserir(string $tabela, array $dados, ?Carbon $criadoEm = null): string
    {
        $id = (string) Str::uuid();
        $agora = $criadoEm ?? $this->agora;

        DB::table($tabela)->insert(array_merge(['id' => $id, 'tenant_id' => $this->tenantId], $dados, ['created_at' => $agora, 'updated_at' => $agora]));

        return $id;
    }
}
