<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'department_id')) {
                // Como as roles antigas não têm departamento, criamos como nullable
                $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }
};