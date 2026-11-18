<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('device_code')->nullable();
            $table->string('firmware_build_date')->nullable();
            $table->string('ble_mac')->nullable();
            $table->string('sim_iccid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'device_code',
                'firmware_build_date',
                'ble_mac',
                'sim_iccid',
            ]);
        });
    }
};
