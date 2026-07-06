<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibilidade peca <-> Grupo de Ativo (nao por Ativo individual --
 * decisao explicita: casa com o template de preventiva, que tambem e
 * por Grupo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_checklist_group', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('material_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('checklist_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['material_id', 'checklist_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_checklist_group');
    }
};
