<?php

namespace App\Services\Demo;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Popula o COMERCIAL da Locadora Demo (so' ela): mais clientes (carteira), leads do CRM no funil, orcamentos,
 * propostas comerciais, solicitacoes de locacao e Voz do Cliente, com no minimo 30 registros de cada. Idempotente:
 * apaga o que este servico criou antes (nas tabelas comerciais do tenant demo) e cria de novo, de forma deterministica.
 * Insere direto nas tabelas (sem observers: nao manda e-mail nem notificacao).
 */
class LocadoraDemoComercial
{
    private const SEMENTE = 20261110;

    /** Prefixo do CNPJ ficticio dos clientes extras: serve para achar e apagar so' estes na recriacao. */
    private const CNPJ_EXTRA = '22.222.222/';

    private string $tenantId;

    private Carbon $agora;

    /** @var list<string> */
    private array $vendedores = [];

    /** @var list<string> */
    private array $clientes = [];

    /** @var array<string, string> slug => id */
    private array $etapas = [];

    private const PREFIXOS = ['Construtora', 'Engenharia', 'Metalúrgica', 'Transportes', 'Incorporadora', 'Montagens', 'Locações', 'Terraplenagem', 'Eventos', 'Indústria', 'Agro', 'Logística'];

    private const SUFIXOS = ['Alvorada', 'Santa Clara', 'Vale Verde', 'Nova Era', 'Horizonte Azul', 'Pioneira', 'Boa Vista', 'Três Pontes', 'Estrela do Sul', 'Monte Alto', 'Rio Claro', 'Serra Dourada',
        'Aurora', 'Bandeirantes', 'Cedro', 'Delta', 'Ipê', 'Jatobá', 'Lagoa Bela', 'Mirante', 'Oásis', 'Paineira', 'Quartzo', 'Real', 'Safira', 'Tupã', 'União', 'Vitória', 'Zênite', 'Acácia', 'Brasa',
        'Cristal', 'Diamante', 'Esmeralda', 'Fênix', 'Granito'];

    private const NOMES = ['Carlos', 'Mariana', 'Roberto', 'Patrícia', 'Anderson', 'Luciana', 'Eduardo', 'Camila', 'Fábio', 'Renata', 'Marcelo', 'Vanessa', 'Rodrigo', 'Aline', 'Gustavo', 'Tatiane', 'Leandro', 'Simone'];

    private const SOBRENOMES = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Ferreira', 'Almeida', 'Ribeiro', 'Carvalho', 'Gomes', 'Martins', 'Araújo', 'Rocha', 'Dias', 'Nogueira'];

    /** Cidades (nome, lat, lng) para leads e clientes. */
    private const CIDADES = [
        ['Campinas', -22.9056, -47.0608], ['São Paulo', -23.5614, -46.6559], ['Sorocaba', -23.5015, -47.4526], ['Jundiaí', -23.1857, -46.8978],
        ['Ribeirão Preto', -21.1775, -47.8103], ['Santos', -23.9618, -46.3322], ['Piracicaba', -22.7253, -47.6492], ['Americana', -22.7374, -47.3331],
        ['Limeira', -22.5641, -47.4017], ['Indaiatuba', -23.0922, -47.2181], ['Atibaia', -23.1171, -46.5563], ['São Carlos', -22.0175, -47.8908],
        ['Bauru', -22.3246, -49.0871], ['Mogi das Cruzes', -23.5208, -46.1854], ['Paulínia', -22.7611, -47.1544], ['Itu', -23.2642, -47.2992],
    ];

    private const SEGMENTOS = ['construcao_civil', 'industrial_hospitalar', 'eventos', 'locacao_equipamentos', 'outro'];

    private const INTERESSES = [
        'Plataforma elevatória tesoura 10m por 60 dias', 'Gerador 150 kVA para canteiro de obras', 'Compressor de ar para jateamento', 'Andaime fachadeiro para reforma de prédio',
        'Plataforma articulada 15m para manutenção de galpão', 'Kit de ferramentas elétricas para obra', 'Gerador 40 kVA para evento', 'Torre de iluminação para obra noturna',
        'Betoneira e vibradores para concretagem', 'Cortadora de piso para obra viária',
    ];

    private const SERVICOS = [
        'Reparo do sistema hidráulico', 'Troca de baterias', 'Revisão preventiva 250h', 'Substituição de mangueiras e conexões', 'Manutenção do painel de comando',
        'Recuperação de motor de arranque', 'Troca de óleo e filtros', 'Reparo de dano na devolução', 'Calibração e teste de carga', 'Troca de pneus e rodízios',
    ];

    public function popular(Tenant $tenant): array
    {
        if (! $tenant->is_demo) {
            throw new \RuntimeException('O comercial só é populado na empresa de demonstração.');
        }

        $this->tenantId = (string) $tenant->id;
        $this->agora = Carbon::now();
        mt_srand(self::SEMENTE);

        try {
            return DB::transaction(function () {
                $this->limpar();
                $this->etapas = DB::table('crm_etapas')->where('tenant_id', $this->tenantId)->pluck('id', 'slug')->all();
                $this->criarVendedores();
                $this->criarClientesExtras();

                return [
                    'vendedores' => count($this->vendedores),
                    'clientes' => DB::table('clients')->where('tenant_id', $this->tenantId)->count(),
                    'leads' => $this->criarLeads(),
                    'orcamentos' => $this->criarOrcamentos(),
                    'propostas' => $this->criarPropostas(),
                    'solicitacoes' => $this->criarSolicitacoes(),
                    'voz_do_cliente' => $this->criarFeedbacks(),
                ];
            });
        } finally {
            mt_srand();
        }
    }

    /** Apaga o que este servico criou (so' tabelas comerciais do tenant demo e os clientes extras). */
    private function limpar(): void
    {
        $t = $this->tenantId;
        $propostas = DB::table('proposta_comerciais')->where('tenant_id', $t)->pluck('id');
        $orcamentos = DB::table('quotes')->where('tenant_id', $t)->pluck('id');
        $solicitacoes = DB::table('solicitacoes_locacao')->where('tenant_id', $t)->pluck('id');

        DB::table('proposta_comercial_itens')->whereIn('proposta_comercial_id', $propostas)->delete();
        DB::table('proposta_comercial_interactions')->whereIn('proposta_comercial_id', $propostas)->delete();
        DB::table('proposta_comerciais')->where('tenant_id', $t)->delete();
        DB::table('quote_items')->whereIn('quote_id', $orcamentos)->delete();
        DB::table('quotes')->where('tenant_id', $t)->delete();
        DB::table('solicitacao_locacao_assets')->whereIn('solicitacao_locacao_id', $solicitacoes)->delete();
        DB::table('solicitacoes_locacao')->where('tenant_id', $t)->delete();
        DB::table('client_feedbacks')->where('tenant_id', $t)->delete();
        DB::table('crm_lead_interactions')->whereIn('crm_lead_id', DB::table('crm_leads')->where('tenant_id', $t)->pluck('id'))->delete();
        DB::table('crm_leads')->where('tenant_id', $t)->delete();
        DB::table('clients')->where('tenant_id', $t)->where('cpf_cnpj', 'like', self::CNPJ_EXTRA.'%')->delete();
    }

    private function criarVendedores(): void
    {
        foreach (['Fernanda Lopes', 'Ricardo Menezes', 'Juliana Prado'] as $i => $nome) {
            $email = 'vendedor'.($i + 1).'@locadorademo.test';
            $u = User::withoutGlobalScopes()->where('email', $email)->first();
            if (! $u) {
                $u = User::create(['name' => $nome.' (comercial)', 'email' => $email, 'password' => Str::random(24), 'tenant_id' => $this->tenantId, 'role' => 'colaborador', 'hourly_rate' => 0]);
                $u->forceFill(['is_approved' => true, 'email_verified_at' => now()])->save();
            }
            $this->vendedores[] = (string) $u->id;
        }
    }

    private function criarClientesExtras(): void
    {
        $existentes = DB::table('clients')->where('tenant_id', $this->tenantId)->pluck('id')->map(fn ($v) => (string) $v)->all();

        for ($i = 0; $i < 24; $i++) {
            [$cidade, $lat, $lng] = self::CIDADES[$i % count(self::CIDADES)];
            $nome = self::PREFIXOS[($i + 3) % count(self::PREFIXOS)].' '.self::SUFIXOS[$i + 12].($i % 4 === 0 ? ' Ltda' : '');
            $id = $this->inserir('clients', [
                'name' => $nome, 'legal_name' => $nome, 'cpf_cnpj' => self::CNPJ_EXTRA.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT).'-'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'document' => self::CNPJ_EXTRA.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT).'-'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'activity_type' => self::SEGMENTOS[$i % 4], 'contact_name' => $this->pessoa(), 'email' => 'comercial'.($i + 20).'@cliente-demo.test',
                'phone' => '(19) 3'.mt_rand(100, 999).'-'.mt_rand(1000, 9999), 'city' => $cidade, 'uf' => 'SP', 'state' => 'SP',
                'latitude' => round($lat + mt_rand(-30, 30) / 1000, 6), 'longitude' => round($lng + mt_rand(-30, 30) / 1000, 6),
            ], $this->agora->copy()->subDays(mt_rand(5, 200)));
            $this->clientes[] = $id;
        }
        $this->clientes = array_merge($existentes, $this->clientes);
    }

    private function criarLeads(): int
    {
        // 36 leads: etapa => quantidade. Os convertidos viram clientes; os perdidos tem motivo.
        $etapas = array_merge(array_fill(0, 8, 'novo'), array_fill(0, 8, 'contato_iniciado'), array_fill(0, 9, 'qualificado'), array_fill(0, 7, 'convertido'), array_fill(0, 4, 'perdido'));
        shuffle($etapas);
        $origens = ['indicacao', 'site', 'contato_frio', 'evento', 'outro'];
        $pesosOrigem = ['indicacao', 'indicacao', 'site', 'site', 'site', 'contato_frio', 'contato_frio', 'evento', 'outro'];
        $perdas = [['preco', null], ['concorrencia', 'Fechou com a concorrência por prazo de entrega'], ['sem_necessidade', null], ['desistencia', null]];
        $n = 0;

        foreach ($etapas as $i => $etapa) {
            [$cidade, $lat, $lng] = self::CIDADES[($i * 5 + 1) % count(self::CIDADES)];
            $criado = $this->agora->copy()->subDays(mt_rand(3, 175))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $aberta = ! in_array($etapa, ['convertido', 'perdido'], true);
            $valor = mt_rand(8, 180) * 1000;
            $vendedor = $this->vendedores[mt_rand(0, count($this->vendedores) - 1)];
            $perda = $etapa === 'perdido' ? $perdas[$n % count($perdas)] : null;

            $this->inserir('crm_leads', [
                'name' => $this->pessoa(), 'company_name' => self::PREFIXOS[$i % count(self::PREFIXOS)].' '.self::SUFIXOS[$i % count(self::SUFIXOS)],
                'phone' => '(19) 9'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999), 'whatsapp' => '(19) 9'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999),
                'email' => 'lead'.($i + 1).'@prospect-demo.test', 'source' => $pesosOrigem[mt_rand(0, count($pesosOrigem) - 1)], 'stage' => $etapa, 'etapa_id' => $this->etapas[$etapa] ?? null,
                'assigned_user_id' => $vendedor, 'estimated_value' => $valor, 'client_id' => $etapa === 'convertido' ? $this->clientes[mt_rand(8, count($this->clientes) - 1)] : null,
                'city' => $cidade, 'uf' => 'SP', 'latitude' => round($lat + mt_rand(-25, 25) / 1000, 6), 'longitude' => round($lng + mt_rand(-25, 25) / 1000, 6),
                'last_contacted_at' => $etapa === 'novo' ? null : $criado->copy()->addDays(mt_rand(1, 12)),
                'next_followup_date' => $aberta ? $this->agora->copy()->addDays(mt_rand(-6, 14))->toDateString() : null,
                'segment' => self::SEGMENTOS[$i % 5], 'equipment_interest' => self::INTERESSES[$i % count(self::INTERESSES)],
                'previsao_fechamento' => $aberta ? $this->agora->copy()->addDays(mt_rand(7, 75))->toDateString() : null,
                'lost_reason' => $perda[1] ?? null, 'lost_reason_category' => $perda[0] ?? null,
                'fechado_em' => $etapa === 'convertido' ? $criado->copy()->addDays(mt_rand(8, 40)) : null,
                'valor_fechado' => $etapa === 'convertido' ? round($valor * mt_rand(80, 100) / 100, 2) : null,
                'vendedor_fechamento_id' => $etapa === 'convertido' ? $vendedor : null,
                'won_notes' => $etapa === 'convertido' ? 'Contrato fechado após visita técnica e proposta.' : null,
                'custom' => json_encode([]),
            ], $criado);
            $n++;
        }

        return $n;
    }

    private function criarOrcamentos(): int
    {
        $status = array_merge(array_fill(0, 5, 'rascunho'), array_fill(0, 9, 'enviado'), array_fill(0, 8, 'aprovado'), array_fill(0, 5, 'reprovado'), array_fill(0, 5, 'concluido'));
        shuffle($status);
        $tipos = ['interno', 'interno', 'interno', 'terceiro', 'terceiro', 'indenizatorio'];

        foreach ($status as $i => $st) {
            $criado = $this->agora->copy()->subDays(mt_rand(2, 170))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $enviado = $st === 'rascunho' ? null : $criado->copy()->addDays(mt_rand(0, 3));
            $itens = [];
            for ($k = 0, $m = mt_rand(1, 3); $k < $m; $k++) {
                $qtd = mt_rand(1, 6);
                $preco = mt_rand(18, 320) * 10;
                $itens[] = [self::SERVICOS[mt_rand(0, count(self::SERVICOS) - 1)], $qtd, $preco];
            }
            $total = array_sum(array_map(fn ($it) => $it[1] * $it[2], $itens));

            $id = $this->inserir('quotes', [
                'client_id' => $this->clientes[mt_rand(0, count($this->clientes) - 1)], 'assigned_user_id' => $this->vendedores[mt_rand(0, 2)],
                'type' => $tipos[mt_rand(0, count($tipos) - 1)], 'status' => $st, 'technical_report' => 'Laudo técnico: '.mb_strtolower($itens[0][0]).' conforme vistoria no equipamento.',
                'total_value' => $total, 'sent_at' => $enviado,
                'client_viewed_at' => in_array($st, ['aprovado', 'reprovado', 'concluido'], true) ? $enviado->copy()->addDays(1) : null,
                'client_responded_at' => in_array($st, ['aprovado', 'reprovado', 'concluido'], true) ? $enviado->copy()->addDays(mt_rand(1, 6)) : null,
                'rejection_reason' => $st === 'reprovado' ? 'Valor acima do orçamento do cliente.' : null,
                'completed_at' => $st === 'concluido' ? $enviado->copy()->addDays(mt_rand(7, 20)) : null,
            ], $criado);

            foreach ($itens as [$descricao, $qtd, $preco]) {
                $this->inserir('quote_items', ['quote_id' => $id, 'description' => $descricao, 'quantity' => $qtd, 'unit_price' => $preco, 'subtotal' => $qtd * $preco], $criado);
            }
        }

        return count($status);
    }

    private function criarPropostas(): int
    {
        $status = array_merge(array_fill(0, 5, 'rascunho'), array_fill(0, 6, 'enviada_para_comercial'), array_fill(0, 8, 'aprovada_interna'), array_fill(0, 8, 'aceita_pelo_cliente'),
            array_fill(0, 3, 'recusada_pelo_cliente'), array_fill(0, 2, 'rejeitada'));
        shuffle($status);
        $categorias = DB::table('asset_categories')->where('tenant_id', $this->tenantId)->pluck('name', 'id')->all();
        $leads = DB::table('crm_leads')->where('tenant_id', $this->tenantId)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $periodos = ['mensal' => 'mês', 'diaria' => 'dia', 'semanal' => 'semana'];

        foreach ($status as $i => $st) {
            $criado = $this->agora->copy()->subDays(mt_rand(2, 150))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $enviada = $st === 'rascunho' ? null : $criado->copy()->addDays(mt_rand(0, 3));
            $itens = [];
            for ($k = 0, $m = mt_rand(1, 3); $k < $m; $k++) {
                $catId = array_keys($categorias)[mt_rand(0, count($categorias) - 1)];
                $periodo = array_keys($periodos)[mt_rand(0, 2)];
                $qtd = mt_rand(1, 4);
                $preco = $periodo === 'mensal' ? mt_rand(18, 90) * 100 : ($periodo === 'semanal' ? mt_rand(6, 25) * 100 : mt_rand(2, 9) * 100);
                $itens[] = [$catId, 'Locação de '.mb_strtolower($categorias[$catId]).' ('.$periodos[$periodo].')', $qtd, $preco, $periodo];
            }
            $total = array_sum(array_map(fn ($it) => $it[2] * $it[3], $itens));
            $respondeu = in_array($st, ['aceita_pelo_cliente', 'recusada_pelo_cliente'], true);

            $id = $this->inserir('proposta_comerciais', [
                'crm_lead_id' => $leads ? $leads[mt_rand(0, count($leads) - 1)] : null, 'client_id' => $this->clientes[mt_rand(0, count($this->clientes) - 1)],
                'seller_user_id' => $this->vendedores[mt_rand(0, 2)], 'status' => $st, 'valid_until' => $criado->copy()->addDays(30)->toDateString(),
                'terms' => 'Proposta válida por 30 dias. Frete e montagem conforme combinado; manutenção preventiva inclusa.', 'total_value' => $total, 'sent_at' => $enviada,
                'reviewed_at' => in_array($st, ['aprovada_interna', 'aceita_pelo_cliente', 'recusada_pelo_cliente', 'rejeitada'], true) ? $criado->copy()->addDays(1) : null,
                'rejection_reason' => $st === 'rejeitada' ? 'Margem abaixo do mínimo: rever valores.' : ($st === 'recusada_pelo_cliente' ? 'Cliente optou por outra locadora.' : null),
                'client_responded_at' => $respondeu ? $enviada->copy()->addDays(mt_rand(1, 8)) : null,
                'cabecalho' => 'Proposta comercial de locação de equipamentos.',
            ], $criado);

            foreach ($itens as [$catId, $descricao, $qtd, $preco, $periodo]) {
                $this->inserir('proposta_comercial_itens', [
                    'proposta_comercial_id' => $id, 'asset_category_id' => $catId, 'type' => 'locacao', 'description' => $descricao, 'quantity' => $qtd,
                    'unit_price' => $preco, 'unit_period' => $periodo, 'subtotal' => $qtd * $preco,
                ], $criado);
            }
        }

        return count($status);
    }

    private function criarSolicitacoes(): int
    {
        $status = array_merge(array_fill(0, 12, 'proposta_em_andamento'), array_fill(0, 12, 'contrato_fechado'), array_fill(0, 2, 'reserva_manutencao'), array_fill(0, 4, 'cancelado'));
        shuffle($status);
        $categorias = DB::table('asset_categories')->where('tenant_id', $this->tenantId)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $ativos = DB::table('assets')->where('tenant_id', $this->tenantId)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $finalidades = ['Obra residencial', 'Manutenção de fachada', 'Montagem de evento', 'Ampliação industrial', 'Parada de manutenção', 'Obra viária', 'Reforma de galpão'];

        foreach ($status as $i => $st) {
            $cliente = $this->clientes[mt_rand(0, count($this->clientes) - 1)];
            $criado = $this->agora->copy()->subDays(mt_rand(1, 120))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $contrato = $st === 'contrato_fechado' ? DB::table('contracts')->where('tenant_id', $this->tenantId)->where('client_id', $cliente)->value('id') : null;

            $this->inserir('solicitacoes_locacao', [
                'user_id' => $this->vendedores[mt_rand(0, 2)], 'customer_id' => $cliente, 'contract_id' => $contrato,
                'category_id' => $categorias[mt_rand(0, count($categorias) - 1)], 'asset_id' => $ativos[mt_rand(0, count($ativos) - 1)],
                'purpose' => $finalidades[mt_rand(0, count($finalidades) - 1)], 'data_saida_prevista' => $criado->copy()->addDays(mt_rand(3, 30))->toDateString(),
                'status_comercial' => $st, 'observations' => $st === 'cancelado' ? 'Cliente adiou o início da obra.' : 'Pedido registrado pelo comercial.',
            ], $criado);
        }

        return count($status);
    }

    private function criarFeedbacks(): int
    {
        $itens = [
            ['elogio', 'Atendimento excelente', 'A equipe entregou o equipamento no prazo e o técnico explicou tudo. Parabéns!'],
            ['elogio', 'Equipamento em ótimo estado', 'A plataforma chegou limpa e revisada, sem nenhum problema durante a obra.'],
            ['elogio', 'Manutenção rápida', 'Tive um problema no gerador e a troca foi feita em menos de 3 horas.'],
            ['reclamacao', 'Atraso na entrega', 'O equipamento chegou um dia depois do combinado e atrasou nossa equipe.'],
            ['reclamacao', 'Cobrança de horas a mais', 'Veio cobrança de horímetro acima do contratado. Preciso entender o cálculo.'],
            ['reclamacao', 'Equipamento com ruído', 'O compressor chegou com ruído anormal e precisou ser trocado.'],
            ['sugestao', 'Aviso de vencimento do contrato', 'Seria bom receber um aviso antes do fim da locação para programar a renovação.'],
            ['sugestao', 'Pagamento por Pix', 'Gostaria de poder pagar as faturas por Pix direto no portal.'],
            ['sugestao', 'Fotos na devolução', 'Sugiro enviar fotos do estado do equipamento na retirada e na devolução.'],
        ];
        $status = ['recebido', 'em_analise', 'concluido', 'concluido', 'concluido'];
        $usados = [];

        for ($i = 0; $i < 32; $i++) {
            [$tipo, $assunto, $texto] = $itens[$i % count($itens)];
            $criado = $this->agora->copy()->subDays(mt_rand(1, 160))->setTime(mt_rand(8, 18), mt_rand(0, 59));
            $st = $status[mt_rand(0, count($status) - 1)];
            do {
                $protocolo = 'FB-'.$criado->format('ymd').'-'.Str::upper(Str::random(4));
            } while (isset($usados[$protocolo]));
            $usados[$protocolo] = true;

            $this->inserir('client_feedbacks', [
                'client_id' => $this->clientes[mt_rand(0, count($this->clientes) - 1)], 'protocol' => $protocolo, 'type' => $tipo, 'subject' => $assunto, 'description' => $texto,
                'status' => $st, 'response' => $st === 'concluido' ? 'Obrigado pelo contato! Já registramos e tratamos o assunto com a equipe responsável.' : null,
                'responded_by' => $st === 'concluido' ? $this->vendedores[0] : null, 'responded_at' => $st === 'concluido' ? $criado->copy()->addDays(mt_rand(1, 4)) : null,
            ], $criado);
        }

        return 32;
    }

    private function pessoa(): string
    {
        return self::NOMES[mt_rand(0, count(self::NOMES) - 1)].' '.self::SOBRENOMES[mt_rand(0, count(self::SOBRENOMES) - 1)];
    }

    /** Insercao direta; devolve o id uuid. */
    private function inserir(string $tabela, array $dados, ?Carbon $criadoEm = null): string
    {
        $id = (string) Str::uuid();
        $quando = $criadoEm ?? $this->agora;

        DB::table($tabela)->insert(array_merge(['id' => $id], in_array($tabela, ['quote_items', 'proposta_comercial_itens'], true) ? ['tenant_id' => $this->tenantId] : ['tenant_id' => $this->tenantId], $dados, ['created_at' => $quando, 'updated_at' => $quando]));

        return $id;
    }
}
