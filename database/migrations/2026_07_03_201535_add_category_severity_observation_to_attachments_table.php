<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('attachments', 'category')) {
                $table->string('category')->nullable()->after('evidence_type');
            }
            if (!Schema::hasColumn('attachments', 'severity')) {
                $table->string('severity')->nullable()->default('ok')->after('category');
            }
            if (!Schema::hasColumn('attachments', 'observation')) {
                $table->text('observation')->nullable()->after('severity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['category', 'severity', 'observation']);
        });
    }
};
