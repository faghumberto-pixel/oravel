<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->dropPrimary(['permission_id', 'model_id', 'model_type']);
            $table->dropColumn('model_id');
        });
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->uuid('model_id')->after('model_type');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->dropPrimary(['role_id', 'model_id', 'model_type']);
            $table->dropColumn('model_id');
        });
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->uuid('model_id')->after('model_type');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            $table->dropPrimary(['permission_id', 'model_id', 'model_type']);
            $table->dropColumn('model_id');
        });
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->after('model_type');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        $tableNames = config('permission.table_names');
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->dropPrimary(['role_id', 'model_id', 'model_type']);
            $table->dropColumn('model_id');
        });
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->after('model_type');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }
};
