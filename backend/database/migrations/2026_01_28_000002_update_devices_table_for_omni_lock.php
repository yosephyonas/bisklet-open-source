<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('model')->nullable();
            $table->string('hardware_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_ip')->nullable();
            $table->tinyInteger('lock_status')->nullable();
            $table->tinyInteger('battery_percent')->nullable();
            $table->tinyInteger('signal_strength')->nullable();
            $table->tinyInteger('gps_sat')->nullable();
            $table->dateTime('last_gps_at')->nullable();
            $table->nullableTimestamps();

            $table->index('last_seen_at');
            $table->index(['is_active', 'last_seen_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE devices MODIFY imei CHAR(15) NOT NULL');
            DB::statement('ALTER TABLE devices MODIFY battery_voltage SMALLINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE devices MODIFY imei VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE devices MODIFY battery_voltage DECIMAL(6,2) NULL');
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['is_active', 'last_seen_at']);

            $table->dropColumn([
                'model',
                'hardware_version',
                'is_active',
                'last_ip',
                'lock_status',
                'battery_percent',
                'signal_strength',
                'gps_sat',
                'last_gps_at',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
