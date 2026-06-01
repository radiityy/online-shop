<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('courier')->nullable();
            $table->string('service')->nullable();
            $table->string('tracking_number')->nullable();

            $table->enum('status', [
                'not_shipped',
                'packed',
                'shipped',
                'delivered',
                'returned',
            ])->default('not_shipped');

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};