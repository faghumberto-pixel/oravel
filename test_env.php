<?php
require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "DB_CONNECTION: " . config('database.default') . "\n";
echo "DB_DATABASE (sqlite): " . config('database.connections.sqlite.database') . "\n";
echo "DB_DATABASE (pgsql): " . config('database.connections.pgsql.database') . "\n";
echo "getenv DB_CONNECTION: " . getenv('DB_CONNECTION') . "\n";
echo "getenv DB_DATABASE: " . getenv('DB_DATABASE') . "\n";
