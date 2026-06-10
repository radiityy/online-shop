<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY order_status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY shipping_status VARCHAR(50) NOT NULL DEFAULT 'not_shipped'");

        DB::statement("ALTER TABLE payments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE shipments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'not_shipped'");
    }

    public function down(): void
    {
        DB::statement("UPDATE orders SET payment_status = 'pending' WHERE payment_status NOT IN ('pending', 'paid', 'failed', 'expired', 'refunded')");
        DB::statement("UPDATE orders SET order_status = 'pending' WHERE order_status NOT IN ('pending', 'processing', 'completed', 'cancelled')");
        DB::statement("UPDATE orders SET shipping_status = 'not_shipped' WHERE shipping_status NOT IN ('not_shipped', 'shipped', 'delivered')");

        DB::statement("UPDATE payments SET status = 'pending' WHERE status NOT IN ('pending', 'paid', 'failed', 'expired', 'refunded')");
        DB::statement("UPDATE shipments SET status = 'not_shipped' WHERE status NOT IN ('not_shipped', 'shipped', 'delivered')");

        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'paid', 'failed', 'expired', 'refunded') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY order_status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY shipping_status ENUM('not_shipped', 'shipped', 'delivered') NOT NULL DEFAULT 'not_shipped'");

        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending', 'paid', 'failed', 'expired', 'refunded') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE shipments MODIFY status ENUM('not_shipped', 'shipped', 'delivered') NOT NULL DEFAULT 'not_shipped'");
    }
};