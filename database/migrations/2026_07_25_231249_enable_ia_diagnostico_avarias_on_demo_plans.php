<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $plans = ['Plano Demo Comercial', 'Plano Demo Nichos'];

    private string $feature = 'ia_diagnostico_avarias';

    public function up(): void
    {
        foreach ($this->plans as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $features = json_decode($plan->features, true) ?? [];
            $features = array_values(array_unique([...$features, $this->feature]));

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->plans as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $features = json_decode($plan->features, true) ?? [];
            $features = array_values(array_diff($features, [$this->feature]));

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }
};
