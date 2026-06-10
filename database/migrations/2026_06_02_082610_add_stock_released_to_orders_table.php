<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'stock_released')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('stock_released')
                    ->default(false)
                    ->after('shipping_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'stock_released')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_released');
            });
        }
    }
};