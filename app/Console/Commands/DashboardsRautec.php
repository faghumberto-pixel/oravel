<?php

namespace App\Console\Commands;

use App\Dashboards\BlockRegistry;
use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Models\DashboardTemplate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cria os 4 dashboards do exemplo aprovado (docs/dashboards/exemplo-rautec.html: Visão geral, Assistência técnica,
 * Peças e materiais, Comercial) como modelos da plataforma, usando os blocos que existem no catálogo. Os blocos do
 * exemplo sem dado no Oravel (fabricação, kits de desgaste, startup) ficam de fora de propósito.
 *
 * Com --empresa, cada modelo é copiado como dashboard exclusivo daquela empresa e vira a tela Início dela.
 * Repetir o comando atualiza os modelos e não duplica nada. Só dados do catálogo: nenhum dado de cliente.
 */
class DashboardsRautec extends Command
{
    protected $signature = 'dashboards:rautec {--empresa= : Nome ou slug da empresa que recebe os 4 dashboards como tela Início}';

    protected $description = 'Cria os 4 dashboards do exemplo Rautec e, opcionalmente, entrega à empresa como tela Início';

    private const DASHBOARDS = [
        'Visão geral' => ['direcao', 'Para a direção: máquinas, assistência, peças e comercial em uma tela.', [
            'manutencao.atendimentos_hoje', 'manutencao.equipamentos_parados', 'financeiro.recebido_mes', 'comercial.orcamentos_sem_retorno',
            'materiais.abaixo_minimo_total', 'manutencao.no_prazo', 'financeiro.receita_por_categoria', 'manutencao.por_situacao',
            'financeiro.recebimentos_atrasados', 'financeiro.recebido_x_pago',
        ]],
        'Assistência técnica' => ['tecnico', 'Para quem coordena os técnicos: agenda, prazo de resposta, deslocamento e defeitos.', [
            'manutencao.no_prazo', 'manutencao.retorno_maquina', 'manutencao.km_rodados', 'manutencao.custo_medio', 'manutencao.agenda_hoje',
            'manutencao.tipo_atendimento', 'manutencao.tempo_primeiro_atendimento', 'manutencao.defeitos_frequentes', 'manutencao.aguardando_peca',
        ]],
        'Peças e materiais' => ['estoque', 'Para o estoque: peças de revenda, peças aplicadas em assistência e materiais.', [
            'materiais.valor_em_estoque', 'materiais.giro', 'materiais.abaixo_minimo_total', 'materiais.compras_a_receber', 'materiais.abaixo_minimo_lista',
            'materiais.estoque_por_categoria', 'materiais.mais_consumidos', 'materiais.cobertura_dias', 'materiais.aplicadas_x_orcadas',
        ]],
        'Comercial' => ['comercial', 'Para os vendedores: funil, contratos a renovar e clientes que precisam de contato.', [
            'comercial.contratos_ativos', 'comercial.conversao_orcamentos', 'comercial.orcamentos_sem_retorno', 'comercial.funil', 'comercial.vendas_por_vendedor',
            'comercial.contratos_a_renovar', 'comercial.clientes_para_ligar', 'comercial.contratos_por_mes',
        ]],
    ];

    public function handle(): int
    {
        $desconhecidos = collect(self::DASHBOARDS)->flatMap(fn ($d) => $d[2])->unique()->reject(fn ($k) => BlockRegistry::find($k));
        if ($desconhecidos->isNotEmpty()) {
            $this->error('Blocos fora do catálogo: '.$desconhecidos->implode(', '));

            return self::FAILURE;
        }

        $empresa = null;
        if ($busca = $this->option('empresa')) {
            $empresa = Tenant::withoutGlobalScopes()->where('slug', $busca)->orWhere('name', $busca)->first();
            if (! $empresa) {
                $this->error('Empresa "'.$busca.'" não encontrada.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($empresa) {
            foreach (self::DASHBOARDS as $nome => [$perfil, $descricao, $blocos]) {
                $modelo = DashboardTemplate::withoutGlobalScopes()->firstOrNew(['tenant_id' => null, 'name' => 'Rautec — '.$nome]);
                $modelo->fill(['profile' => $perfil, 'description' => $descricao, 'is_active' => true])->save();
                $modelo->blocks()->delete();
                foreach ($blocos as $i => $chave) {
                    $modelo->blocks()->create(['block_key' => $chave, 'position' => $i + 1, 'config' => []]);
                }
                $this->line('Modelo "'.$modelo->name.'": '.count($blocos).' blocos');

                if ($empresa && ! DashboardTemplate::withoutGlobalScopes()->where('tenant_id', $empresa->id)->where('source_template_id', $modelo->id)->exists()) {
                    DashboardTemplateService::exclusivoPara($modelo, $empresa, $nome, true);
                    $this->info('  → entregue a '.$empresa->name.' como Início');
                }
            }
        });

        return self::SUCCESS;
    }
}
