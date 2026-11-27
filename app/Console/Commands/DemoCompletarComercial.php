<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Popula a "Gestão Comercial" da empresa de teste do Vendas (depois de demo:completar-vendas): Clientes (um para cada conta do CRM), Orçamentos
 * com itens, Propostas Comerciais com itens e Contratos de prestação de serviço. Dados fictícios, com datas relativas a hoje. Só ADICIONA:
 * anota tudo em storage/app/demo-comercial-ids.json e --remover desfaz exatamente isso (inclusive a ligação conta do CRM => cliente).
 * Insere direto nas tabelas (sem observers: sem e-mail, notificação nem log). Sem Faker: roda em PROD.
 */
class DemoCompletarComercial extends Command
{
    protected $signature = 'demo:completar-comercial {--tenant=TESTE Primeiro Acesso : nome da empresa} {--remover : Desfaz o que este comando fez}';

    protected $description = 'Popula Clientes, Orçamentos, Propostas Comerciais e Contratos da empresa de teste do Vendas. Só adiciona; reversível com --remover';

    /** descrição, tipo (peca|servico), preço unitário */
    private const ITENS_ORCAMENTO = [
        ['Mão de obra técnica (hora)', 'servico', 180.00], ['Visita técnica e diagnóstico', 'servico', 350.00], ['Troca de óleo e filtros', 'servico', 420.00],
        ['Kit de vedação hidráulica', 'peca', 890.00], ['Mangueira hidráulica 3/4"', 'peca', 340.00], ['Bateria 150Ah', 'peca', 1250.00], ['Correia do motor', 'peca', 210.00],
    ];

    /** descrição, tipo, período, preço unitário */
    private const ITENS_PROPOSTA = [
        ['Locação de Plataforma Elevatória 12m', 'equipamento', 'mensal', 7800.00], ['Locação de Gerador 100 kVA', 'equipamento', 'mensal', 6200.00],
        ['Locação de Empilhadeira 2,5t', 'equipamento', 'mensal', 5400.00], ['Operador habilitado', 'mao_de_obra', 'mensal', 4800.00],
        ['Documentação e treinamento NR-35', 'seguranca_documentacao', 'unico', 1900.00], ['Kit de EPI para operação', 'acessorio', 'unico', 240.00],
    ];

    /** objeto do contrato, categoria de serviço, valor mensal */
    private const CONTRATOS = [
        ['Operador habilitado de plataforma elevatória', 'mao_de_obra', 4800.00], ['Manutenção preventiva programada da frota', 'mao_de_obra', 3200.00],
        ['Segurança e documentação (NR-35 e NR-12)', 'seguranca_documentacao', 2100.00], ['Fornecimento de insumos e consumíveis', 'insumo', 1750.00],
        ['Operador habilitado de empilhadeira', 'mao_de_obra', 4600.00], ['Acessórios e componentes para a operação', 'acessorio', 980.00],
    ];

    private string $tid;

    /** @var array<string, list<string>> */
    private array $criados = [];

    /** @var list<string> contas do CRM que ganharam client_id (o valor anterior era sempre nulo) */
    private array $contasLigadas = [];

    /** @var array<string, array<int, string>> */
    private array $colunas = [];

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
            $this->warn('A Gestão Comercial já foi populada. Use --remover antes para refazer.');

            return self::FAILURE;
        }
        $contas = DB::table('crm_contas')->where('tenant_id', $this->tid)->orderBy('created_at')->get();
        if ($contas->isEmpty()) {
            $this->error('Esta empresa não tem contas no CRM: rode demo:popular-vendas e demo:completar-vendas primeiro.');

            return self::FAILURE;
        }
        foreach (['clients', 'quotes', 'contracts', 'proposta_comerciais'] as $tabela) {
            if (DB::table($tabela)->where('tenant_id', $this->tid)->exists()) {
                $this->error("Esta empresa já tem registros em {$tabela}: o comando só popula empresa vazia, para nunca misturar dado fictício com real.");

                return self::FAILURE;
            }
        }

        mt_srand(2028);
        DB::transaction(function () use ($contas) {
            $vendedores = DB::table('users')->where('tenant_id', $this->tid)->where('email', 'like', 'vendedor%@vendas-demo.oravel.test')->pluck('id')->all();
            $clientes = $this->clientes($contas);
            $this->orcamentos($clientes, $vendedores);
            $this->propostas($clientes, $vendedores);
            $this->contratos($clientes);
        });

        File::put($this->arquivo(), json_encode(['criados' => $this->criados, 'contas_ligadas' => $this->contasLigadas]));
        $this->info('Gestão Comercial populada: '.collect($this->criados)->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');
        $this->line('Para desfazer: php artisan demo:completar-comercial --remover');

        return self::SUCCESS;
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-comercial-ids.json');
    }

    private function mk(string $tabela, array $linha): string
    {
        $this->colunas[$tabela] ??= Schema::getColumnListing($tabela);
        $linha = $linha + ['id' => (string) Str::uuid(), 'tenant_id' => $this->tid, 'created_at' => now(), 'updated_at' => now()];
        DB::table($tabela)->insert(array_intersect_key($linha, array_flip($this->colunas[$tabela])));
        $this->criados[$tabela][] = $linha['id'];

        return $linha['id'];
    }

    /** @return list<array{id: string, nome: string, lead: ?string}> um cliente para cada conta do CRM, ligado a ela */
    private function clientes($contas): array
    {
        $lista = [];
        foreach ($contas as $c) {
            $id = $this->mk('clients', [
                'name' => $c->nome, 'fantasy_name' => $c->nome_fantasia, 'cpf_cnpj' => $c->documento, 'phone' => $c->telefone, 'whatsapp' => $c->whatsapp, 'email' => $c->email,
                'city' => $c->cidade, 'uf' => $c->uf, 'contact_name' => DB::table('crm_conta_contatos')->where('crm_conta_id', $c->id)->value('name'), 'activity_type' => $c->segmento,
            ]);
            DB::table('crm_contas')->where('id', $c->id)->where('tenant_id', $this->tid)->update(['client_id' => $id]);
            $this->contasLigadas[] = $c->id;
            $lista[] = ['id' => $id, 'nome' => $c->nome];
        }

        return $lista;
    }

    private function orcamentos(array $clientes, array $vendedores): void
    {
        $situacoes = ['rascunho', 'rascunho', 'enviado', 'enviado', 'enviado', 'aprovado', 'aprovado', 'aprovado', 'reprovado', 'concluido'];
        foreach ($situacoes as $i => $status) {
            $quando = now()->subDays(mt_rand(2, 70));
            $itens = [];
            foreach (array_rand(self::ITENS_ORCAMENTO, mt_rand(2, 4)) as $k) {
                [$descricao, $tipo, $preco] = self::ITENS_ORCAMENTO[$k];
                $qtd = $tipo === 'servico' ? mt_rand(2, 10) : mt_rand(1, 4);
                $itens[] = [$descricao, $tipo, $qtd, $preco, round($qtd * $preco, 2)];
            }
            $total = round(array_sum(array_column($itens, 4)), 2);
            $enviado = $status !== 'rascunho';
            $id = $this->mk('quotes', [
                'client_id' => $clientes[$i % count($clientes)]['id'], 'assigned_user_id' => $vendedores ? $vendedores[$i % count($vendedores)] : null, 'type' => 'interno', 'status' => $status,
                'total_value' => $total, 'technical_report' => 'Orçamento de demonstração para o serviço combinado com o cliente.', 'created_at' => $quando, 'updated_at' => $quando,
                'sent_at' => $enviado ? $quando : null, 'approval_token' => $enviado ? Str::random(48) : null,
                'client_viewed_at' => in_array($status, ['aprovado', 'reprovado', 'concluido'], true) ? $quando->copy()->addDay() : null,
                'client_responded_at' => in_array($status, ['aprovado', 'reprovado', 'concluido'], true) ? $quando->copy()->addDays(2) : null,
                'rejection_reason' => $status === 'reprovado' ? 'Valor acima do orçamento aprovado pelo cliente.' : null,
                'completed_at' => $status === 'concluido' ? $quando->copy()->addDays(9) : null,
            ]);
            foreach ($itens as [$descricao, $tipo, $qtd, $preco, $subtotal]) {
                $this->mk('quote_items', ['quote_id' => $id, 'type' => $tipo, 'description' => $descricao, 'quantity' => $qtd, 'unit_price' => $preco, 'subtotal' => $subtotal, 'created_at' => $quando, 'updated_at' => $quando]);
            }
        }
    }

    private function propostas(array $clientes, array $vendedores): void
    {
        $situacoes = ['rascunho', 'enviada_para_comercial', 'aprovada_interna', 'aprovada_interna', 'aceita_pelo_cliente', 'aceita_pelo_cliente', 'recusada_pelo_cliente', 'rejeitada'];
        foreach ($situacoes as $i => $status) {
            $quando = now()->subDays(mt_rand(3, 60));
            $itens = [];
            $candidatos = array_rand(self::ITENS_PROPOSTA, mt_rand(2, 4));
            foreach ($candidatos as $k) {
                [$descricao, $tipo, $periodo, $preco] = self::ITENS_PROPOSTA[$k];
                $qtd = $periodo === 'mensal' ? mt_rand(1, 6) : mt_rand(1, 3);
                $itens[] = [$descricao, $tipo, $periodo, $qtd, $preco, round($qtd * $preco, 2)];
            }
            $total = round(array_sum(array_column($itens, 5)), 2);
            $enviada = ! in_array($status, ['rascunho'], true);
            $id = $this->mk('proposta_comerciais', [
                'client_id' => $clientes[$i % count($clientes)]['id'], 'seller_user_id' => $vendedores ? $vendedores[$i % count($vendedores)] : null, 'status' => $status,
                'valid_until' => $quando->copy()->addDays(15)->toDateString(), 'terms' => 'Pagamento mensal, vencimento em 10 dias após a emissão da nota. Proposta de demonstração.',
                'total_value' => $total, 'sent_at' => $enviada ? $quando : null, 'approval_token' => in_array($status, ['aprovada_interna', 'aceita_pelo_cliente', 'recusada_pelo_cliente'], true) ? Str::random(48) : null,
                'client_viewed_at' => in_array($status, ['aceita_pelo_cliente', 'recusada_pelo_cliente'], true) ? $quando->copy()->addDay() : null,
                'client_responded_at' => in_array($status, ['aceita_pelo_cliente', 'recusada_pelo_cliente'], true) ? $quando->copy()->addDays(3) : null,
                'rejection_reason' => $status === 'rejeitada' ? 'Condições comerciais fora da política de preços.' : null, 'created_at' => $quando, 'updated_at' => $quando,
            ]);
            foreach ($itens as [$descricao, $tipo, $periodo, $qtd, $preco, $subtotal]) {
                $this->mk('proposta_comercial_itens', ['proposta_comercial_id' => $id, 'type' => $tipo, 'description' => $descricao, 'quantity' => $qtd, 'unit_price' => $preco, 'unit_period' => $periodo,
                    'subtotal' => $subtotal, 'created_at' => $quando, 'updated_at' => $quando]);
            }
        }
    }

    /** O número do contrato é único no sistema inteiro (não por empresa): leva um pedaço do id da empresa para nunca colidir com outra. */
    private function contratos(array $clientes): void
    {
        $situacoes = ['Ativo', 'Ativo', 'Ativo', 'Ativo', 'Encerrado', 'Draft'];
        foreach (self::CONTRATOS as $i => [$objeto, $categoria, $valor]) {
            $status = $situacoes[$i];
            $inicio = $status === 'Draft' ? now()->addDays(10) : now()->subMonths(mt_rand(2, 8));
            $this->mk('contracts', [
                'client_id' => $clientes[$i % count($clientes)]['id'], 'contract_number' => 'VND-'.strtoupper(substr(str_replace('-', '', $this->tid), 0, 6)).'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'status' => $status, 'is_active' => $status !== 'Encerrado',
                'start_date' => $inicio->toDateString(), 'end_date' => ($status === 'Encerrado' ? now()->subMonth() : $inicio->copy()->addMonths(12))->toDateString(), 'price' => $valor,
                'billing_type' => 'mensal_fixo', 'service_category' => $categoria, 'payment_method' => 'Boleto', 'usage_purpose' => $objeto, 'legal_forum' => 'Foro da comarca de Campinas/SP',
                'multa_rescisoria' => 10, 'observations' => 'Contrato de demonstração.', 'created_at' => $inicio, 'updated_at' => $inicio,
            ]);
        }
    }

    private function remover(): int
    {
        if (! File::exists($this->arquivo())) {
            $this->warn('Não há lista de ids: nada foi feito por este comando (ou já foi desfeito).');

            return self::SUCCESS;
        }

        $feito = json_decode(File::get($this->arquivo()), true) ?: [];
        DB::transaction(function () use ($feito) {
            if (! empty($feito['contas_ligadas'])) {
                DB::table('crm_contas')->where('tenant_id', $this->tid)->whereIn('id', $feito['contas_ligadas'])->update(['client_id' => null]); // era sempre nulo
            }
            // Filhos antes dos pais.
            foreach (['contracts', 'proposta_comercial_itens', 'proposta_comerciais', 'quote_items', 'quotes', 'clients'] as $tabela) {
                if (! empty($feito['criados'][$tabela])) {
                    DB::table($tabela)->where('tenant_id', $this->tid)->whereIn('id', $feito['criados'][$tabela])->delete();
                }
            }
        });
        File::delete($this->arquivo());
        $this->info('Desfeito: '.collect((array) ($feito['criados'] ?? []))->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');

        return self::SUCCESS;
    }
}
