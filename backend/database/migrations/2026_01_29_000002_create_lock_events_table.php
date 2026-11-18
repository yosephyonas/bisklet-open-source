<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lock_events', function (Blueprint $table) {
            $table->id();
            $table->char('imei', 15)->nullable();
            $table->string('type', 50);
            $table->json('payload')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['imei', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lock_events');
    }
};
