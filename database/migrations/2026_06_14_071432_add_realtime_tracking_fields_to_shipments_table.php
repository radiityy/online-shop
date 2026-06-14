<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'courier_code')) {
                $table->string('courier_code')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'tracking_status')) {
                $table->string('tracking_status')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'tracking_history')) {
                $table->json('tracking_history')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'tracking_raw_response')) {
                $table->json('tracking_raw_response')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'last_tracked_at')) {
                $table->timestamp('last_tracked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'courier_code')) {
                $table->dropColumn('courier_code');
            }

            if (Schema::hasColumn('shipments', 'tracking_status')) {
                $table->dropColumn('tracking_status');
            }

            if (Schema::hasColumn('shipments', 'tracking_history')) {
                $table->dropColumn('tracking_history');
            }

            if (Schema::hasColumn('shipments', 'tracking_raw_response')) {
                $table->dropColumn('tracking_raw_response');
            }

            if (Schema::hasColumn('shipments', 'last_tracked_at')) {
                $table->dropColumn('last_tracked_at');
            }
        });
    }
};