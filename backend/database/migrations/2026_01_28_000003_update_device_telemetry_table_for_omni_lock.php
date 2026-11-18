<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_telemetry', function (Blueprint $table) {
            $table->char('device_ts', 12)->nullable();
            $table->dateTime('received_at')->nullable();
            $table->unsignedBigInteger('op_unix')->nullable();
            $table->ulid('event_ulid')->nullable()->unique();

            $table->index(['imei', 'received_at']);
            $table->index(['type', 'received_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('UPDATE device_telemetry SET received_at = created_at WHERE received_at IS NULL AND created_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('device_telemetry', function (Blueprint $table) {
            $table->dropIndex(['imei', 'received_at']);
            $table->dropIndex(['type', 'received_at']);
            $table->dropUnique(['event_ulid']);

            $table->dropColumn([
                'device_ts',
                'received_at',
                'op_unix',
                'event_ulid',
            ]);
        });
    }
};
