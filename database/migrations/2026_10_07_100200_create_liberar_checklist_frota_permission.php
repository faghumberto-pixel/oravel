<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Permissao especial (nao e CRUD): quem pode liberar um veiculo bloqueado pelo checklist.
        // O administrador do cliente ja passa por regra propria; esta serve para perfis de acesso.
        Permission::firstOrCreate(['name' => 'liberar_checklist_frota', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Permission::where('name', 'liberar_checklist_frota')->where('guard_name', 'web')->delete();
    }
};
