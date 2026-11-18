<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lock_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('imei', 15);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('hdop', 8, 2)->nullable();
            $table->tinyInteger('sat')->nullable();
            $table->boolean('is_valid')->default(false);
            $table->dateTime('captured_at');
            $table->string('source', 2)->nullable();
            $table->unsignedBigInteger('raw_event_id')->nullable();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
            $table->foreign('raw_event_id')->references('id')->on('device_telemetry')->nullOnDelete();

            $table->index(['imei', 'captured_at']);
            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lock_locations');
    }
};
