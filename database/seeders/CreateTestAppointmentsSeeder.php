<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CreateTestAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'teste-tecnico')->first();
        $tech = User::where('email', 'tecnico@teste.local')->first();

        if (!$tenant || !$tech) {
            echo "✗ Tenant ou técnico não encontrado\n";
            return;
        }

        Appointment::firstOrCreate(
            ['tenant_id' => $tenant->id, 'technician_id' => $tech->id, 'assunto' => 'Revisão de bombas'],
            [
                'descricao' => 'Verificação de estado das bombas no pátio',
                'scheduled_at' => Carbon::now()->addDays(1)->setHour(9)->setMinute(0),
                'urgente' => false,
                'completed' => false,
            ]
        );

        Appointment::firstOrCreate(
            ['tenant_id' => $tenant->id, 'technician_id' => $tech->id, 'assunto' => 'Troca de peças URGENTE'],
            [
                'descricao' => 'Troca de peças de desgaste na bomba principal',
                'scheduled_at' => Carbon::now()->addDays(2)->setHour(14)->setMinute(0),
                'urgente' => true,
                'completed' => false,
            ]
        );

        Appointment::firstOrCreate(
            ['tenant_id' => $tenant->id, 'technician_id' => $tech->id, 'assunto' => 'Limpeza de filtros'],
            [
                'descricao' => 'Limpeza dos filtros de entrada',
                'scheduled_at' => Carbon::now()->addDays(3)->setHour(10)->setMinute(30),
                'urgente' => false,
                'completed' => false,
            ]
        );

        echo "✓ Agendamentos de teste criados\n";
    }
}
