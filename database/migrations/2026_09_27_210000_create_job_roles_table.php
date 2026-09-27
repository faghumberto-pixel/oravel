<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Ponto de partida pra cada tenant (novo cadastro, editavel/apagavel
    // depois) -- nao e' mais uma lista fixa no codigo, so' o seed inicial.
    private const DEFAULT_NAMES = [
        'Vendedor', 'Técnico', 'Analista', 'Assistente',
        'Supervisor', 'Coordenador', 'Gerente', 'Diretor',
    ];

    public function up(): void
    {
        Schema::create('job_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignUuid('job_role_id')->nullable()->after('role_title')->constrained('job_roles')->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('job_roles')->insert(
                collect(self::DEFAULT_NAMES)->map(fn (string $name) => [
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_role_id');
        });

        Schema::dropIfExists('job_roles');
    }
};
