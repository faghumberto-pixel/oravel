<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Popula a Agenda do Técnico (App\Models\Appointment -- compromissos
 * pessoais, misturados com O.S. agendada no mesmo calendário, ver
 * AgendaTecnicoWidget) nos 5 tenants de demonstração. Idempotente,
 * aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=AppointmentDemoSeeder
 */
class AppointmentDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    /**
     * [dias a partir de hoje (negativo = passado), assunto, urgente,
     * completed]. completed sempre acompanha os itens do passado (fez
     * sentido já ter acontecido), nunca os futuros.
     */
    private const TEMPLATE = [
        [-6, 'Treinamento de segurança NR-12', false, true],
        [-3, 'Reunião de alinhamento semanal', false, true],
        [0, 'Visita técnica preventiva agendada', true, false],
        [2, 'Inspeção de equipamento antes de nova locação', false, false],
        [5, 'Alinhamento com fornecedor de peças', false, false],
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (Appointment::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $technicians = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'tecnico'))
                ->limit(2)
                ->get();

            if ($technicians->isEmpty()) {
                continue;
            }

            foreach (self::TEMPLATE as $i => [$dias, $assunto, $urgente, $completed]) {
                $tecnico = $technicians[$i % $technicians->count()];

                Appointment::create([
                    'tenant_id' => $tenant->id,
                    'technician_id' => $tecnico->id,
                    'assunto' => $assunto,
                    'descricao' => "Compromisso registrado na agenda pessoal de {$tecnico->name}.",
                    'urgente' => $urgente,
                    'completed' => $completed,
                    'scheduled_at' => now()->addDays($dias)->setTime(9 + $i, 0),
                ]);
            }
        }
    }
}
