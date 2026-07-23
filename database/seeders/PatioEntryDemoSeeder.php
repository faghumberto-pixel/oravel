<?php

namespace Database\Seeders;

use App\Models\PatioEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Popula o registro de portaria (App\Models\PatioEntry -- qualquer
 * veículo entrando/saindo da unidade, distinto de EquipmentPatioArrival,
 * ver App\Filament\Pages\PatioChegadas) nos 5 tenants de demonstração.
 * Idempotente, aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=PatioEntryDemoSeeder
 */
class PatioEntryDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    /**
     * [dias atrás, direção, motivo, motorista, veículo da empresa?,
     * origem/destino].
     */
    private const TEMPLATE = [
        [8, PatioEntry::DIRECTION_ENTRADA, PatioEntry::REASON_FORNECEDOR, 'Carlos Eduardo Ramos', false, 'Distribuidora de Peças Central'],
        [6, PatioEntry::DIRECTION_ENTRADA, PatioEntry::REASON_ENTREGA_PECAS, 'Motoboy Rápido Express', false, 'Transportadora Rápido Express'],
        [4, PatioEntry::DIRECTION_SAIDA, PatioEntry::REASON_SAIDA_EXTERNA, 'João Pereira dos Santos', true, 'Cliente -- entrega programada'],
        [3, PatioEntry::DIRECTION_ENTRADA, PatioEntry::REASON_VISITA, 'Ana Paula Ribeiro', false, 'Visita comercial'],
        [1, PatioEntry::DIRECTION_SAIDA, PatioEntry::REASON_TRANSFERENCIA, 'Marcos Vinícius Alves', true, 'Filial -- transferência interna'],
        [0, PatioEntry::DIRECTION_ENTRADA, PatioEntry::REASON_FORNECEDOR, 'Roberto Nogueira Lima', false, 'Auto Peças Nogueira'],
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (PatioEntry::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();

            if (! $admin) {
                continue;
            }

            foreach (self::TEMPLATE as [$dias, $direction, $reason, $driverName, $isCompanyVehicle, $origin]) {
                PatioEntry::create([
                    'tenant_id' => $tenant->id,
                    'direction' => $direction,
                    'plate' => strtoupper(chr(random_int(65, 90)).chr(random_int(65, 90)).chr(random_int(65, 90))).'-'.random_int(1000, 9999),
                    'is_company_vehicle' => $isCompanyVehicle,
                    'driver_name' => $driverName,
                    'driver_document' => (string) random_int(100000000, 999999999),
                    'origin' => $origin,
                    'reason' => $reason,
                    'brings_equipment' => false,
                    'arrived_at' => now()->subDays($dias)->setTime(random_int(7, 17), random_int(0, 59)),
                    'registered_by_user_id' => $admin->id,
                ]);
            }
        }
    }
}
