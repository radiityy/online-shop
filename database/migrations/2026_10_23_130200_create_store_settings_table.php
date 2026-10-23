<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('origin_contact_name')->nullable();
            $table->string('origin_contact_phone', 20)->nullable();
            $table->string('origin_area_id', 64)->nullable();
            $table->string('origin_area_name')->nullable();
            $table->string('origin_postal_code', 10)->nullable();
            $table->text('origin_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
