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
        Schema::create('bikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('model')->nullable();
            $table->string('type')->default('standard');
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->decimal('battery_level', 5, 2)->nullable();
            $table->decimal('odometer_km', 8, 2)->default(0);
            $table->decimal('price_per_minute', 8, 2)->default(0);
            $table->decimal('unlock_fee', 8, 2)->default(0);
            $table->timestamp('last_service_at')->nullable();
            $table->json('hardware')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bikes');
    }
};
