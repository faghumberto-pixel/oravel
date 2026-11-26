<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CrmFunilPadrao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dados FICTÍCIOS de CRM para a empresa de teste do pacote Vendas (referência de apresentação): vendedores, metas do mês, oportunidades em todas as
 * etapas do funil, propostas, visitas e perdas com motivo. Só ADICIONA: recusa rodar em empresa que já tem leads e nunca apaga nada além do que ele
 * mesmo criou (--remover, pela lista de ids em storage/app/demo-vendas-ids.json). Insere direto nas tabelas (sem observers: sem e-mail, notificação
 * nem log) e usa datas relativas a hoje, para o painel parecer vivo sempre. Sem Faker: roda em PROD.
 */
class DemoPopularVendas extends Command
{
    protected $signature = 'demo:popular-vendas {--tenant=TESTE Primeiro Acesso : nome da empresa} {--remover : Apaga só o que este comando criou}';

    protected $description = 'Popula a empresa de teste do Vendas com dados fictícios de CRM (só adiciona; reversível com --remover)';

    /** @var array<string, list<string>> */
    private array $criados = [];

    /** @var array<string, array<int, string>> */
    private array $colunas = [];

    private string $tid;

    /** Vendedores fictícios (nome, fração da meta já atingida no mês, meta de valor, de ganhos e de visitas). */
    private const VENDEDORES = [
        ['Carlos Mendes', 0.95, 120000, 6, 24],
        ['Fernanda Lima', 0.70, 90000, 5, 20],
        ['Rafael Souza', 0.45, 150000, 6, 22],
        ['Juliana Prado', 0.20, 80000, 4, 18],
    ];

    /** Cidades do interior de SP com coordenadas (para o mapa dos leads). */
    private const CIDADES = [
        ['Campinas', -22.9056, -47.0608], ['Sorocaba', -23.5015, -47.4526], ['Ribeirão Preto', -21.1775, -47.8103],
        ['Jundiaí', -23.1857, -46.8978], ['Piracicaba', -22.7253, -47.6492], ['São José dos Campos', -23.2237, -45.9009],
        ['Bauru', -22.3246, -49.0871], ['Limeira', -22.5649, -47.4017], ['Indaiatuba', -23.0903, -47.2181], ['Americana', -22.7391, -47.3314],
    ];

    private const EMPRESAS = [
        'Locadora Vale Verde', 'Munck e Guindastes Paulista', 'Plataformas do Interior', 'Elevar Equipamentos', 'RS Locação Pesada',
        'Torre Forte Locações', 'Máquinas Brasil Centro', 'Altura Segura Locadora', 'Geradores e Cia', 'Terra Firme Terraplenagem',
        'Construtora Horizonte', 'Metalúrgica São Jorge', 'Agro Campo Maquinário', 'Logística Rápida SP', 'Engenharia Pinheiro',
        'Usina Santa Clara', 'Obras e Montagens Lima', 'Pavimenta Sul', 'Indústria Nova Era', 'Condomínio Parque das Águas',
        'Mineração Rio Claro', 'Frota Prime Transportes', 'Estruturas Metálicas Ouro', 'Hidráulica Central', 'Serralheria Duarte',
        'Rodovias do Oeste', 'Armazéns Gerais Bauru', 'Cerâmica Santa Rita', 'Papel e Celulose Alfa', 'Montagens Industriais Beta',
        'Frigorífico Boa Carne', 'Açúcar e Álcool Doce Vale', 'Vidraçaria Cristal', 'Siderúrgica Aço Forte', 'Terminal Logístico Norte',
        'Construções Andrade', 'Metal Press Indústria', 'Locações Nova Fronteira', 'Obras Públicas Municipais', 'Reforma e Cia Engenharia',
    ];

    private const MOTIVOS_PERDA = ['Preço', 'Prazo de entrega', 'Fechou com concorrente', 'Sem orçamento no momento', 'Projeto adiado'];

    private const ORIGENS = ['indicacao', 'site', 'contato_frio', 'evento', 'outro'];

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
        if (DB::table('crm_leads')->where('tenant_id', $this->tid)->exists()) {
            $this->error('Esta empresa já tem leads: o comando só popula empresa vazia, para nunca misturar dado fictício com real.');

            return self::FAILURE;
        }

        mt_srand(2026);
        DB::transaction(function () {
            $vendedores = $this->vendedores();
            $this->metas($vendedores);
            $leads = $this->leads($vendedores);
            $this->propostas($leads, $vendedores);
            $this->visitas($leads, $vendedores);
            // Liga cada lead à etapa do funil padrão (cria o funil se a empresa ainda não tem), como o próprio CRM faz ao abrir.
            CrmFunilPadrao::garantir($this->tid);
        });

        File::put($this->arquivo(), json_encode($this->criados, JSON_PRETTY_PRINT));
        $this->info('Dados fictícios criados: '.collect($this->criados)->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');
        $this->line('Para desfazer: php artisan demo:popular-vendas --remover');

        return self::SUCCESS;
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-vendas-ids.json');
    }

    private function mk(string $tabela, array $linha): string
    {
        $this->colunas[$tabela] ??= Schema::getColumnListing($tabela);
        $id = (string) Str::uuid();
        $linha = $linha + ['id' => $id, 'tenant_id' => $this->tid, 'created_at' => now(), 'updated_at' => now()];
        DB::table($tabela)->insert(array_intersect_key($linha, array_flip($this->colunas[$tabela])));
        $this->criados[$tabela][] = $linha['id'];

        return $linha['id'];
    }

    /** @return list<array{id: string, nome: string, feito: float, meta: int, ganhos: int, visitas: int}> */
    private function vendedores(): array
    {
        $lista = [];
        foreach (self::VENDEDORES as $i => [$nome, $feito, $meta, $ganhos, $visitas]) {
            $id = $this->mk('users', [
                'name' => $nome, 'email' => 'vendedor'.($i + 1).'.'.substr($this->tid, 0, 8).'@vendas-demo.oravel.test', 'password' => Hash::make(Str::random(40)),
                'is_approved' => true, 'email_verified_at' => now(), 'two_factor_exempt' => true,
            ]);
            $lista[] = ['id' => $id, 'nome' => $nome, 'feito' => $feito, 'meta' => $meta, 'ganhos' => $ganhos, 'visitas' => $visitas];
        }

        return $lista;
    }

    private function metas(array $vendedores): void
    {
        foreach ([now()->startOfMonth(), now()->subMonth()->startOfMonth()] as $mes) {
            foreach ($vendedores as $v) {
                $this->mk('crm_metas', ['user_id' => $v['id'], 'mes' => $mes->toDateString(), 'meta_valor' => $v['meta'], 'meta_ganhos' => $v['ganhos'], 'meta_visitas' => $v['visitas']]);
            }
        }
    }

    /** @return list<array{id: string, vendedor: string, stage: string, valor: float, empresa: string}> */
    private function leads(array $vendedores): array
    {
        $leads = [];
        $n = 0;
        $criar = function (string $stage, array $extra) use (&$leads, &$n, $vendedores) {
            $v = $extra['vendedor'] ?? $vendedores[$n % count($vendedores)];
            unset($extra['vendedor']);
            $cidade = self::CIDADES[$n % count(self::CIDADES)];
            $empresa = self::EMPRESAS[$n % count(self::EMPRESAS)];
            $valor = (float) ($extra['valor'] ?? (15000 + mt_rand(0, 85) * 1000));
            unset($extra['valor']);
            $criado = $extra['criado'] ?? now()->subDays(mt_rand(5, 60));
            unset($extra['criado']);

            $id = $this->mk('crm_leads', $extra + [
                'name' => 'Contato '.$empresa, 'company_name' => $empresa, 'stage' => $stage, 'source' => self::ORIGENS[$n % count(self::ORIGENS)],
                'estimated_value' => $valor, 'assigned_user_id' => $v['id'], 'city' => $cidade[0], 'state' => 'SP', 'latitude' => $cidade[1] + mt_rand(-40, 40) / 1000,
                'longitude' => $cidade[2] + mt_rand(-40, 40) / 1000, 'created_at' => $criado, 'updated_at' => $extra['updated_at'] ?? now()->subDays(mt_rand(0, 9)),
            ]);
            $leads[] = ['id' => $id, 'vendedor' => $v['id'], 'stage' => $stage, 'valor' => $valor, 'empresa' => $empresa];
            $n++;
        };

        // Fechamentos do mês: cada vendedor fecha a fração da meta definida acima (em 1 a 2 vendas).
        foreach ($vendedores as $v) {
            $alvo = $v['meta'] * $v['feito'];
            $vendas = $v['feito'] > 0.6 ? 2 : 1;
            for ($i = 0; $i < $vendas; $i++) {
                $valor = round($alvo / $vendas, 2);
                $fechado = now()->subDays(mt_rand(0, max(0, now()->day - 1)));
                $criar('convertido', ['vendedor' => $v, 'valor' => $valor, 'valor_fechado' => $valor, 'fechado_em' => $fechado, 'vendedor_fechamento_id' => $v['id'],
                    'criado' => $fechado->copy()->subDays(mt_rand(12, 40)), 'updated_at' => $fechado]);
            }
        }
        // Fechamentos dos meses anteriores (alimentam ciclo de venda, ranking de 90 dias e origem por mês).
        for ($i = 0; $i < 10; $i++) {
            $v = $vendedores[$i % count($vendedores)];
            $valor = (float) (20000 + mt_rand(0, 60) * 1000);
            $fechado = now()->subDays(mt_rand(35, 85));
            $criar('convertido', ['vendedor' => $v, 'valor' => $valor, 'valor_fechado' => $valor, 'fechado_em' => $fechado, 'vendedor_fechamento_id' => $v['id'],
                'criado' => $fechado->copy()->subDays(mt_rand(10, 45)), 'updated_at' => $fechado]);
        }
        // Perdas com motivo.
        for ($i = 0; $i < 9; $i++) {
            $motivo = self::MOTIVOS_PERDA[$i % count(self::MOTIVOS_PERDA)];
            $fechado = now()->subDays(mt_rand(3, 80));
            $criar('perdido', ['lost_reason_category' => $motivo, 'lost_reason' => $motivo, 'fechado_em' => $fechado, 'criado' => $fechado->copy()->subDays(mt_rand(10, 35)), 'updated_at' => $fechado]);
        }
        // Oportunidades abertas: novas, em contato e qualificadas (algumas paradas há semanas, com previsão de fechamento).
        foreach (['novo' => 8, 'contato_iniciado' => 8, 'qualificado' => 9] as $stage => $total) {
            for ($i = 0; $i < $total; $i++) {
                $parado = $i % 4 === 0;
                $criar($stage, [
                    'previsao_fechamento' => now()->addDays(mt_rand(3, 45))->toDateString(),
                    // Retornos agendados: alguns atrasados, um hoje e vários nos próximos dias (alimenta "Próximos compromissos").
                    'next_followup_date' => $i % 3 === 0 ? now()->addDays(mt_rand(-5, 12))->toDateString() : null,
                    'updated_at' => $parado ? now()->subDays(mt_rand(16, 40)) : now()->subDays(mt_rand(0, 9)),
                    'criado' => $parado ? now()->subDays(mt_rand(45, 70)) : now()->subDays(mt_rand(2, 40)),
                ]);
            }
        }

        return $leads;
    }

    private function propostas(array $leads, array $vendedores): void
    {
        $numero = 1;
        $situacoes = ['enviada', 'enviada', 'enviada', 'enviada', 'enviada', 'aceita', 'aceita', 'aceita', 'aceita', 'recusada', 'recusada', 'expirada', 'rascunho'];
        foreach ($situacoes as $i => $status) {
            $lead = $leads[($i * 3) % count($leads)];
            $criada = now()->subDays(mt_rand(2, 60));
            $this->mk('crm_propostas', [
                'numero' => $numero++, 'crm_lead_id' => $lead['id'], 'vendedor_user_id' => $lead['vendedor'], 'titulo' => 'Locação de equipamentos — '.$lead['empresa'],
                'validade_ate' => $status === 'enviada' ? now()->addDays($i < 2 ? 3 + $i : 15 + $i)->toDateString() : now()->subDays(mt_rand(1, 20))->toDateString(),
                'total' => round($lead['valor'] * 0.9, 2), 'status' => $status, 'token' => Str::random(40),
                'enviada_em' => $status === 'rascunho' ? null : $criada, 'respondida_em' => in_array($status, ['aceita', 'recusada'], true) ? $criada->copy()->addDays(3) : null,
                'motivo_recusa' => $status === 'recusada' ? 'Preço acima do orçado' : null, 'created_at' => $criada, 'updated_at' => $criada,
            ]);
        }
    }

    private function visitas(array $leads, array $vendedores): void
    {
        // Realizadas: espalhadas pelo mês (mais para vendedores com meta mais avançada) e pelos 30 dias anteriores.
        foreach ($vendedores as $vi => $v) {
            $noMes = (int) round($v['visitas'] * (0.4 + $v['feito'] * 0.5));
            for ($i = 0; $i < $noMes; $i++) {
                $this->visita($leads, $v, now()->subDays(mt_rand(0, max(0, now()->day - 1)))->setTime(mt_rand(8, 16), 0), 'realizada');
            }
            for ($i = 0; $i < 4; $i++) {
                $this->visita($leads, $v, now()->subDays(mt_rand(31, 55))->setTime(mt_rand(8, 16), 0), 'realizada');
            }
            // Agendadas à frente.
            for ($i = 0; $i < 2; $i++) {
                $this->visita($leads, $v, now()->addDays(mt_rand(1, 10))->setTime(mt_rand(8, 16), 0), 'agendada');
            }
        }
    }

    private function visita(array $leads, array $v, $quando, string $status): void
    {
        $lead = $leads[mt_rand(0, count($leads) - 1)];
        $cidade = self::CIDADES[mt_rand(0, count(self::CIDADES) - 1)];
        $this->mk('crm_visitas', [
            'user_id' => $v['id'], 'crm_lead_id' => $lead['id'], 'contato_nome' => 'Responsável — '.$lead['empresa'], 'objetivo' => 'apresentacao',
            'agendada_para' => $quando, 'status' => $status, 'cidade' => $cidade[0], 'uf' => 'SP', 'latitude' => $cidade[1], 'longitude' => $cidade[2],
            'resultado' => $status === 'realizada' ? 'Apresentação feita; cliente pediu proposta.' : null,
            'checkin_em' => $status === 'realizada' ? $quando : null, 'checkout_em' => $status === 'realizada' ? $quando->copy()->addHour() : null,
        ]);
    }

    private function remover(): int
    {
        if (! File::exists($this->arquivo())) {
            $this->warn('Não há lista de ids: nada foi criado por este comando (ou já foi removido).');

            return self::SUCCESS;
        }

        $criados = json_decode(File::get($this->arquivo()), true) ?: [];
        DB::transaction(function () use ($criados) {
            // Filhos antes dos pais (visitas e propostas apontam para leads; tudo aponta para os vendedores).
            foreach (['crm_visitas', 'crm_propostas', 'crm_leads', 'crm_metas', 'users'] as $tabela) {
                if (! empty($criados[$tabela])) {
                    DB::table($tabela)->where('tenant_id', $this->tid)->whereIn('id', $criados[$tabela])->delete();
                }
            }
        });
        File::delete($this->arquivo());
        $this->info('Dados fictícios removidos: '.collect($criados)->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');

        return self::SUCCESS;
    }
}
