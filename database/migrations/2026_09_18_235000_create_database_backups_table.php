<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de backups diarios do banco (2026_09_18, criado apos o incidente de
 * perda de dados de PROD -- ver scripts/backup-prod-database.sh). Nao e'
 * o backup em si (isso e' o arquivo .dump no servidor) -- e' so' o
 * registro pra aparecer numa tela do painel Central com filtro por
 * tenant/data. Global/nao-tenant-scoped, igual site_visits/announcements:
 * um dump cobre TODOS os tenants de uma vez, entao tenant_names guarda a
 * lista de quem estava presente naquele momento (pra filtrar "backups que
 * incluem o tenant X").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('tenant_count')->default(0);
            $table->json('tenant_names')->nullable();
            $table->string('status')->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
