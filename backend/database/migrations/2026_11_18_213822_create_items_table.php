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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('name', 150);
            $table->bigInteger('price');
            $table->integer('stock');
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE items ADD CONSTRAINT items_price_positive_check CHECK (price > 0)'
        );
        DB::statement(
            'ALTER TABLE items ADD CONSTRAINT items_stock_non_negative_check CHECK (stock >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
