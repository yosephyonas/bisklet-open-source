<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bike_id')->constrained()->cascadeOnDelete();
            $table->foreignId('start_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->foreignId('end_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->decimal('unlock_fee', 10, 2)->default(0);
            $table->decimal('per_minute_rate', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('ETB');
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->json('pricing_breakdown')->nullable();
            $table->json('telemetry')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
