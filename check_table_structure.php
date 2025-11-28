<?php

/**
 * Database Structure Diagnostic Script
 * Run this script to check if your production database has all required columns
 *
 * Usage: php check_table_structure.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Database Structure Checker ===\n";
echo "Checking database connection...\n\n";

try {
    DB::connection()->getPdo();
    echo "✓ Database connection successful\n\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Checking table_orders structure ===\n";

$requiredColumns = [
    'id',
    'order_number',
    'unique_identifier',
    'mapping_id',
    'table_number',
    'area',
    'customer_name',
    'status',
    'notes',
    'order_items',
    'order_notes',
    'total_amount',
    'submission_source',
    'created_at',
    'updated_at'
];

$tableExists = Schema::hasTable('table_orders');

if (!$tableExists) {
    echo "✗ Table 'table_orders' does not exist!\n";
    echo "Run: php artisan migrate\n";
    exit(1);
}

echo "✓ Table 'table_orders' exists\n\n";

$missingColumns = [];
$existingColumns = [];

foreach ($requiredColumns as $column) {
    if (Schema::hasColumn('table_orders', $column)) {
        $existingColumns[] = $column;
        echo "✓ Column '{$column}' exists\n";
    } else {
        $missingColumns[] = $column;
        echo "✗ Column '{$column}' is MISSING\n";
    }
}

echo "\n=== Checking table_mappings structure ===\n";

$requiredMappingColumns = [
    'id',
    'order_number',
    'submission_id',
    'submitted_at',
    'table_number',
    'area',
    'status',
    'source',
    'delivered_by',
    'delivered_at',
    'cleared_at',
    'clear_reason',
    'notes',
    'update_count',
    'created_at',
    'updated_at'
];

$mappingTableExists = Schema::hasTable('table_mappings');

if (!$mappingTableExists) {
    echo "✗ Table 'table_mappings' does not exist!\n";
    echo "Run: php artisan migrate\n";
    exit(1);
}

echo "✓ Table 'table_mappings' exists\n\n";

$missingMappingColumns = [];
$existingMappingColumns = [];

foreach ($requiredMappingColumns as $column) {
    if (Schema::hasColumn('table_mappings', $column)) {
        $existingMappingColumns[] = $column;
        echo "✓ Column '{$column}' exists\n";
    } else {
        $missingMappingColumns[] = $column;
        echo "✗ Column '{$column}' is MISSING\n";
    }
}

echo "\n=== Checking table_notifications structure ===\n";

$notificationTableExists = Schema::hasTable('table_notifications');

if (!$notificationTableExists) {
    echo "✗ Table 'table_notifications' does not exist!\n";
    echo "Run: php artisan migrate\n";
} else {
    echo "✓ Table 'table_notifications' exists\n";
}

echo "\n=== Summary ===\n";

if (empty($missingColumns) && empty($missingMappingColumns)) {
    echo "✓ All required columns are present!\n";
    echo "✓ Database structure is correct!\n\n";
    echo "The database structure is not the issue.\n";
    echo "Check the Laravel logs for the actual error:\n";
    echo "  tail -f storage/logs/laravel.log\n";
    exit(0);
} else {
    echo "✗ Database structure issues found!\n\n";

    if (!empty($missingColumns)) {
        echo "Missing columns in 'table_orders':\n";
        foreach ($missingColumns as $column) {
            echo "  - {$column}\n";
        }
        echo "\n";
    }

    if (!empty($missingMappingColumns)) {
        echo "Missing columns in 'table_mappings':\n";
        foreach ($missingMappingColumns as $column) {
            echo "  - {$column}\n";
        }
        echo "\n";
    }

    echo "SOLUTION: Run these commands on your production server:\n";
    echo "  php artisan migrate:status\n";
    echo "  php artisan migrate --force\n";
    echo "\nThis will add all missing columns.\n";
    exit(1);
}

// Additional checks
echo "\n=== Additional Database Checks ===\n";

try {
    // Check if we can query the tables
    $ordersCount = DB::table('table_orders')->count();
    echo "✓ Can query table_orders (count: {$ordersCount})\n";

    $mappingsCount = DB::table('table_mappings')->count();
    echo "✓ Can query table_mappings (count: {$mappingsCount})\n";

    // Check JSON column support
    if (Schema::hasColumn('table_orders', 'order_items')) {
        $testQuery = DB::table('table_orders')
            ->whereNotNull('order_items')
            ->first();
        echo "✓ JSON column 'order_items' is accessible\n";
    }

} catch (\Exception $e) {
    echo "✗ Error querying tables: " . $e->getMessage() . "\n";
}

echo "\n=== Migration Status ===\n";
echo "Run 'php artisan migrate:status' to see which migrations have been run.\n";
echo "\n";
