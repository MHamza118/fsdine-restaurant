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
            $table->json('order_items')->nullable()->after('notes');
            $table->text('order_notes')->nullable()->after('order_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn(['order_items', 'order_notes']);
        });
    }
};
