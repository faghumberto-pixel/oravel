<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('checklist_templates', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};