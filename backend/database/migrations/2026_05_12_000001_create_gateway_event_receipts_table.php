<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_event_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('event_hash', 64)->unique();
            $table->string('event_id')->nullable()->index();
            $table->string('event', 100)->index();
            $table->char('imei', 15)->nullable()->index();
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['event', 'received_at']);
            $table->index(['processed_at', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_event_receipts');
    }
};
