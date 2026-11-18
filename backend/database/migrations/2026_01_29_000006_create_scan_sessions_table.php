<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('imei', 15);
            $table->string('scan_token_hash', 64);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['imei', 'expires_at']);
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_sessions');
    }
};
