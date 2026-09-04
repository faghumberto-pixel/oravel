<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Morphs para Contract, MaintenanceOrder
            $table->string('signable_type');
            $table->uuid('signable_id');

            // Token único para acesso público seguro
            $table->string('token', 64)->unique()->index();

            // Informações do signatário
            $table->string('signer_name');
            $table->string('signer_document')->nullable(); // CPF/CNPJ
            $table->string('signer_email')->nullable();
            $table->string('signer_phone')->nullable();

            // Assinatura em PNG (caminho no Storage)
            $table->string('signature_image_path')->nullable();

            // Metadados de assinatura
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('geolocation')->nullable(); // {lat, lng, accuracy}

            // Status e timestamps
            $table->timestamp('signed_at')->nullable();
            $table->enum('status', ['pending', 'signed', 'expired', 'canceled'])->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->string('document_hash')->nullable(); // SHA-256 do PDF

            // Tenant scoping
            $table->uuid('tenant_id')->index();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['signable_type', 'signable_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
