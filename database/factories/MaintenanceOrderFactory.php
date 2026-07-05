<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Nota: a tabela real NAO tem coluna commercial_status (so existe no
 * $fillable do model, e so e usada de leitura pelo painel Kanban
 * "comercial" -- setar esse campo aqui quebraria o INSERT). os_number
 * fica por conta do proprio model (booted() gera o sequencial).
 */
class MaintenanceOrderFactory extends Factory
{
    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-8 months', '-2 weeks');

        return [
            'id' => (string) Str::uuid(),
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'maintenance_type' => $this->faker->randomElement(['Corretiva', 'Corretiva', 'Preventiva']),
            'description' => $this->faker->randomElement([
                'Ruído anormal no motor durante operação',
                'Vazamento de óleo hidráulico identificado',
                'Falha no sistema elétrico de partida',
                'Manutenção preventiva programada',
                'Superaquecimento durante uso prolongado',
                'Desgaste excessivo em componente móvel',
            ]),
            'hours_spent' => 0,
            'labor_cost' => $this->faker->randomFloat(2, 100, 800),
            'material_cost' => $this->faker->randomFloat(2, 0, 1200),
            'logistics_cost' => $this->faker->randomFloat(2, 0, 300),
            'total_order_cost' => 0,
            'is_rework' => $this->faker->boolean(8),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function withElapsed(\DateTime $createdAt, int $daysLater): \DateTime
    {
        return (clone $createdAt)->modify("+{$daysLater} days");
    }

    /** Aguardando diagnostico (estado inicial, ja e o default). */
    public function aguardandoDiagnostico(): static
    {
        return $this->state(fn () => [
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
        ]);
    }

    /** Em manutencao: cronometro rodando. */
    public function emManutencao(): static
    {
        return $this->state(function (array $attrs) {
            $createdAt = $attrs['created_at'];
            $startedAt = $this->withElapsed($createdAt, 1);

            return [
                'status' => 'Em Andamento',
                'internal_status' => 'em_manutencao',
                'started_at' => $startedAt,
                'last_timer_start' => $startedAt,
                'total_time_seconds' => $this->faker->numberBetween(3600, 4 * 3600),
            ];
        });
    }

    /** Aguardando peca: parado no meio do atendimento. */
    public function aguardandoPeca(): static
    {
        return $this->state(function (array $attrs) {
            $createdAt = $attrs['created_at'];
            $startedAt = $this->withElapsed($createdAt, 1);

            return [
                'status' => 'Pendente',
                'internal_status' => 'aguardando_peca',
                'started_at' => $startedAt,
                'total_time_seconds' => $this->faker->numberBetween(1800, 3 * 3600),
            ];
        });
    }

    /** Teste de qualidade: servico feito, em revisao final. */
    public function testeQualidade(): static
    {
        return $this->state(function (array $attrs) {
            $createdAt = $attrs['created_at'];
            $startedAt = $this->withElapsed($createdAt, 1);

            return [
                'status' => 'Em Andamento',
                'internal_status' => 'teste_qualidade',
                'started_at' => $startedAt,
                'total_time_seconds' => $this->faker->numberBetween(4 * 3600, 8 * 3600),
                'hours_spent' => $this->faker->randomFloat(2, 4, 8),
            ];
        });
    }

    /** Pendencia: aguardando decisao/aprovacao externa. */
    public function pendencia(): static
    {
        return $this->state(function (array $attrs) {
            $createdAt = $attrs['created_at'];
            $startedAt = $this->withElapsed($createdAt, 2);

            return [
                'status' => 'Pendente',
                'internal_status' => 'pendencia',
                'started_at' => $startedAt,
                'total_time_seconds' => $this->faker->numberBetween(3600, 5 * 3600),
            ];
        });
    }

    /** Concluida: ciclo completo, com custo total fechado. */
    public function concluido(): static
    {
        return $this->state(function (array $attrs) {
            $createdAt = $attrs['created_at'];
            $startedAt = $this->withElapsed($createdAt, 1);
            $finishedAt = $this->withElapsed($createdAt, $this->faker->numberBetween(2, 6));
            $laborCost = $attrs['labor_cost'] ?? $this->faker->randomFloat(2, 100, 800);
            $materialCost = $attrs['material_cost'] ?? $this->faker->randomFloat(2, 0, 1200);
            $logisticsCost = $attrs['logistics_cost'] ?? $this->faker->randomFloat(2, 0, 300);

            return [
                'status' => 'Concluída',
                'internal_status' => 'concluido',
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'hours_spent' => $this->faker->randomFloat(2, 2, 10),
                'total_time_seconds' => $this->faker->numberBetween(4 * 3600, 20 * 3600),
                'total_order_cost' => round($laborCost + $materialCost + $logisticsCost, 2),
                'updated_at' => $finishedAt,
            ];
        });
    }

    /** Cancelada. */
    public function cancelada(): static
    {
        return $this->state(fn () => [
            'status' => 'Cancelada',
            'internal_status' => 'aguardando_diagnostico',
            'cancel_reason' => $this->faker->randomElement(['Cliente desistiu do reparo', 'Equipamento substituído', 'Duplicidade de chamado']),
        ]);
    }
}
