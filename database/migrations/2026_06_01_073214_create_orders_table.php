<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();

            $table->string('order_code')->unique();

            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('postal_code', 10)->nullable();
            $table->text('full_address');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'expired',
                'refunded',
            ])->default('pending');

            $table->enum('order_status', [
                'pending',
                'processing',
                'completed',
                'cancelled',
            ])->default('pending');

            $table->enum('shipping_status', [
                'not_shipped',
                'packed',
                'shipped',
                'delivered',
            ])->default('not_shipped');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'order_status']);
            $table->index(['payment_status', 'shipping_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};