<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * F1 (prioridade configuravel): catalogo de prioridades por tenant. Nasce com 4 niveis do sistema
 * (critica/alta/media/baixa -- os codigos que maintenance_orders.priority ja usava: media/alta/baixa) e o tenant pode
 * acrescentar os dele; cada nivel aponta para um nivel base para os relatorios continuarem comparaveis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('color', 20)->default('#6b7280');
            $table->string('base_level', 20); // critica|alta|media|baixa
            $table->unsignedSmallInteger('sort_order')->default(0); // 1 = mais alta
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        $padrao = [
            ['code' => 'critica', 'name' => 'Crítica', 'color' => '#dc2626', 'sort_order' => 1],
            ['code' => 'alta', 'name' => 'Alta', 'color' => '#f97316', 'sort_order' => 2],
            ['code' => 'media', 'name' => 'Média', 'color' => '#eab308', 'sort_order' => 3],
            ['code' => 'baixa', 'name' => 'Baixa', 'color' => '#22c55e', 'sort_order' => 4],
        ];

        DB::table('tenants')->pluck('id')->each(function ($tenantId) use ($padrao) {
            foreach ($padrao as $n) {
                DB::table('priority_levels')->insert($n + [
                    'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'base_level' => $n['code'],
                    'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_levels');
    }
};
