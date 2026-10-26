<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_reference', 100)->nullable();
            $table->text('refund_note')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('account_reminded_at')->nullable();
            $table->unsignedTinyInteger('account_reminders_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'refund_reference',
                'refund_note',
                'refunded_at',
                'refunded_by',
                'account_reminded_at',
                'account_reminders_sent',
            ]);
        });
    }
};