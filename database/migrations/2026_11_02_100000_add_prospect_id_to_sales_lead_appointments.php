<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compromisso da Programacao pode nascer de um Prospect (campo "Proximo contato"),
     * que ainda nao e' lead: sales_lead_id passa a ser opcional e ganha prospect_id.
     */
    public function up(): void
    {
        // SQLite nao suporta ALTER COLUMN DROP NOT NULL diretamente;
        // recriamos a tabela para remover a constraint.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('sales_lead_appointments', function (Blueprint $table) {
                // Adiciona coluna temporaria nullable, copia dados, recria tabela
            });
            // Recriar tabela sem NOT NULL em sales_lead_id
            DB::statement('CREATE TABLE sales_lead_appointments_new (
                id TEXT PRIMARY KEY,
                sales_lead_id TEXT NULL,
                assigned_user_id TEXT NULL,
                title TEXT NOT NULL,
                notes TEXT NULL,
                type TEXT NOT NULL DEFAULT "demonstracao",
                scheduled_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pendente",
                completed_at TEXT NULL,
                last_alerted_at TEXT NULL,
                prospect_id TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (sales_lead_id) REFERENCES sales_leads(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE
            )');
            DB::statement('INSERT INTO sales_lead_appointments_new SELECT id, sales_lead_id, assigned_user_id, title, notes, type, scheduled_at, status, completed_at, last_alerted_at, NULL, created_at, updated_at FROM sales_lead_appointments');
            DB::statement('DROP TABLE sales_lead_appointments');
            DB::statement('ALTER TABLE sales_lead_appointments_new RENAME TO sales_lead_appointments');
            DB::statement('CREATE INDEX idx_sla_status_scheduled ON sales_lead_appointments(status, scheduled_at)');
            DB::statement('CREATE INDEX idx_sla_sales_lead ON sales_lead_appointments(sales_lead_id)');
        } else {
            DB::statement('ALTER TABLE sales_lead_appointments ALTER COLUMN sales_lead_id DROP NOT NULL');
            Schema::table('sales_lead_appointments', function (Blueprint $table) {
                $table->foreignUuid('prospect_id')->nullable()->after('sales_lead_id')->constrained('prospects')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_lead_appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prospect_id');
        });
    }
};
