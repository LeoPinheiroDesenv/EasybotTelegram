<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Creating cache tables...\n\n";

try {
    // Verificar se a tabela já existe
    if (Schema::hasTable('cache')) {
        echo "Table 'cache' already exists.\n";
    } else {
        Schema::create('cache', function ($table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        echo "Table 'cache' created successfully!\n";
    }

    if (Schema::hasTable('cache_locks')) {
        echo "Table 'cache_locks' already exists.\n";
    } else {
        Schema::create('cache_locks', function ($table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        echo "Table 'cache_locks' created successfully!\n";
    }

    echo "\n✓ Cache tables are ready!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

