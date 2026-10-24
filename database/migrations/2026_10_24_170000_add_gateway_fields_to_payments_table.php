<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 20)->nullable()->after('payment_method');
            $table->string('payment_type', 30)->nullable()->after('gateway');
            $table->string('bank', 20)->nullable()->after('payment_type');
            $table->string('gateway_order_id', 64)->nullable()->unique()->after('bank');
            $table->string('transaction_id', 64)->nullable()->index()->after('gateway_order_id');
            $table->string('va_number', 64)->nullable()->after('transaction_id');
            $table->text('qr_string')->nullable()->after('va_number');
            $table->string('qr_url', 500)->nullable()->after('qr_string');
            $table->string('deeplink_url', 500)->nullable()->after('qr_url');
            $table->decimal('fee', 12, 2)->default(0)->after('amount');
            $table->string('gateway_status', 30)->nullable()->after('status');
            $table->string('fraud_status', 20)->nullable()->after('gateway_status');
            $table->timestamp('expires_at')->nullable()->index()->after('paid_at');
            $table->timestamp('last_webhook_at')->nullable()->after('expires_at');
            $table->json('raw_response')->nullable()->after('last_webhook_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_order_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn([
                'gateway',
                'payment_type',
                'bank',
                'gateway_order_id',
                'transaction_id',
                'va_number',
                'qr_string',
                'qr_url',
                'deeplink_url',
                'fee',
                'gateway_status',
                'fraud_status',
                'expires_at',
                'last_webhook_at',
                'raw_response',
            ]);
        });
    }
};