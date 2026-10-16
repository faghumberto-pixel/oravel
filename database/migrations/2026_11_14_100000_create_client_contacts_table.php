<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vários contatos por cliente (espelho de crm_lead_contacts). O contact_name
 * antigo vira o contato principal, mas a coluna continua existindo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
        });

        DB::table('clients')
            ->whereNotNull('contact_name')->whereRaw("trim(contact_name) <> ''")
            ->orderBy('id')
            ->each(function ($client) {
                DB::table('client_contacts')->insert([
                    'id' => (string) Str::uuid(), 'tenant_id' => $client->tenant_id, 'client_id' => $client->id,
                    'name' => trim($client->contact_name), 'is_primary' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
