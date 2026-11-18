<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->after('metadata');

            $table->unique(['user_id', 'idempotency_key'], 'payments_user_idempotency_unique');
            $table->index(['provider', 'provider_reference'], 'payments_provider_reference_idx');
            $table->index(['status', 'created_at'], 'payments_status_created_idx');
            $table->index(['ride_id', 'status'], 'payments_ride_status_idx');
            $table->index('paid_at', 'payments_paid_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_user_idempotency_unique');
            $table->dropIndex('payments_provider_reference_idx');
            $table->dropIndex('payments_status_created_idx');
            $table->dropIndex('payments_ride_status_idx');
            $table->dropIndex('payments_paid_at_idx');
            $table->dropColumn('idempotency_key');
        });
    }
};
