<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('imei')->unique();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->decimal('battery_voltage', 6, 2)->nullable();
            $table->string('lock_state')->nullable();
            $table->string('firmware_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
