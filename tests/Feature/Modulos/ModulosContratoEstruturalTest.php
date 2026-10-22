<?php

namespace Tests\Feature\Modulos;

use App\Policies\AbstractPolicy;
use App\Support\SaaSRegistry;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Contrato estrutural de TODO modulo SaaS, sem tocar no banco: metadados do
 * SaaSRegistry completos e Policy propria (nomeada) herdando AbstractPolicy.
 * Modulo novo que esquecer qualquer um desses itens falha aqui.
 */
class ModulosContratoEstruturalTest extends TestCase
{
    /**
     * Divida conhecida em 2026-10-22: policies escritas a mao (Material,
     * MaintenanceOrder, Role -- ver CLAUDE.md) ou modulos sem policy propria.
     * A lista so pode ENCOLHER: modulo novo fora dela tem que seguir o contrato,
     * e item corrigido tem que sair daqui (o teste cobra isso).
     */
    private const POLICY_FORA_DO_PADRAO = [
        'BatteryCycleReading', 'ActivityLogEntry', 'NotificationLog', 'MaintenanceStatusHistory',
        'AssetNr13Specification', 'AbcMatrixHistory', 'LandingPageLead', 'Material',
        'MaintenanceOrder', 'CashflowReport', 'Role', 'BankReconciliation', 'AsaasPaymentSync',
    ];

    /** Features compartilhadas entre modelos irmaos (mesmo toggle de plano liga os dois). */
    private const FEATURES_REPETIDAS_CONHECIDAS = ['tabela_warehouses', 'tabela_parts', 'tabela_proposta_comercial'];

    /** Slugs repetidos de proposito: os dois sistemas de estoque (Material x Part) e correlatos. */
    private const SLUGS_REPETIDOS_CONHECIDOS = ['almoxarifado', 'funcao', 'movimento_estoque', 'peca'];

    public function test_metadados_completos_em_todo_modulo(): void
    {
        $falhas = [];
        foreach (SaaSRegistry::modules() as $m) {
            foreach (['slug', 'feature', 'label'] as $campo) {
                if (blank($m[$campo])) {
                    $falhas[] = class_basename($m['model'])." sem {$campo}";
                }
            }
        }

        $this->assertSame([], $falhas);
    }

    public function test_todo_modulo_tem_policy_propria_que_herda_abstract_policy(): void
    {
        $falhas = [];
        foreach (SaaSRegistry::modules() as $m) {
            $policy = Gate::getPolicyFor($m['model']);
            $nome = class_basename($m['model']);

            $ok = $policy instanceof AbstractPolicy && class_basename($policy) === $nome.'Policy';
            $conhecido = in_array($nome, self::POLICY_FORA_DO_PADRAO, true);

            if (! $ok && ! $conhecido) {
                $falhas[] = "{$nome}: policy ".($policy ? get_class($policy) : 'ausente').' fora do padrao';
            }
            if ($ok && $conhecido) {
                $falhas[] = "{$nome}: ja esta no padrao, remova de POLICY_FORA_DO_PADRAO";
            }
        }

        $this->assertSame([], $falhas);
    }

    public function test_slugs_e_features_sao_unicos(): void
    {
        $slugs = array_column(SaaSRegistry::modules(), 'slug');
        $features = array_column(SaaSRegistry::modules(), 'feature');

        $repetidos = array_keys(array_filter(array_count_values($slugs), fn ($n) => $n > 1));
        $this->assertEqualsCanonicalizing(self::SLUGS_REPETIDOS_CONHECIDOS, $repetidos, 'slug de permissao duplicado fora da lista conhecida');
        $dupFeatures = array_keys(array_filter(array_count_values($features), fn ($n) => $n > 1));
        $this->assertEqualsCanonicalizing(self::FEATURES_REPETIDAS_CONHECIDAS, $dupFeatures, 'feature duplicada fora da lista conhecida');
    }
}
