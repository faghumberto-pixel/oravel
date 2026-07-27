<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma linha por destinatario interno de um EmailMessage -- read_at e' o
     * que da' "lida/nao lida" por pessoa e o que permite a query real de
     * "Recebidos" (via join), coisa que um array json na propria
     * email_messages nao permitiria.
     */
    public function up(): void
    {
        Schema::create('email_message_recipients', function (Blueprint $table) {
            // Sem id proprio de proposito: e' pivot pura (BelongsToMany::sync()
            // so' popula as FKs + timestamps, nao um id customizado sem
            // default -- ja' quebrou uma vez com uuid('id')->primary() aqui).
            $table->foreignUuid('email_message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->primary(['email_message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_message_recipients');
    }
};
