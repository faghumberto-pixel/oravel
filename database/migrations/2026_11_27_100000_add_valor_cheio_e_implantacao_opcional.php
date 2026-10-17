<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valor cheio × recorrente e implantação opcional:
     *  - tenant_complementos.valor_cheio: o que o operador digita; valor_mensal passa a ser o COBRADO (cheio com desconto);
     *  - plans.implantacao_opcional: nas vitrines, o cliente pode contratar ou não a implantação;
     *  - tenants.implantacao_contratada: true/false conforme a escolha no checkout; nulo = clientes antigos (sem escolha registrada).
     */
    public function up(): void
    {
        Schema::table('tenant_complementos', function (Blueprint $table) {
            $table->decimal('valor_cheio', 10, 2)->nullable()->after('feature');
        });

        // Linhas de teste anteriores ao desconto: o cheio equivalente ao que já era cobrado.
        $desconto = (float) config('precos.desconto_recorrente_percentual', 40);
        DB::table('tenant_complementos')->whereNull('valor_cheio')->update(['valor_cheio' => DB::raw('ROUND(valor_mensal / '.(1 - $desconto / 100).', 2)')]);

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('implantacao_opcional')->default(false)->after('implementation_billing_mode');
        });

        DB::table('plans')->where('campaign_tag', 'vitrine')->update(['implantacao_opcional' => true]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('implantacao_contratada')->nullable()->after('implementation_billing_mode');
            // "Mensal sem compromisso" (boleto/Pix no valor cheio): cada pagamento libera 30 dias; vencido, o sistema bloqueia sozinho.
            $table->boolean('acesso_por_periodo')->default(false);
            $table->date('acesso_ate')->nullable();
            $table->string('acesso_pagamento_id', 60)->nullable(); // último pagamento que já estendeu o acesso (o Asaas repete eventos)
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['implantacao_contratada', 'acesso_por_periodo', 'acesso_ate', 'acesso_pagamento_id']));
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('implantacao_opcional'));
        Schema::table('tenant_complementos', fn (Blueprint $t) => $t->dropColumn('valor_cheio'));
    }
};
