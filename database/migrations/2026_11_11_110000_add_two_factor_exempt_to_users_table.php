<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Excecao individual a obrigatoriedade do 2FA. So a Central (super admin)
        // altera isso; fora do $fillable de proposito. Nao muda o escopo de tenant.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_exempt')->default(false);
            $table->string('two_factor_exempt_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_exempt', 'two_factor_exempt_reason']);
        });
    }
};
