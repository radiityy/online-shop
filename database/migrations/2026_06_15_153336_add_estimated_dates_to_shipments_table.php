<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'estimated_packed_at')) {
                $table->timestamp('estimated_packed_at')->nullable()->after('last_tracked_at');
            }

            if (! Schema::hasColumn('shipments', 'estimated_shipped_at')) {
                $table->timestamp('estimated_shipped_at')->nullable()->after('estimated_packed_at');
            }

            if (! Schema::hasColumn('shipments', 'estimated_delivered_from')) {
                $table->date('estimated_delivered_from')->nullable()->after('estimated_shipped_at');
            }

            if (! Schema::hasColumn('shipments', 'estimated_delivered_to')) {
                $table->date('estimated_delivered_to')->nullable()->after('estimated_delivered_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'estimated_delivered_to')) {
                $table->dropColumn('estimated_delivered_to');
            }

            if (Schema::hasColumn('shipments', 'estimated_delivered_from')) {
                $table->dropColumn('estimated_delivered_from');
            }

            if (Schema::hasColumn('shipments', 'estimated_shipped_at')) {
                $table->dropColumn('estimated_shipped_at');
            }

            if (Schema::hasColumn('shipments', 'estimated_packed_at')) {
                $table->dropColumn('estimated_packed_at');
            }
        });
    }
};