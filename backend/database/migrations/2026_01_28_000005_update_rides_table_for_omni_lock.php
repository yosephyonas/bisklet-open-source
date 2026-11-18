<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->char('imei', 15)->nullable();
            $table->foreign('imei')->references('imei')->on('devices')->nullOnDelete();

            $table->foreignUlid('start_command_id')->nullable()->constrained('lock_commands')->nullOnDelete();
            $table->foreignId('end_event_id')->nullable()->constrained('device_telemetry')->nullOnDelete();

            $table->unsignedInteger('minutes')->nullable();
            $table->decimal('price_total', 10, 2)->nullable();
            $table->string('billing_status')->default('unbilled');

            $table->index(['imei', 'status']);
            $table->index(['user_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['imei', 'status']);
            $table->dropIndex(['user_id', 'started_at']);
            $table->dropIndex(['status', 'started_at']);

            $table->dropForeign(['imei']);
            $table->dropForeign(['start_command_id']);
            $table->dropForeign(['end_event_id']);

            $table->dropColumn([
                'imei',
                'start_command_id',
                'end_event_id',
                'minutes',
                'price_total',
                'billing_status',
            ]);
        });
    }
};
