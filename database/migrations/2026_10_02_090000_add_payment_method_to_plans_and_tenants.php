<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forma de pagar a mensalidade: 'cartao' (checkout recorrente automático) ou
        // 'boleto_pix' (assinatura mensal por boleto/Pix, enviada por e-mail).
        Schema::table('plans', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cartao');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('payment_method'));
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('payment_method'));
    }
};
