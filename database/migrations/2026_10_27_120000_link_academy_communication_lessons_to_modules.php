<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As aulas do curso "Comunicacao" nao tinham modulo amarrado e apareciam pra qualquer contrato.
 * So' preenche feature_key vazio (nao sobrescreve o que alguem ajustou na Central).
 */
return new class extends Migration
{
    private const LINKS = [
        '/comunicacao/fotos-evidencias.html' => 'tabela_maintenance_orders',
        '/comunicacao/descricao-audio.html' => 'modulo_chat',
    ];

    public function up(): void
    {
        foreach (self::LINKS as $path => $feature) {
            DB::table('lessons')
                ->where('page_url', 'like', '%'.$path)
                ->whereNull('feature_key')
                ->update(['feature_key' => $feature]);
        }
    }

    public function down(): void
    {
        foreach (self::LINKS as $path => $feature) {
            DB::table('lessons')
                ->where('page_url', 'like', '%'.$path)
                ->where('feature_key', $feature)
                ->update(['feature_key' => null]);
        }
    }
};
