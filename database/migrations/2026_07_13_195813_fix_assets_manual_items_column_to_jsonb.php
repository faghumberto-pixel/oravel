<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * assets.manual_items foi criada como `json` (nao `jsonb`) -- Postgres nao
 * tem operador de igualdade/hash pra `json`, entao qualquer SELECT DISTINCT
 * ou GROUP BY tocando assets.* falha com "could not identify an equality
 * operator for type json" (42883). E' exatamente o que Filament gera pra
 * campos Select ->multiple()->relationship() com preload (ex: campo
 * "assets" em SolicitacaoLocacaoResource). Colunas irmas (checklist,
 * details) ja usam jsonb -- so esta ficou pra tras.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE assets ALTER COLUMN manual_items TYPE jsonb USING manual_items::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assets ALTER COLUMN manual_items TYPE json USING manual_items::json');
    }
};
