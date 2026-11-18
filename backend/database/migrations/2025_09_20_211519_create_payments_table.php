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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ETB');
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
