<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('asaas_customer_id')->nullable()->after('plan_id');
            $table->string('asaas_subscription_id')->nullable()->after('asaas_customer_id');
            $table->enum('asaas_status', ['pending', 'synced', 'error'])->default('pending')->after('asaas_subscription_id');
            $table->timestamp('asaas_synced_at')->nullable()->after('asaas_status');
            $table->index('asaas_customer_id');
            $table->index('asaas_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['asaas_customer_id']);
            $table->dropIndex(['asaas_subscription_id']);
            $table->dropColumn([
                'asaas_customer_id',
                'asaas_subscription_id',
                'asaas_status',
                'asaas_synced_at',
            ]);
        });
    }
};
