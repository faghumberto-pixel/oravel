<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement('
                CREATE TABLE IF NOT EXISTS maintenance_order_checklists_new AS
                SELECT * FROM maintenance_order_checklists
            ');
            DB::statement('DROP TABLE IF EXISTS maintenance_order_checklists');
            DB::statement('ALTER TABLE maintenance_order_checklists_new RENAME TO maintenance_order_checklists');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            DB::statement('ALTER TABLE maintenance_order_checklists ALTER COLUMN maintenance_order_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Sem ação necessária no down para SQLite
        } else {
            DB::statement('ALTER TABLE maintenance_order_checklists ALTER COLUMN maintenance_order_id SET NOT NULL');
        }
    }
};