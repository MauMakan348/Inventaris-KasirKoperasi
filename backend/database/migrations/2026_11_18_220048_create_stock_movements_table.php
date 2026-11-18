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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity_change');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['item_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('SALE_POS', 'SALE_ONLINE', 'RESTOCK', 'OPNAME'))"
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_change_non_zero_check CHECK (quantity_change <> 0)'
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_stock_before_non_negative_check CHECK (stock_before >= 0)'
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_stock_after_non_negative_check CHECK (stock_after >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
