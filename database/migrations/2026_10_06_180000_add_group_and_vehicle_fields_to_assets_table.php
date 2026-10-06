<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Grupo do ativo: 'maquina' (todos os ativos que ja existem) ou 'veiculo'. Decide quais campos
            // aparecem no cadastro. Marca = fabricante; ano de fabricacao = manufacturing_year; horimetro e
            // odometro ja existem (horimetro_inicial/last_horimetro, odometro_atual).
            $table->string('grupo', 20)->default('maquina')->index();
            $table->boolean('veiculo_pesado')->default(false);
            $table->string('modelo')->nullable();
            $table->string('cor', 60)->nullable();
            $table->string('chassi', 30)->nullable();
            $table->unsignedSmallInteger('ano_modelo')->nullable();
            // Somente veiculo
            $table->string('placa', 10)->nullable();
            $table->string('renavam', 11)->nullable();
            $table->date('licenciamento_vencimento')->nullable();   // "vencimento do emplacamento"
            $table->string('seguro_seguradora')->nullable();
            $table->string('seguro_apolice')->nullable();
            $table->date('seguro_vencimento')->nullable();
            $table->string('tacografo_numero')->nullable();          // veiculo pesado
            $table->date('tacografo_vencimento')->nullable();        // afericao do tacografo
            $table->index(['tenant_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'placa']);
            $table->dropColumn([
                'grupo', 'veiculo_pesado', 'modelo', 'cor', 'chassi', 'ano_modelo', 'placa', 'renavam',
                'licenciamento_vencimento', 'seguro_seguradora', 'seguro_apolice', 'seguro_vencimento',
                'tacografo_numero', 'tacografo_vencimento',
            ]);
        });
    }
};
