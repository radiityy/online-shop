<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('biteship_order_id', 64)->nullable()->unique()->after('tracking_number');
            $table->string('biteship_status', 32)->nullable()->after('biteship_order_id');
            $table->unsignedInteger('biteship_price')->nullable()->after('biteship_status');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['biteship_order_id']);
            $table->dropColumn(['biteship_order_id', 'biteship_status', 'biteship_price']);
        });
    }
};