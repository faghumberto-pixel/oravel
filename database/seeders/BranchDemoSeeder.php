<?php

namespace Database\Seeders;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula Filiais (App\Models\Branch) nos 5 tenants de demonstração e
 * vincula as Contas a Pagar/Receber já existentes à filial Matriz -- sem
 * isso, o campo "Filial" nas duas telas fica sempre vazio mesmo depois de
 * cadastrar a Filial, porque nada aponta pra ela. Idempotente, aditivo.
 *
 * Uso: php artisan db:seed --class=BranchDemoSeeder
 */
class BranchDemoSeeder extends Seeder
{
    /**
     * Só os 2 tenants com frota maior ganham uma 2ª filial -- os outros 3
     * são operação de pátio único.
     */
    private const SLUGS_COM_FILIAL_SECUNDARIA = [
        'torres-guindastes',
        'geradores-rmc',
    ];

    public function run(): void
    {
        $slugs = [
            'torres-guindastes',
            'geradores-rmc',
            'construtora-alicerce-locacoes',
            'eventos-show-geradores',
            'hospital-vida-plena-energia',
        ];

        foreach ($slugs as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (Branch::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $matriz = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Matriz',
                'description' => 'Pátio e escritório principal.',
                'city' => 'Campinas',
                'state' => 'SP',
            ]);

            if (in_array($slug, self::SLUGS_COM_FILIAL_SECUNDARIA, true)) {
                Branch::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Filial Regional',
                    'description' => 'Apoio operacional pra atendimento na região.',
                    'city' => 'Sumaré',
                    'state' => 'SP',
                ]);
            }

            // Sem isso, cadastrar a Filial não muda nada visível -- as
            // contas já existentes continuam sem filial atribuída.
            AccountPayable::where('tenant_id', $tenant->id)->whereNull('branch_id')->update(['branch_id' => $matriz->id]);
            AccountReceivable::where('tenant_id', $tenant->id)->whereNull('branch_id')->update(['branch_id' => $matriz->id]);
        }
    }
}
