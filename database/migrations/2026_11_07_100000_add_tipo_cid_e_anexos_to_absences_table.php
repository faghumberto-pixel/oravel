<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->string('type')->default('falta_justificada')->after('employee_id');
            $table->string('cid_code', 10)->nullable()->after('reason');
            $table->text('details')->nullable()->after('cid_code');
            $table->json('attachments')->nullable()->after('attachment_path');
            $table->index(['tenant_id', 'employee_id', 'start_date']);
        });

        // Registros antigos tinham um único anexo em attachment_path: passa para a lista nova.
        DB::table('absences')->whereNotNull('attachment_path')->orderBy('created_at')->each(function ($a) {
            DB::table('absences')->where('id', $a->id)->update(['attachments' => json_encode([$a->attachment_path])]);
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'employee_id', 'start_date']);
            $table->dropColumn(['type', 'cid_code', 'details', 'attachments']);
        });
    }
};
