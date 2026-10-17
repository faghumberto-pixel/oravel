<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('pacote', 'crm')->update(['pacote' => 'vendas']);
    }

    public function down(): void
    {
        DB::table('plans')->where('pacote', 'vendas')->update(['pacote' => 'crm']);
    }
};
