<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->boolean('blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id']);
            $table->index(['tenant_id', 'employee_id']);
        });

        // Trava real de negocio (nao so' validacao de formulario): antes de
        // gravar uma alocacao com blocked=false, confere se o colaborador
        // tem certificacao vigente pra TODA norma exigida pela categoria do
        // ativo. Sem certificacao valida, a linha entra bloqueada em vez de
        // ser rejeitada -- preserva o historico de "tentativa negada" pra
        // auditoria, ver artefato "Organograma Padrão Oravel" e RFC HR Field.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_equipment_allocation_nr_block()
            RETURNS TRIGGER AS $$
            DECLARE
                missing_norma TEXT;
            BEGIN
                IF NEW.blocked = false THEN
                    SELECT req.norma INTO missing_norma
                    FROM nr_requirements_by_category req
                    JOIN assets a ON a.asset_category_id = req.asset_category_id
                    WHERE a.id = NEW.asset_id
                      AND req.tenant_id = NEW.tenant_id
                      AND NOT EXISTS (
                          SELECT 1 FROM employee_certifications ec
                          WHERE ec.employee_id = NEW.employee_id
                            AND ec.norma = req.norma
                            AND ec.data_validade >= CURRENT_DATE
                      )
                    LIMIT 1;

                    IF missing_norma IS NOT NULL THEN
                        NEW.blocked := true;
                        NEW.blocked_reason := 'certificacao_ausente_ou_vencida:' || missing_norma;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_equipment_allocation_nr_block
                BEFORE INSERT OR UPDATE ON equipment_allocations
                FOR EACH ROW
                EXECUTE FUNCTION enforce_equipment_allocation_nr_block();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_equipment_allocation_nr_block ON equipment_allocations');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_equipment_allocation_nr_block');
        Schema::dropIfExists('equipment_allocations');
    }
};
