<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de entrega/emprestimo de EPI por colaborador (compliance NR-6).
 * Cada ciclo de entrega/devolucao e' uma linha nova (nunca reaproveita uma
 * linha existente pra um novo emprestimo) -- e' isso que garante
 * rastreabilidade completa em caso de acidente: "quais EPIs este
 * colaborador tinha e desde quando" e' so' filtrar por employee_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epi_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('material_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('internal_unit_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->string('reason');
            $table->string('ownership_mode');
            $table->string('status')->default('ativo');

            $table->timestamp('delivered_at');
            $table->foreignUuid('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignUuid('returned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('returned_condition')->nullable();

            $table->uuid('replaced_by_delivery_id')->nullable();
            $table->foreignUuid('material_stock_movement_id')->nullable()->constrained('material_stock_movements')->nullOnDelete();

            // Trava real de negocio (nao so' validacao de formulario), mesma
            // logica de equipment_allocations.blocked/blocked_reason: uma
            // trigger confere o CA do Material entregue antes de gravar.
            $table->boolean('blocked')->default(false);
            $table->string('blocked_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'material_id']);
            $table->index(['employee_id', 'status']);
        });

        // replaced_by_delivery_id auto-referencia epi_deliveries.id -- a FK
        // precisa vir depois da tabela criada (com a primary key ja
        // commitada), senao o Postgres rejeita "no unique constraint
        // matching given keys" (confirmado empiricamente rodando a
        // migration original com o ->constrained() dentro do Schema::create
        // acima; mesma causa-raiz do padrao ja usado em parent_id/
        // parent_os_id de maintenance_orders, que tambem so' ganham FK via
        // Schema::table() numa migration separada).
        Schema::table('epi_deliveries', function (Blueprint $table) {
            $table->foreign('replaced_by_delivery_id')->references('id')->on('epi_deliveries')->nullOnDelete();
        });

        // Antes de gravar uma entrega com blocked=false, confere se o CA do
        // Material entregue esta vigente hoje. Sem CA valido, a linha entra
        // bloqueada em vez de ser rejeitada -- preserva a tentativa negada
        // pra auditoria (ver enforce_equipment_allocation_nr_block, mesmo
        // padrao). Roda em UPDATE tambem: um comando diario forca o re-save
        // das entregas ativas quando um CA vence, fazendo o `blocked` virar
        // true retroativamente nas entregas que estao em uso -- e' esse
        // dado que alimenta o dashboard de "EPI vencido em uso".
        //
        // PL/pgSQL e' sintaxe exclusiva do Postgres -- a suite de testes
        // roda em sqlite in-memory (ver phpunit.xml), que rejeita esse
        // DDL com erro de sintaxe (confirmado empiricamente). O guard
        // abaixo mantem o comportamento real em produção (Postgres) sem
        // quebrar `php artisan test`; em sqlite o bloqueio de CA vencido
        // fica só a cargo da validação de formulário no Resource.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_epi_delivery_ca_block()
            RETURNS TRIGGER AS $$
            DECLARE
                ca_vencido BOOLEAN;
                numero_ca TEXT;
            BEGIN
                IF NEW.blocked = false THEN
                    SELECT (spec.ca_validade < CURRENT_DATE), spec.ca_number
                    INTO ca_vencido, numero_ca
                    FROM epi_specifications spec
                    WHERE spec.material_id = NEW.material_id
                      AND spec.deleted_at IS NULL
                    LIMIT 1;

                    IF ca_vencido THEN
                        NEW.blocked := true;
                        NEW.blocked_reason := 'ca_vencido:' || COALESCE(numero_ca, 's/n');
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_epi_delivery_ca_block
                BEFORE INSERT OR UPDATE ON epi_deliveries
                FOR EACH ROW
                EXECUTE FUNCTION enforce_epi_delivery_ca_block();
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_epi_delivery_ca_block ON epi_deliveries');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_epi_delivery_ca_block');
        }

        Schema::dropIfExists('epi_deliveries');
    }
};
