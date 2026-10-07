<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vínculo entre um veículo e o rastreador GPS (Traccar): a distância percorrida alimenta o odômetro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_vinculos_gps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedBigInteger('traccar_device_id');
            $table->string('identificador', 100);                      // uniqueId / IMEI do rastreador
            $table->timestamp('ultima_sincronizacao')->nullable();     // só avança quando o Traccar respondeu
            $table->decimal('km_acumulado', 10, 3)->default(0);        // fração de km que ainda não virou 1 km inteiro no odômetro
            $table->string('ultimo_resultado', 120)->nullable();       // texto curto da última sincronização
            $table->boolean('ativo')->default(true);
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo']);
        });
        // Um veículo tem um rastreador ativo, e um rastreador pertence a um veículo ativo.
        DB::statement('CREATE UNIQUE INDEX frota_vinculo_gps_ativo_unico ON frota_vinculos_gps (ativo_id) WHERE ativo = true');
        DB::statement('CREATE UNIQUE INDEX frota_vinculo_gps_device_unico ON frota_vinculos_gps (traccar_device_id) WHERE ativo = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_vinculos_gps');
    }
};
