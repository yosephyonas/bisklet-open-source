<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusive_deals', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->string('badge', 60)->nullable();
            $table->string('cta_label', 80)->nullable();
            $table->string('deeplink', 255)->nullable();
            $table->string('image_url', 512)->nullable();
            $table->boolean('is_exclusive')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->index(
                ['is_exclusive', 'is_active', 'starts_at', 'ends_at', 'priority'],
                'exclusive_deals_filters_idx'
            );
            $table->index(
                ['is_exclusive', 'is_active', 'priority'],
                'exclusive_deals_priority_idx'
            );
            $table->index(['starts_at', 'ends_at'], 'exclusive_deals_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusive_deals');
    }
};
