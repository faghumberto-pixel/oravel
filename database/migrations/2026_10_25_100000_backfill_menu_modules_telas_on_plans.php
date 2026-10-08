<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Cada tela do menu (PMP, Relatórios, Pátio...) também ganhou chave própria no
 * contrato. Mesma regra da migração anterior: só preenche chaves ainda
 * inexistentes, ligando-as nos contratos que já tinham algum dos módulos de
 * origem (ou em todos, quando a tela não tinha módulo de origem).
 */
return new class extends Migration
{
    public function up(): void
    {
        $menus = config('menu_modules', []);

        foreach (Plan::query()->get() as $plan) {
            $features = $plan->features ?? [];
            $mudou = false;

            foreach ($menus as $chave => $menu) {
                if (array_key_exists($chave, $features)) {
                    continue;
                }

                $herda = $menu['herda'] ?? [];
                $features[$chave] = $herda === [] || collect($herda)->contains(fn ($f) => ($features[$f] ?? false) === true);
                $mudou = true;
            }

            if ($mudou) {
                $plan->update(['features' => $features]);
            }
        }
    }

    public function down(): void
    {
        // Mantém as chaves: remover apagaria escolhas feitas na Central depois da migração.
    }
};
