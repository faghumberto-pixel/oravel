<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('attachments', function (Blueprint $table) {
            // Ajustado para UUID para manter a consistência industrial do Oravel
            $table->uuid('id')->primary();
            
            // Relacionamento com a OS (Ajustado para foreignUuid)
            $table->foreignUuid('maintenance_order_id')
                  ->constrained()
                  ->onDelete('cascade'); // Se apagar a OS, apaga os anexos

            // --- ADITIVO: Multitenancy (Essencial para Pactual vs JR Diesel) ---
            $table->foreignUuid('tenant_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Dados do Arquivo (Mantidos integralmente)
            $table->string('file_path'); // Caminho no storage
            $table->string('file_name'); // Nome original ou gerado
            $table->string('mime_type')->nullable(); // ex: image/jpeg

            // Contexto da Evidência (Mantido integralmente)
            $table->string('evidence_type')->index(); 

            // Dados de Validação (Mantidos integralmente)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // --- ADITIVO: Endereço Humano (Pulo do Gato para o Laudo) ---
            $table->string('address')->nullable(); 

            // Data/Hora de captura real (Mantido integralmente)
            $table->timestamp('captured_at')->nullable(); 

            $table->timestamps(); // created_at e updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('attachments');
    }
}