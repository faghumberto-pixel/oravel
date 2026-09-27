<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            // Nullable no banco pra nao quebrar as 8 funcoes ja semeadas sem
            // departamento (migration anterior) -- o form do Resource exige
            // preenchimento daqui pra frente.
            $table->foreignUuid('department_id')->nullable()->after('name')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
