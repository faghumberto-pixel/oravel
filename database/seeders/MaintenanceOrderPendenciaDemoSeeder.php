<?php

namespace Database\Seeders;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPendencia;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Popula Pendências de O.S. (App\Models\MaintenanceOrderPendencia --
 * separado do status "Pendência" do Kanban, consultado até resolver em
 * App\Filament\Pages\EventosEFalhas) nos 5 tenants de demonstração.
 * Idempotente, aditivo, sem criar tenant novo.
 *
 * Nota: MaintenanceOrderPendenciaObserver notifica de verdade os papéis
 * Supervisor/Gerente/Analista de Manutenção na criação -- comportamento
 * esperado, mesmo padrão de outros seeders desta sessão.
 *
 * Uso: php artisan db:seed --class=MaintenanceOrderPendenciaDemoSeeder
 */
class MaintenanceOrderPendenciaDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (MaintenanceOrderPendencia::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $orders = MaintenanceOrder::where('tenant_id', $tenant->id)->limit(3)->get();
            $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();

            if ($orders->count() < 2 || ! $admin) {
                continue;
            }

            // Aberta: falta peça, ainda incomodando -- é o que aparece no
            // Painel de Eventos e Falhas até alguém resolver.
            MaintenanceOrderPendencia::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $orders->get(0)->id,
                'description' => 'Peça de reposição solicitada ao fornecedor, prazo de entrega ainda não confirmado.',
                'created_by_user_id' => $admin->id,
                'status' => MaintenanceOrderPendencia::STATUS_ABERTA,
            ]);

            // Resolvida: histórico -- já foi resolvida, some do alerta ativo.
            MaintenanceOrderPendencia::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $orders->get(1)->id,
                'description' => 'Aguardando liberação do cliente pra acesso ao local da manutenção.',
                'created_by_user_id' => $admin->id,
                'status' => MaintenanceOrderPendencia::STATUS_RESOLVIDA,
                'resolved_at' => now()->subDays(2),
                'resolved_by_user_id' => $admin->id,
                'resolution_notes' => 'Cliente liberou acesso, serviço concluído normalmente.',
            ]);
        }
    }
}
