<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Completa a empresa de teste do Vendas (depois de demo:popular-vendas): catálogo de produtos e serviços, contas de clientes com contatos,
 * itens realistas nas propostas que ainda não têm (o total gravado não muda, então os números dos painéis também não: a diferença para a soma dos itens vira desconto),
 * veículos e despesas de visita. Só ADICIONA: anota tudo em storage/app/demo-vendas-completar-ids.json e --remover desfaz exatamente isso
 * (inclusive as ligações que fez em leads e propostas). Insere direto nas tabelas (sem observers). Sem Faker: roda em PROD.
 */
class DemoCompletarVendas extends Command
{
    protected $signature = 'demo:completar-vendas {--tenant=TESTE Primeiro Acesso : nome da empresa} {--remover : Desfaz o que este comando fez}';

    protected $description = 'Completa a empresa de teste do Vendas (catálogo, contas, itens das propostas, veículos e despesas). Só adiciona; reversível com --remover';

    /** nome, tipo, unidade, preço */
    private const CATALOGO = [
        ['Locação de Plataforma Elevatória 12m', 'servico', 'mês', 7800.00],
        ['Locação de Empilhadeira 2,5t', 'servico', 'mês', 5400.00],
        ['Locação de Gerador 100 kVA', 'servico', 'mês', 6200.00],
        ['Locação de Compressor 185 PCM', 'servico', 'mês', 4300.00],
        ['Locação de Munck 8t', 'servico', 'diária', 1650.00],
        ['Transporte e entrega do equipamento', 'servico', 'viagem', 950.00],
        ['Operador habilitado', 'servico', 'mês', 4800.00],
        ['Manutenção preventiva programada', 'servico', 'visita', 780.00],
        ['Kit de EPI para operação', 'produto', 'un', 240.00],
        ['Cabo de aço 12mm (rolo 50m)', 'produto', 'un', 1180.00],
    ];

    /** razão social, nome fantasia, segmento, cidade, uf, contato */
    private const CONTAS = [
        ['Construtora Horizonte Ltda', 'Horizonte', 'Construção civil', 'Campinas', 'SP', 'Marcos Almeida'],
        ['Metalúrgica Vale Forte S.A.', 'Vale Forte', 'Indústria', 'Sorocaba', 'SP', 'Patrícia Nogueira'],
        ['Agro Campo Maquinário Ltda', 'Agro Campo', 'Agronegócio', 'Ribeirão Preto', 'SP', 'Rogério Tavares'],
        ['Logística Rápida SP Ltda', 'Logística Rápida', 'Logística', 'Jundiaí', 'SP', 'Cláudia Ferraz'],
        ['Engenharia Pinheiro & Filhos', 'Pinheiro Engenharia', 'Construção civil', 'Piracicaba', 'SP', 'Eduardo Pinheiro'],
        ['Usina Santa Clara S.A.', 'Santa Clara', 'Energia', 'Bauru', 'SP', 'Helena Barros'],
        ['Obras e Montagens Lima Ltda', 'Montagens Lima', 'Montagem industrial', 'Limeira', 'SP', 'Fábio Lima'],
        ['Indústria Nova Era Ltda', 'Nova Era', 'Indústria', 'Indaiatuba', 'SP', 'Sandra Moreira'],
    ];

    private string $tid;

    /** @var array<string, list<string>> */
    private array $criados = [];

    /** @var array<string, array<string, mixed>> o que mudou em linhas que já existiam: tabela => [id => valores anteriores] */
    private array $alterados = [];

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
            $this->warn('A empresa já foi completada. Use --remover antes para refazer.');

            return self::FAILURE;
        }
        if (! DB::table('crm_propostas')->where('tenant_id', $this->tid)->exists()) {
            $this->error('Esta empresa não tem propostas: rode demo:popular-vendas primeiro.');

            return self::FAILURE;
        }
        if (DB::table('crm_produtos')->where('tenant_id', $this->tid)->exists() || DB::table('crm_contas')->where('tenant_id', $this->tid)->exists()) {
            $this->error('Esta empresa já tem catálogo ou contas: o comando só completa empresa sem eles, para nunca misturar dado fictício com real.');

            return self::FAILURE;
        }

        mt_srand(2027);
        DB::transaction(function () {
            $produtos = $this->catalogo();
            $contas = $this->contas();
            $this->ligarContas($contas);
            $this->itensDasPropostas($produtos);
            $veiculos = $this->veiculos();
            $this->despesas($veiculos);
        });

        File::put($this->arquivo(), json_encode(['criados' => $this->criados, 'alterados' => $this->alterados]));
        $this->info('Empresa completada: '.collect($this->criados)->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');
        $this->line('Para desfazer: php artisan demo:completar-vendas --remover');

        return self::SUCCESS;
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-vendas-completar-ids.json');
    }

    private function mk(string $tabela, array $linha): string
    {
        $this->colunas[$tabela] ??= Schema::getColumnListing($tabela);
        $linha = $linha + ['id' => (string) Str::uuid(), 'tenant_id' => $this->tid, 'created_at' => now(), 'updated_at' => now()];
        DB::table($tabela)->insert(array_intersect_key($linha, array_flip($this->colunas[$tabela])));
        $this->criados[$tabela][] = $linha['id'];

        return $linha['id'];
    }

    /** @return list<array{id: string, nome: string, preco: float}> */
    private function catalogo(): array
    {
        $lista = [];
        foreach (self::CATALOGO as $i => [$nome, $tipo, $unidade, $preco]) {
            $id = $this->mk('crm_produtos', ['codigo' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'nome' => $nome, 'tipo' => $tipo, 'unidade' => $unidade, 'preco' => $preco, 'ativo' => true,
                'descricao' => 'Item de demonstração do catálogo.']);
            $lista[] = ['id' => $id, 'nome' => $nome, 'preco' => $preco];
        }

        return $lista;
    }

    /** @return list<string> ids das contas */
    private function contas(): array
    {
        $vendedores = DB::table('users')->where('tenant_id', $this->tid)->where('email', 'like', 'vendedor%@vendas-demo.oravel.test')->pluck('id')->all();
        $ids = [];
        foreach (self::CONTAS as $i => [$nome, $fantasia, $segmento, $cidade, $uf, $contato]) {
            $cnpj = '11'.str_pad((string) (222000 + $i * 137), 6, '0', STR_PAD_LEFT).'000'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT);
            $cnpj = substr($cnpj.'00', 0, 14);
            $slug = Str::slug($fantasia);
            $id = $this->mk('crm_contas', [
                'nome' => $nome, 'nome_fantasia' => $fantasia, 'tipo_pessoa' => 'pj', 'documento' => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj),
                'documento_digitos' => $cnpj, 'email' => 'compras@'.$slug.'.demo.test', 'telefone' => '(19) 3'.mt_rand(200, 899).'-'.mt_rand(1000, 9999),
                'whatsapp' => '(19) 9'.mt_rand(8000, 9999).'-'.mt_rand(1000, 9999), 'segmento' => $segmento, 'cidade' => $cidade, 'uf' => $uf,
                'responsavel_user_id' => $vendedores ? $vendedores[$i % count($vendedores)] : null, 'origem' => 'demonstracao', 'ativa' => true, 'custom' => '{}',
            ]);
            $this->mk('crm_conta_contatos', ['crm_conta_id' => $id, 'name' => $contato, 'role_title' => 'Comprador', 'phone' => '(19) 9'.mt_rand(8000, 9999).'-'.mt_rand(1000, 9999),
                'email' => Str::slug($contato).'@'.$slug.'.demo.test', 'is_primary' => true]);
            $ids[] = $id;
        }

        return $ids;
    }

    /** Liga leads e propostas (que não têm conta) às contas, em rodízio. Guarda o valor anterior para o --remover. */
    private function ligarContas(array $contas): void
    {
        foreach (['crm_leads', 'crm_propostas', 'crm_visitas'] as $tabela) {
            $linhas = DB::table($tabela)->where('tenant_id', $this->tid)->whereNull('crm_conta_id')->orderBy('created_at')->pluck('id')->all();
            foreach ($linhas as $i => $id) {
                DB::table($tabela)->where('id', $id)->where('tenant_id', $this->tid)->update(['crm_conta_id' => $contas[$i % count($contas)]]);
                $this->alterados[$tabela][] = $id; // o valor anterior era sempre nulo (só mexemos em quem não tinha conta)
            }
        }
    }

    /**
     * Itens realistas para cada proposta sem itens: linhas do catálogo, com os preços do catálogo e quantidades pequenas, do mais caro para o
     * mais barato, até chegar perto do total que a proposta já tinha. A pequena diferença que sobra vira DESCONTO comercial (campo que a proposta
     * já tem), então o total gravado continua exatamente o mesmo e os números dos painéis não mudam.
     *
     * @param  list<array{id: string, nome: string, preco: float}>  $produtos
     */
    private function itensDasPropostas(array $produtos): void
    {
        $ordenados = collect($produtos)->sortByDesc('preco')->values()->all();
        $menor = (float) end($ordenados)['preco'];

        foreach (DB::table('crm_propostas')->where('tenant_id', $this->tid)->get(['id', 'total', 'desconto']) as $proposta) {
            if (DB::table('crm_proposta_itens')->where('crm_proposta_id', $proposta->id)->exists()) {
                continue;
            }

            $total = round((float) $proposta->total, 2); // total líquido já gravado: não muda
            $soma = 0.0;
            $linhas = 0;

            foreach ($ordenados as $produto) {
                $falta = round($total - $soma, 2);
                if ($falta < $produto['preco'] || $linhas >= 4) {
                    continue;
                }
                $qtd = max(1, min(12, (int) floor($falta / $produto['preco']))); // meses ou unidades
                $this->item($proposta->id, $produto, $qtd, $produto['preco']);
                $soma += $qtd * $produto['preco'];
                $linhas++;
            }

            // Proposta menor que qualquer item do catálogo, ou com sobra: fecha com um item do menor preço e ajusta o desconto.
            if ($soma < $total || $linhas === 0) {
                $this->item($proposta->id, end($ordenados), 1, $menor);
                $soma += $menor;
            }

            // Líquido = soma dos itens - desconto: o desconto é a diferença entre os itens e o total que a proposta já tinha.
            $desconto = round($soma - $total, 2);
            if ($desconto !== round((float) $proposta->desconto, 2)) {
                DB::table('crm_propostas')->where('id', $proposta->id)->where('tenant_id', $this->tid)->update(['desconto' => $desconto]);
                $this->alterados['crm_propostas_desconto'][$proposta->id] = (float) $proposta->desconto;
            }
        }
    }

    /** @param array{id: string, nome: string, preco: float} $produto */
    private function item(string $propostaId, array $produto, int $qtd, float $preco): void
    {
        $this->mk('crm_proposta_itens', ['crm_proposta_id' => $propostaId, 'crm_produto_id' => $produto['id'], 'descricao' => $produto['nome'], 'quantidade' => $qtd,
            'preco_unitario' => $preco, 'subtotal' => round($qtd * $preco, 2)]);
    }

    /** @return list<array{id: string, user: ?string}> */
    private function veiculos(): array
    {
        $vendedores = DB::table('users')->where('tenant_id', $this->tid)->where('email', 'like', 'vendedor%@vendas-demo.oravel.test')->pluck('id')->all();
        $lista = [];
        foreach ([['ABC1D23', 'Fiat Strada', 'Fiat', 'Strada', 2024, 18400], ['EFG4H56', 'VW Saveiro', 'Volkswagen', 'Saveiro', 2023, 41200], ['IJK7L89', 'Chevrolet Montana', 'Chevrolet', 'Montana', 2025, 9800]] as $i => [$placa, $descricao, $marca, $modelo, $ano, $km]) {
            $user = $vendedores ? $vendedores[$i % count($vendedores)] : null;
            $id = $this->mk('crm_veiculos', ['placa' => $placa, 'descricao' => $descricao, 'marca' => $marca, 'modelo' => $modelo, 'ano' => $ano, 'km_atual' => $km, 'responsavel_user_id' => $user,
                'vencimento_licenciamento' => now()->addMonths(3 + $i)->toDateString(), 'vencimento_seguro' => now()->addMonths(5 + $i)->toDateString(), 'proxima_revisao_km' => $km + 3000, 'ativo' => true]);
            $lista[] = ['id' => $id, 'user' => $user];
        }

        return $lista;
    }

    /** Despesas ligadas a visitas já realizadas (combustível, pedágio e alimentação), com status variados. */
    private function despesas(array $veiculos): void
    {
        $visitas = DB::table('crm_visitas')->where('tenant_id', $this->tid)->where('status', 'realizada')->orderByDesc('agendada_para')->limit(40)->get(['id', 'user_id', 'agendada_para']);
        $status = ['aprovada', 'aprovada', 'paga', 'pendente', 'aprovada', 'paga'];
        foreach ($visitas as $i => $visita) {
            $veiculo = $veiculos[$i % count($veiculos)];
            $tipo = ['combustivel', 'pedagio', 'alimentacao'][$i % 3];
            $valor = match ($tipo) {
                'combustivel' => mt_rand(180, 420),
                'pedagio' => mt_rand(18, 64),
                default => mt_rand(35, 90),
            };
            $this->mk('crm_visita_despesas', [
                'crm_visita_id' => $visita->id, 'user_id' => $visita->user_id, 'crm_veiculo_id' => $tipo === 'combustivel' ? $veiculo['id'] : null, 'data' => substr((string) $visita->agendada_para, 0, 10),
                'tipo' => $tipo, 'valor' => $valor, 'descricao' => 'Despesa de demonstração', 'litros' => $tipo === 'combustivel' ? round($valor / 6.1, 2) : null, 'status' => $status[$i % count($status)],
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
            // Ligações feitas em linhas que já existiam: voltam a ficar sem conta (era sempre nulo).
            foreach ((array) ($feito['alterados'] ?? []) as $tabela => $ids) {
                if ($tabela === 'crm_propostas_desconto') {
                    foreach ($ids as $id => $anterior) {
                        DB::table('crm_propostas')->where('tenant_id', $this->tid)->where('id', $id)->update(['desconto' => $anterior]);
                    }

                    continue;
                }
                DB::table($tabela)->where('tenant_id', $this->tid)->whereIn('id', $ids)->update(['crm_conta_id' => null]);
            }
            // Filhos antes dos pais.
            foreach (['crm_visita_despesas', 'crm_proposta_itens', 'crm_veiculos', 'crm_conta_contatos', 'crm_contas', 'crm_produtos'] as $tabela) {
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
