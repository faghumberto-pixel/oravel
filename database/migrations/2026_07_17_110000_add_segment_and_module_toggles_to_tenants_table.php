<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'segment')) {
                $table->string('segment')->nullable()->after('plan_id');
            }
            if (! Schema::hasColumn('tenants', 'enabled_modules')) {
                $table->json('enabled_modules')->nullable()->after('features');
            }
            if (! Schema::hasColumn('tenants', 'ui_customizations')) {
                $table->json('ui_customizations')->nullable()->after('enabled_modules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['segment', 'enabled_modules', 'ui_customizations'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
