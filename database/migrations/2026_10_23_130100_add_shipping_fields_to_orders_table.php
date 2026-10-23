<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('area_id', 64)->nullable()->after('district');
            $table->decimal('shipping_insurance_fee', 12, 2)->default(0)->after('shipping_cost');
            $table->string('shipping_courier_code', 32)->nullable()->after('shipping_insurance_fee');
            $table->string('shipping_courier_name', 64)->nullable()->after('shipping_courier_code');
            $table->string('shipping_service_code', 32)->nullable()->after('shipping_courier_name');
            $table->string('shipping_service_name', 100)->nullable()->after('shipping_service_code');
            $table->string('shipping_duration', 50)->nullable()->after('shipping_service_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'area_id',
                'shipping_insurance_fee',
                'shipping_courier_code',
                'shipping_courier_name',
                'shipping_service_code',
                'shipping_service_name',
                'shipping_duration',
            ]);
        });
    }
};
