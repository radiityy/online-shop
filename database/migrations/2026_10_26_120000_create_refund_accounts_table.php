<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 20);
            // Encrypted by the model cast, so these are text columns.
            $table->text('account_number')->nullable();
            $table->text('account_holder')->nullable();
            // Only the last four digits stay readable, for the masked display and the audit trail.
            $table->string('account_last4', 4)->nullable();
            $table->boolean('name_matches')->default(true);
            $table->boolean('needs_review')->default(false);
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_accounts');
    }
};