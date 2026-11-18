<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('bike_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('imei')->nullable();
            $table->string('source', 80);
            $table->string('severity', 32)->default('warning');
            $table->string('status', 32)->default('open');
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'source', 'occurred_at']);
            $table->index(['imei', 'status']);
            $table->index(['bike_id', 'status']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
    }
};
