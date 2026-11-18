<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_telemetry', function (Blueprint $table) {
            $table->id();
            $table->string('imei');
            $table->string('type');
            $table->longText('raw_payload')->nullable();
            $table->json('parsed_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('imei');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_telemetry');
    }
};
