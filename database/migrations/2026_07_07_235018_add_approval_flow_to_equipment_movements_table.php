<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->foreignUuid('maintenance_order_id')->nullable()->change();

            if (! Schema::hasColumn('equipment_movements', 'solicitacao_locacao_id')) {
                $table->foreignUuid('solicitacao_locacao_id')->nullable()->after('maintenance_order_id')
                    ->constrained('solicitacoes_locacao')->nullOnDelete();
            }
            if (! Schema::hasColumn('equipment_movements', 'approved_by_user_id')) {
                $table->foreignUuid('approved_by_user_id')->nullable()->after('completed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('equipment_movements', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (! Schema::hasColumn('equipment_movements', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('equipment_movements', 'qr_token')) {
                $table->string('qr_token')->nullable()->unique()->after('rejected_reason');
            }
        });

        // Item de checklist de patolas/estabilizadores -- generico o
        // suficiente pra nao soar estranho em ativos sem patolas ("se
        // aplicavel"), mas cobre o caso de guindastes/plataformas.
        if (! DB::table('equipment_movement_item_templates')->where('label', 'like', '%patolas%')->exists()) {
            DB::table('equipment_movement_item_templates')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'mobilizacao',
                'section' => 'Segurança',
                'label' => 'Inspeção de patolas/estabilizadores (se aplicável)',
                'sort_order' => 10,
                'requires_photo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitacao_locacao_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approved_at', 'rejected_reason', 'qr_token']);
            $table->foreignUuid('maintenance_order_id')->nullable(false)->change();
        });
    }
};
