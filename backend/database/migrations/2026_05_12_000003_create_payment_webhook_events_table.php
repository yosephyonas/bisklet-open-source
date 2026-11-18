<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 100);
            $table->string('event_id', 191);
            $table->string('provider_reference')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('status', 50)->default('received');
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'payment_webhooks_provider_event_unique');
            $table->index(['provider', 'provider_reference'], 'payment_webhooks_provider_ref_idx');
            $table->index(['status', 'received_at'], 'payment_webhooks_status_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
