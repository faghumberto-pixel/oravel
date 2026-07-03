<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->uuid('material_category_id')->nullable()->after('tenant_id');
            $table->foreign('material_category_id')->references('id')->on('material_categories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['material_category_id']);
            $table->dropColumn('material_category_id');
        });

        Schema::dropIfExists('material_categories');
    }
};
