<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PartsRequest usa BelongsToTenant (filtra toda query por tenant_id) mas a
     * tabela nunca teve essa coluna -- /admin/parts-requests dava 500 pra
     * qualquer tenant admin real. O CHECK constraint tambem so aceitava
     * pendente/entregue, enquanto o form/workflow sempre teve 3 estados
     * (Pendente -> Comprada/Pedida -> Entregue).
     */
    public function up(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::statement('UPDATE parts_requests pr SET tenant_id = mo.tenant_id FROM maintenance_orders mo WHERE mo.id = pr.maintenance_order_id');

        DB::statement('ALTER TABLE parts_requests ALTER COLUMN tenant_id SET NOT NULL');

        DB::statement('ALTER TABLE parts_requests DROP CONSTRAINT parts_requests_status_check');
        DB::statement("ALTER TABLE parts_requests ADD CONSTRAINT parts_requests_status_check CHECK (status::text = ANY (ARRAY['pendente'::character varying, 'pedida'::character varying, 'entregue'::character varying]::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parts_requests DROP CONSTRAINT parts_requests_status_check');
        DB::statement("ALTER TABLE parts_requests ADD CONSTRAINT parts_requests_status_check CHECK (status::text = ANY (ARRAY['pendente'::character varying, 'entregue'::character varying]::text[]))");

        Schema::table('parts_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
