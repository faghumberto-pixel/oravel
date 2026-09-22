<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentação técnica por equipamento NR-13 (prontuário, projeto, PMOC quando aplicável,
 * certificados de inspeção) -- mesmo padrão de FleetVehicleDocument/EmployeeCertification:
 * tipo + data_emissao/data_validade + arquivo (Media Library, singleFile).
 *
 * NÃO tem relação com App\Models\Pmoc (isso é o PMOC de ar-condicionado da RE-ANVISA 09/2003 --
 * outro domínio, outra tabela). Aqui "pmoc" é só um dos valores possíveis de `tipo`, pro caso
 * de o tenant usar esse nome pra algum plano de manutenção do próprio equipamento NR-13.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr13_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->string('tipo'); // prontuario | projeto | pmoc | certificado_inspecao | laudo | outro
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable(); // nullable: prontuário/projeto não têm validade, ex.: certificado tem
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id']);
            $table->index(['tipo', 'data_validade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nr13_documents');
    }
};
