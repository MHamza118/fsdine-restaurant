<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the source column from enum to varchar to support more values
        DB::statement("ALTER TABLE `table_mappings` MODIFY `source` VARCHAR(50) DEFAULT 'customer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to enum with original values
        DB::statement("ALTER TABLE `table_mappings` MODIFY `source` ENUM('customer', 'admin') DEFAULT 'customer'");
    }
};
