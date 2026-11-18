<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lock_statuses', function (Blueprint $table) {
            $table->char('imei', 15)->primary();
            $table->boolean('is_locked')->nullable();
            $table->unsignedSmallInteger('voltage')->nullable();
            $table->tinyInteger('battery_percent')->nullable();
            $table->tinyInteger('signal_strength')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('imei')->references('imei')->on('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lock_statuses');
    }
};
