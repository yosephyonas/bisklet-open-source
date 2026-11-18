<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_log_id')->nullable()->constrained('maintenance_logs')->nullOnDelete();
            $table->string('category', 80);
            $table->string('severity', 32)->default('minor');
            $table->text('description');
            $table->boolean('affects_safety')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('photo_urls')->nullable();
            $table->string('status', 32)->default('open');
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['bike_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
