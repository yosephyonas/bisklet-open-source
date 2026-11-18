<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->string('imei')->nullable();
            $table->string('type', 80);
            $table->string('title', 160);
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ride_id']);
            $table->index(['imei']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
