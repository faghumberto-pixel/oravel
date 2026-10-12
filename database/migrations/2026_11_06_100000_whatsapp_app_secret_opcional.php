<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Com o "Conectar WhatsApp" (cadastro incorporado) a empresa usa o app da Oravel e não tem segredo próprio.
        DB::statement('ALTER TABLE tenant_whatsapp_settings ALTER COLUMN app_secret DROP NOT NULL');
    }

    public function down(): void
    {
        // Sem volta segura: linhas sem segredo próprio existiriam.
    }
};
