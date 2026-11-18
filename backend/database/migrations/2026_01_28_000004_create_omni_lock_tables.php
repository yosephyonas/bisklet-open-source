<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lock_commands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('request_id')->unique();
            $table->char('imei', 15);
            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('command');
            $table->string('status');
            $table->dateTime('requested_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->json('result_json')->nullable();
            $table->tinyInteger('reset_flag')->nullable();
            $table->tinyInteger('cycle_min')->nullable();
            $table->unsignedBigInteger('op_unix')->nullable();

            $table->index(['imei', 'requested_at']);
            $table->index(['user_id', 'requested_at']);
            $table->index('status');
        });

        Schema::create('gps_points', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('imei', 15);
            $table->char('device_ts', 12);
            $table->dateTime('received_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('is_valid');
            $table->tinyInteger('sat')->nullable();
            $table->decimal('hdop', 8, 2)->nullable();
            $table->decimal('alt_m', 8, 2)->nullable();
            $table->string('source', 2);
            $table->unsignedBigInteger('raw_ref_event_id')->nullable();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->foreign('raw_ref_event_id')->references('id')->on('device_telemetry')->nullOnDelete();

            $table->index(['imei', 'received_at']);
            $table->index('received_at');
        });

        Schema::create('alarms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('imei', 15);
            $table->tinyInteger('type');
            $table->char('device_ts', 12);
            $table->dateTime('received_at');
            $table->boolean('is_active')->default(true);
            $table->dateTime('handled_at')->nullable();
            $table->unsignedBigInteger('telemetry_event_id')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->foreign('telemetry_event_id')->references('id')->on('device_telemetry')->nullOnDelete();

            $table->index(['imei', 'received_at']);
            $table->index(['type', 'received_at']);
            $table->index(['is_active', 'received_at']);
        });

        Schema::create('ble_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('imei', 15);
            $table->string('key_value', 64);
            $table->unsignedInteger('version')->nullable();
            $table->dateTime('rotated_at');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->index(['imei', 'rotated_at']);
        });

        Schema::create('firmware_updates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('imei', 15);
            $table->string('target_version');
            $table->string('status');
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->tinyInteger('progress_percent')->default(0);
            $table->text('error_message')->nullable();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->index(['status', 'scheduled_at']);
            $table->index(['imei', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmware_updates');
        Schema::dropIfExists('ble_keys');
        Schema::dropIfExists('alarms');
        Schema::dropIfExists('gps_points');
        Schema::dropIfExists('lock_commands');
    }
};
