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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 150);
            $table->integer('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('subtotal');

            $table->index('order_id');
            $table->index('item_id');
        });

        DB::statement(
            'ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive_check CHECK (quantity > 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
