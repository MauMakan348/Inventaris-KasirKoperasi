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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('channel', 10);
            $table->string('status', 15);
            $table->string('payment_method', 15);
            $table->bigInteger('total_amount');
            $table->bigInteger('amount_paid')->nullable();
            $table->bigInteger('change_amount')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_channel_check CHECK (channel IN ('POS', 'ONLINE'))"
        );
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('COMPLETED'))"
        );
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('CASH', 'TRANSFER'))"
        );
        DB::statement(
            'ALTER TABLE orders ADD CONSTRAINT orders_total_amount_non_negative_check CHECK (total_amount >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
