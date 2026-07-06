<?php

namespace Database\Factories;

use App\Models\EquipmentReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipmentReplacementFactory extends Factory
{
    protected $model = EquipmentReplacement::class;

    private static array $reasons = [
        'Falha recorrente no motor, já retornou 3x pra manutenção.',
        'Fim de vida útil estimada, custo de reparo não compensa.',
        'Estrutura comprometida após avaria, risco operacional.',
        'Cliente reportou perda de performance constante.',
        'Vazamento hidráulico recorrente, sem solução definitiva nas últimas intervenções.',
        'Desgaste excessivo do rodante/pneus, fora da tolerância de segurança.',
        'Painel de controle com falhas intermitentes, difícil diagnóstico em campo.',
        'Corrosão estrutural avançada identificada na última vistoria.',
        'Consumo de combustível muito acima do esperado, indício de desgaste interno do motor.',
        'Sistema elétrico com curtos recorrentes, risco de incêndio.',
        'Capacidade de carga comprometida após sobrecarga acidental.',
        'Ruído/vibração anormal na operação, possível dano em rolamentos internos.',
        'Custo acumulado de manutenção já superou o valor residual do equipamento.',
        'Equipamento reprovado em inspeção de segurança (NR-13/NR-12).',
        'Peça crítica descontinuada pelo fabricante, sem reposição disponível.',
    ];

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'urgency' => $this->faker->randomElement([
                EquipmentReplacement::URGENCY_NORMAL,
                EquipmentReplacement::URGENCY_NORMAL,
                EquipmentReplacement::URGENCY_URGENTE,
                EquipmentReplacement::URGENCY_CRITICO,
            ]),
            'reason' => $this->faker->randomElement(self::$reasons),
        ];
    }

    /**
     * Unica state "estatica" da factory: cancelamento nao exige nenhum
     * movement/substituto pra ser consistente, pode acontecer em qualquer
     * estagio. Os demais estagios (substituto identificado, movements em
     * andamento, concluido) sao produzidos chamando os metodos reais do
     * model (identifyReplacement/startLogisticsMovements + conclusao dos
     * movements) no seeder, pra nao gerar linha com status avancado mas
     * sem os movements/efeitos colaterais que deveriam existir junto.
     */
    public function cancelado(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentReplacement::STATUS_CANCELADO,
            'reason' => 'Cliente optou por manter o equipamento original após negociação.',
        ]);
    }
}
