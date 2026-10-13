<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FEATURE = 'tabela_client_feedbacks';

    public function up(): void
    {
        Schema::create('client_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('protocol', 32);
            $table->string('type', 20);
            $table->string('subject', 191);
            $table->text('description');
            $table->string('status', 20)->default('recebido');
            $table->text('response')->nullable();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'protocol']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'type', 'status']);
        });

        // Liga o módulo só nos Contratos (plans) que algum tenant usa de fato: sem isso a equipe da locadora
        // receberia o registro do cliente e não conseguiria abrir a tela para responder.
        $emUso = DB::table('tenants')->whereNotNull('plan_id')->distinct()->pluck('plan_id');

        foreach (DB::table('plans')->whereIn('id', $emUso)->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (! array_key_exists(self::FEATURE, $features) || $features[self::FEATURE] !== true) {
                $features[self::FEATURE] = true;
                DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_feedbacks');
    }
};
