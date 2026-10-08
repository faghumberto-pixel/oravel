<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Menus pai ganharam chave própria no contrato (config/menu_modules.php).
 * Para ninguém perder menu ao publicar, cada contrato recebe a chave LIGADA
 * quando já tinha algum dos módulos que liberavam o menu (ou em todos, para
 * menus sem módulo de origem). Quem fica sem módulo nenhum dentro do menu
 * recebe a chave desligada, como o menu vazio já sumia.
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
                $ligado = $herda === [] || collect($herda)->contains(fn ($f) => ($features[$f] ?? false) === true);

                $features[$chave] = $ligado;
                $mudou = true;
            }

            if ($mudou) {
                $plan->update(['features' => $features]);
            }
        }
    }

    public function down(): void
    {
        $menus = array_keys(config('menu_modules', []));

        foreach (Plan::query()->get() as $plan) {
            $features = array_diff_key($plan->features ?? [], array_flip($menus));
            $plan->update(['features' => $features]);
        }
    }
};
