<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('table_orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('order_notes');
            }
            if (!Schema::hasColumn('table_orders', 'submission_source')) {
                $table->string('submission_source')->default('admin_manual')->after('total_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            if (Schema::hasColumn('table_orders', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('table_orders', 'submission_source')) {
                $table->dropColumn('submission_source');
            }
        });
    }
};
