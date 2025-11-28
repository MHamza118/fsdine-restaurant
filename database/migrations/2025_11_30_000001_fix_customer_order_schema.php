<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            // Ensure structured order items/notes columns exist
            if (!Schema::hasColumn('table_orders', 'order_items')) {
                $table->json('order_items')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('table_orders', 'order_notes')) {
                $table->text('order_notes')->nullable()->after('order_items');
            }

            if (!Schema::hasColumn('table_orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('order_notes');
            }

            if (!Schema::hasColumn('table_orders', 'submission_source')) {
                $table->string('submission_source')->default('admin_manual')->after('total_amount');
            }
        });

        Schema::table('table_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('table_mappings', 'submission_id')) {
                $table->string('submission_id')->nullable()->after('order_number');
            }

            if (!Schema::hasColumn('table_mappings', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submission_id');
            }

            if (!Schema::hasColumn('table_mappings', 'update_count')) {
                $table->unsignedInteger('update_count')->default(0)->after('notes');
            }
        });

        // Ensure `source` column can store values like customer_web_order
        if (Schema::hasColumn('table_mappings', 'source')) {
            DB::statement("ALTER TABLE `table_mappings` MODIFY `source` VARCHAR(50) DEFAULT 'customer'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            if (Schema::hasColumn('table_orders', 'submission_source')) {
                $table->dropColumn('submission_source');
            }

            if (Schema::hasColumn('table_orders', 'total_amount')) {
                $table->dropColumn('total_amount');
            }

            if (Schema::hasColumn('table_orders', 'order_notes')) {
                $table->dropColumn('order_notes');
            }

            if (Schema::hasColumn('table_orders', 'order_items')) {
                $table->dropColumn('order_items');
            }
        });

        Schema::table('table_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('table_mappings', 'update_count')) {
                $table->dropColumn('update_count');
            }

            if (Schema::hasColumn('table_mappings', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }

            if (Schema::hasColumn('table_mappings', 'submission_id')) {
                $table->dropColumn('submission_id');
            }
        });

        // Revert source column to original enum definition if needed
        if (Schema::hasColumn('table_mappings', 'source')) {
            DB::statement("ALTER TABLE `table_mappings` MODIFY `source` ENUM('customer', 'admin') DEFAULT 'customer'");
        }
    }
};

