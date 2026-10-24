<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id', 'payments_order_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('order_id', 'payments_order_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_id_index');
        });
    }
};