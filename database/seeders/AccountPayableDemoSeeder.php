<?php

namespace Database\Seeders;

use App\Models\AccountPayable;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula Contas a Pagar (App\Models\AccountPayable -- o par de Contas a
 * Receber, que já tinha dados) nos 5 tenants de demonstração. branch_id
 * fica de fora de propósito (Branch ainda está vazia, é a próxima da
 * lista). Idempotente, aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=AccountPayableDemoSeeder
 */
class AccountPayableDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    /**
     * [descrição, valor, dias até o vencimento (negativo = já venceu),
     * status].
     */
    private const TEMPLATE = [
        ['Aluguel do galpão/pátio', 8500.00, 12, 'pendente'],
        ['Peças de reposição -- estoque de manutenção', 2340.50, -5, 'atrasado'],
        ['Seguro de frota e equipamentos', 3120.00, -20, 'pago'],
        ['Conta de energia elétrica', 1890.75, 5, 'pendente'],
        ['Manutenção preventiva de veículo próprio', 980.00, -15, 'pago'],
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (AccountPayable::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            foreach (self::TEMPLATE as [$description, $amount, $diasVencimento, $status]) {
                AccountPayable::create([
                    'tenant_id' => $tenant->id,
                    'description' => $description,
                    'amount' => $amount,
                    'due_date' => now()->addDays($diasVencimento),
                    'payment_date' => $status === 'pago' ? now()->addDays($diasVencimento)->subDays(random_int(1, 3)) : null,
                    'status' => $status,
                ]);
            }
        }
    }
}
